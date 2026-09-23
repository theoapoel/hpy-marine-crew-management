<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Erpnext\ErpnextClient;
use App\Support\ErpUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Authenticates users directly against ERP HPY — no local user table.
 * The returned sid is kept in the Laravel session and reused for every API call.
 */
class LoginController extends Controller
{
    public function __construct(private readonly ErpnextClient $erpnext) {}

    public function show(Request $request)
    {
        if ($request->session()->has(ErpnextClient::SESSION_KEY.'.sid')) {
            return redirect()->route('company.select');
        }

        return view('auth.login', [
            'erpUrl' => config('services.erpnext.url'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'usr' => ['required', 'string'],
            'pwd' => ['required', 'string'],
        ]);

        if (! $this->erpnext->hasUrl()) {
            throw ValidationException::withMessages([
                'usr' => 'ERPNEXT_URL is not set in .env.',
            ]);
        }

        $this->ensureNotRateLimited($request);

        try {
            $session = $this->erpnext->attemptLogin($credentials['usr'], $credentials['pwd']);
        } catch (\Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'usr' => 'Cannot reach ERP HPY: '.$e->getMessage(),
            ]);
        }

        if ($session === null) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'usr' => 'Wrong ERP HPY username or password.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();
        $request->session()->put(ErpnextClient::SESSION_KEY, $session);

        // Roles decide what the user may do in this app; read them once per session.
        $request->session()->put(ErpUser::ROLES_KEY, $this->erpnext->rolesOf($session['user']));

        // Which company to work in comes next; that page falls through to the
        // originally requested page (or the dashboard) once one is picked.
        return redirect()->route('company.select');
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($sid = $request->session()->get(ErpnextClient::SESSION_KEY.'.sid')) {
            $this->erpnext->logout($sid);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function ensureNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        throw ValidationException::withMessages([
            'usr' => 'Too many sign-in attempts. Try again in '
                .RateLimiter::availableIn($this->throttleKey($request)).' seconds.',
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return 'erpnext-login|'.mb_strtolower((string) $request->input('usr')).'|'.$request->ip();
    }
}
