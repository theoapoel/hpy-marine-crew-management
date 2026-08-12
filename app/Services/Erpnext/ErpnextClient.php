<?php

namespace App\Services\Erpnext;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

/**
 * Thin wrapper around the ERP HPY REST API.
 *
 * Three ways to authenticate, tried in this order:
 *  1. an API key pair (ERPNEXT_API_KEY/ERPNEXT_API_SECRET) sent as an Authorization
 *     header. Set it and every call to ERP HPY goes out as that one service identity —
 *     no session to expire, no cookie to lose behind a proxy, nothing to re-login;
 *  2. the sid of the user logged in through our own login form (Laravel session);
 *  3. a service account from ERPNEXT_USERNAME/PASSWORD, which logs in for a `sid`
 *     cookie and caches it.
 *
 * Note what choosing 1 gives up: ERP HPY no longer sees who is asking, so its
 * per-user permissions stop filtering anything and every user reads and writes with
 * the key owner's rights. Who may do what is then decided entirely by this app's own
 * Gates and by the company scoping each query carries. Users still log in against ERP
 * HPY — that is where identity, roles and the company list come from; it is only the
 * data calls that travel under the key.
 *
 * Docs: https://frappeframework.com/docs/user/en/api/rest
 */
class ErpnextClient
{
    private const SID_CACHE_KEY = 'erpnext_sid';
    private const SID_TTL = 3600; // seconds

    /** Laravel session key holding the logged-in user's ERP HPY session. */
    public const SESSION_KEY = 'erpnext';

    /** Laravel session key holding the company the user is working in. */
    public const COMPANY_KEY = 'erpnext_company';

    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly int $timeout = 15,
        private readonly bool $verify = true,
        private readonly ?string $apiKey = null,
        private readonly ?string $apiSecret = null,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: rtrim((string) config('services.erpnext.url'), '/'),
            username: config('services.erpnext.username'),
            password: config('services.erpnext.password'),
            timeout: (int) config('services.erpnext.timeout', 15),
            verify: (bool) config('services.erpnext.verify', true),
            apiKey: config('services.erpnext.api_key'),
            apiSecret: config('services.erpnext.api_secret'),
        );
    }

    /** Can we reach ERP HPY at all? Enough for the login form. */
    public function hasUrl(): bool
    {
        return filled($this->baseUrl);
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.erpnext.enabled')
            && $this->hasUrl()
            && ($this->hasUserSession() || $this->hasApiToken() || $this->hasServiceAccount());
    }

    public function hasApiToken(): bool
    {
        return filled($this->apiKey) && filled($this->apiSecret);
    }

    public function hasServiceAccount(): bool
    {
        return filled($this->username) && filled($this->password);
    }

    public function hasUserSession(): bool
    {
        return filled(Session::get(self::SESSION_KEY . '.sid'));
    }

    /**
     * Log a user in against ERP HPY and return their session details.
     * Returns null when ERP HPY rejects the credentials.
     *
     * @return array{sid: string, user: string, full_name: string}|null
     */
    public function attemptLogin(string $usr, string $pwd): ?array
    {
        $response = $this->base()
            ->asForm()
            ->post('/api/method/login', ['usr' => $usr, 'pwd' => $pwd]);

        if ($response->status() === 401) {
            return null;
        }

        $response->throw();

        $sid = $this->sidFrom($response);

        if (! $sid) {
            throw new \RuntimeException('ERP HPY login succeeded but no sid cookie was returned.');
        }

        return [
            'sid' => $sid,
            'user' => $this->loggedUser($sid) ?? $usr,
            'full_name' => (string) ($response->json('full_name') ?: $usr),
        ];
    }

    /**
     * Companies the logged-in user may work in, as plain names.
     *
     * @return array<int, string>
     */
    public function companies(): array
    {
        return array_column($this->list('Company', ['name'], [], 100), 'name');
    }

    /**
     * Companies with what the picker shows: their logo (Company.company_logo) and
     * abbreviation, keyed by name.
     *
     * @return array<string, array{logo: ?string, abbr: ?string}>
     */
    public function companyProfiles(): array
    {
        $rows = $this->list('Company', ['name', 'company_logo', 'abbr'], [], 100);

        return collect($rows)->mapWithKeys(fn ($row) => [
            $row['name'] => ['logo' => $row['company_logo'] ?? null, 'abbr' => $row['abbr'] ?? null],
        ])->all();
    }

    /** The company the user picked at login, else the configured default. */
    public function company(): ?string
    {
        return Session::get(self::COMPANY_KEY) ?: (config('services.erpnext.company') ?: null);
    }

    /** Invalidate the ERP HPY side of a user session. Failures are not fatal. */
    public function logout(string $sid): void
    {
        try {
            $this->request($sid)->get('/api/method/logout');
        } catch (\Throwable) {
            // session will expire on its own
        }
    }

    /**
     * Role names granted to an ERP HPY user; the app authorises against these.
     *
     * @return array<int, string>
     */
    public function rolesOf(string $user): array
    {
        try {
            $rows = $this->listChildren('Has Role', 'User', ['role'], [['parent', '=', $user]], 200);

            return array_values(array_filter(array_column($rows, 'role')));
        } catch (\Throwable) {
            return [];
        }
    }

    /** The ERP HPY user id (email) behind a sid. */
    private function loggedUser(string $sid): ?string
    {
        try {
            return $this->request($sid)
                ->get('/api/method/frappe.auth.get_logged_user')
                ->throw()
                ->json('message');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * List documents of a Doctype.
     *
     * @param  array<string>  $fields
     * @param  array<mixed>   $filters  Frappe filter format, e.g. [['status', '=', 'Active']]
     * @return array<int, array<string, mixed>>
     */
    public function list(string $doctype, array $fields = ['*'], array $filters = [], int $limit = 100, int $start = 0, array $extra = []): array
    {
        return $this->send(fn (PendingRequest $r) => $r->get("/api/resource/{$doctype}", $extra + [
            'fields' => json_encode($fields),
            'filters' => json_encode($filters),
            'limit_page_length' => $limit,
            'limit_start' => $start,
        ]))->json('data', []);
    }

    /**
     * Rows of a child table (e.g. "Employee Certificate"). ERP HPY only allows querying
     * a child Doctype when the parent Doctype is named.
     *
     * @param  array<string>  $fields
     * @param  array<mixed>   $filters
     * @return array<int, array<string, mixed>>
     */
    public function listChildren(string $childDoctype, string $parentDoctype, array $fields = ['*'], array $filters = [], int $limit = 500): array
    {
        return $this->list($childDoctype, $fields, $filters, $limit, 0, ['parent' => $parentDoctype]);
    }

    /** @return array<string, mixed> */
    public function get(string $doctype, string $name): array
    {
        return $this->send(fn (PendingRequest $r) => $r->get("/api/resource/{$doctype}/" . rawurlencode($name)))
            ->json('data', []);
    }

    /** Does a document exist? Cheaper and quieter than get() + catch. */
    public function exists(string $doctype, string $name): bool
    {
        return $this->list($doctype, ['name'], [['name', '=', $name]], 1) !== [];
    }

    /** @param array<string, mixed> $data */
    public function create(string $doctype, array $data): array
    {
        return $this->send(fn (PendingRequest $r) => $r->post("/api/resource/{$doctype}", $data))
            ->json('data', []);
    }

    /** @param array<string, mixed> $data */
    public function update(string $doctype, string $name, array $data): array
    {
        return $this->send(fn (PendingRequest $r) => $r->put("/api/resource/{$doctype}/" . rawurlencode($name), $data))
            ->json('data', []);
    }

    /**
     * Upload a file into ERP HPY and attach it to a document.
     * Returns the stored file_url, which is what Attach fields hold.
     */
    public function upload(string $contents, string $filename, string $doctype, string $docname, bool $private = true): string
    {
        $response = $this->send(fn (PendingRequest $r) => $r
            ->attach('file', $contents, $filename)
            ->post('/api/method/upload_file', [
                'is_private' => $private ? 1 : 0,
                'doctype' => $doctype,
                'docname' => $docname,
                'folder' => 'Home/Attachments',
            ]));

        return (string) $response->json('message.file_url');
    }

    /**
     * Fetch an uploaded file (an Attach field's file_url) using the session's own
     * credentials. Private files live behind the ERP HPY login, which the user's
     * browser has no cookie for — so the app fetches them instead.
     *
     * @return array{contents: string, content_type: string}
     */
    public function download(string $fileUrl): array
    {
        // Ask for the file itself, not the json ERP HPY sends when Accept is json.
        $response = $this->send(fn (PendingRequest $r) => $r->replaceHeaders(['Accept' => '*/*'])->get($fileUrl));

        return [
            'contents' => $response->body(),
            'content_type' => $response->header('Content-Type') ?: 'application/octet-stream',
        ];
    }

    /**
     * Run one of ERP HPY's own reports and hand back what it produced.
     *
     * The financial statements are query reports, not doctypes: there is nothing to
     * list. Running them here rather than reimplementing the arithmetic means the app
     * and the ERP desk can never disagree about what the ledger says.
     *
     * `ignore_prepared_report` keeps the answer synchronous — a prepared report would
     * come back as a job id and an empty table.
     *
     * @param  array<string, mixed>  $filters
     * @return array{columns: array<int, mixed>, result: array<int, mixed>}
     */
    public function report(string $report, array $filters): array
    {
        $message = $this->send(fn (PendingRequest $r) => $r->get('/api/method/frappe.desk.query_report.run', [
            'report_name' => $report,
            'filters' => json_encode($filters),
            'ignore_prepared_report' => 1,
            'are_default_filters' => 'false',
        ]))->json('message', []);

        return [
            'columns' => $message['columns'] ?? [],
            'result' => $message['result'] ?? [],
        ];
    }

    /**
     * Call a whitelisted server method.
     *
     * Some things are not a document write and cannot be done through the resource
     * API — cancelling is the one that matters here: writing docstatus 2 straight onto
     * a document skips the unlinking ERP HPY does on a real cancel, and the delete
     * that follows is then refused.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function call(string $method, array $params = []): array
    {
        return $this->send(fn (PendingRequest $r) => $r->post('/api/method/' . $method, $params))->json() ?? [];
    }

    public function delete(string $doctype, string $name): void
    {
        $this->send(fn (PendingRequest $r) => $r->delete("/api/resource/{$doctype}/" . rawurlencode($name)));
    }

    /**
     * Run an authenticated request, re-logging in once if the session expired.
     *
     * @param  \Closure(PendingRequest): \Illuminate\Http\Client\Response  $callback
     */
    private function send(\Closure $callback): \Illuminate\Http\Client\Response
    {
        try {
            return $callback($this->authorized())->throw();
        } catch (RequestException $e) {
            if ($e->response->status() !== 401) {
                throw $e;
            }

            // A rejected token is a wrong or revoked key pair; retrying cannot fix it,
            // and there is no user session involved to send back to the login form.
            if ($this->hasApiToken()) {
                throw $e;
            }

            // A user session cannot be renewed without their password: send them back
            // to the login form. A service account session can just be re-established.
            if ($this->hasUserSession()) {
                Session::forget(self::SESSION_KEY);

                throw new ErpnextSessionExpired('Your ERP HPY session has expired.', previous: $e);
            }

            Cache::forget(self::SID_CACHE_KEY);

            return $callback($this->request($this->sid()))->throw();
        }
    }

    /**
     * The request the API is actually called with: the logged-in user's session when
     * there is one, otherwise the server's own credentials.
     */
    private function authorized(): PendingRequest
    {
        if ($this->hasApiToken()) {
            return $this->base()->withHeaders([
                'Authorization' => "token {$this->apiKey}:{$this->apiSecret}",
            ]);
        }

        return $this->request($this->sid());
    }

    private function base(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->withOptions(['verify' => $this->verify])
            ->acceptJson();
    }

    private function request(string $sid): PendingRequest
    {
        return $this->base()->withCookies(['sid' => $sid], $this->cookieDomain());
    }

    /** The logged-in user's sid, else the cached service account sid. */
    private function sid(): string
    {
        $sid = Session::get(self::SESSION_KEY . '.sid');

        if (filled($sid)) {
            return $sid;
        }

        if (! $this->hasServiceAccount()) {
            throw new \RuntimeException(
                'No ERP HPY session: log in, or set ERPNEXT_API_KEY/ERPNEXT_API_SECRET (or ERPNEXT_USERNAME/PASSWORD).'
            );
        }

        return Cache::remember(self::SID_CACHE_KEY, self::SID_TTL, fn () => $this->login());
    }

    private function login(): string
    {
        $response = $this->base()
            ->asForm()
            ->post('/api/method/login', [
                'usr' => $this->username,
                'pwd' => $this->password,
            ])->throw();

        $sid = $this->sidFrom($response);

        if (! $sid) {
            throw new \RuntimeException('ERP HPY login succeeded but no sid cookie was returned.');
        }

        return $sid;
    }

    private function sidFrom(\Illuminate\Http\Client\Response $response): ?string
    {
        return collect($response->cookies()->toArray())->firstWhere('Name', 'sid')['Value'] ?? null;
    }

    private function cookieDomain(): string
    {
        return (string) parse_url($this->baseUrl, PHP_URL_HOST);
    }
}
