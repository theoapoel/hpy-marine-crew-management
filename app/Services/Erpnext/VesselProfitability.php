<?php

namespace App\Services\Erpnext;

use Illuminate\Support\Collection;

/**
 * What each ship earns and what it costs, read out of ERP HPY.
 *
 * A vessel does not carry money of its own: it earns through Projects — one per
 * contract, charter or voyage — linked back to the ship by the custom Project.vessel
 * field (`php artisan erp:sync-project-fields`). One vessel has many projects.
 *
 * The figures are added up from the documents rather than taken from Project's own
 * costing fields, so every number on screen can be opened down to the invoice or
 * journal entry that produced it:
 *
 *   revenue  Sales Invoice Item      component from the item group
 *   cost     Purchase Invoice Item   component from the item group
 *   cost     Journal Entry Account   component from the account — crew cost is
 *                                    allocated this way for now
 *
 * Two rules keep the totals honest: only submitted documents count (docstatus 1),
 * and amounts are read in the company currency (`base_*`), so a USD charter and an
 * IDR repair bill can be added together. Everything is scoped to the company picked
 * in the header, like every other module.
 */
class VesselProfitability
{
    /** Projects per "project in (…)" query — keeps the query string sane. */
    private const CHUNK = 40;

    private const PROJECT_FIELDS = [
        'name', 'project_name', 'vessel', 'status', 'customer', 'company',
        'expected_start_date', 'expected_end_date', 'project_type', 'percent_complete',
    ];

    public function __construct(private readonly ErpnextClient $erpnext)
    {
    }

    /**
     * One row per ship: what it earned, what it cost, what is left.
     *
     * @param  array{from?: ?string, to?: ?string}  $window
     * @return array<string, mixed>
     */
    public function fleet(array $window = []): array
    {
        $projects = $this->projects();
        $lines = $this->lines($projects->pluck('name')->all(), $window);
        $names = $this->vesselNames();

        $byProject = $projects->keyBy('name');

        $rows = $lines->groupBy(fn ($line) => (string) ($byProject[$line['project']]['vessel'] ?? ''))
            ->map(fn (Collection $group, string $vessel) => $this->totals($group) + [
                'vessel' => $vessel,
                'label' => $names[$vessel] ?? ($vessel ?: 'Not linked to a vessel'),
            ]);

        // A project with no invoices yet still belongs on the list at zero, otherwise
        // a ship that has only just started work looks like it does not exist.
        foreach ($projects->groupBy(fn ($p) => (string) ($p['vessel'] ?? '')) as $vessel => $group) {
            $rows[$vessel] ??= $this->totals(collect()) + [
                'vessel' => $vessel,
                'label' => $names[$vessel] ?? ($vessel ?: 'Not linked to a vessel'),
            ];
        }

        $counts = $projects->countBy(fn ($p) => (string) ($p['vessel'] ?? ''));

        $rows = $rows->map(fn (array $row) => $row + ['projects' => $counts[$row['vessel']] ?? 0])
            ->sortByDesc('margin')
            ->values();

        return [
            'rows' => $rows,
            'totals' => $this->totals($lines),
            'components' => $this->components($lines),
        ];
    }

    /**
     * One ship: its projects, each with its own bottom line, plus the component
     * breakdown across all of them.
     *
     * @param  array{from?: ?string, to?: ?string}  $window
     * @return array<string, mixed>
     */
    public function vessel(string $vessel, array $window = []): array
    {
        $projects = $this->projects([['vessel', '=', $vessel]]);
        $lines = $this->lines($projects->pluck('name')->all(), $window);
        $byProject = $lines->groupBy('project');

        $rows = $projects->map(fn (array $project) => $project + $this->totals($byProject[$project['name']] ?? collect()))
            ->sortByDesc(fn ($p) => $p['expected_start_date'] ?? '')
            ->values();

        return [
            'vessel' => $vessel,
            'label' => $this->vesselNames()[$vessel] ?? $vessel,
            'projects' => $rows,
            'totals' => $this->totals($lines),
            'components' => $this->components($lines),
        ];
    }

    /**
     * One project, down to the documents — this is where "why is the margin thin"
     * gets answered.
     *
     * @return array<string, mixed>
     */
    public function project(string $project): array
    {
        $document = $this->erpnext->get('Project', $project);

        abort_if($this->outsideCompany($document), 404);

        $lines = $this->lines([$project], []);

        return [
            'project' => (object) $document,
            'totals' => $this->totals($lines),
            'components' => $this->components($lines),
            'lines' => $lines->sortBy([['date', 'desc'], ['document', 'asc']])->values(),
        ];
    }

    /**
     * Projects of the session company.
     *
     * @param  array<mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function projects(array $filters = []): Collection
    {
        if ($company = $this->erpnext->company()) {
            $filters[] = ['company', '=', $company];
        }

        return collect($this->erpnext->list('Project', self::PROJECT_FIELDS, $filters, 500));
    }

    /**
     * Ship code -> the name people actually call it by.
     *
     * @return array<string, string>
     */
    private function vesselNames(): array
    {
        $filters = [];

        if ($company = $this->erpnext->company()) {
            $filters[] = ['company', '=', $company];
        }

        return collect($this->safely(fn () => $this->erpnext->list('Vessel', ['name', 'vessel_name'], $filters, 500)))
            ->mapWithKeys(fn (array $v) => [$v['name'] => ($v['vessel_name'] ?? null) ?: $v['name']])
            ->all();
    }

    /**
     * Every revenue and cost line belonging to these projects, in one shape:
     * kind, component, amount and the document it came from.
     *
     * @param  array<int, string>  $projects
     * @param  array{from?: ?string, to?: ?string}  $window
     * @return Collection<int, array<string, mixed>>
     */
    private function lines(array $projects, array $window): Collection
    {
        if ($projects === []) {
            return collect();
        }

        $lines = collect();

        foreach (array_chunk($projects, self::CHUNK) as $chunk) {
            $lines = $lines
                ->concat($this->invoiceLines('Sales Invoice', $chunk))
                ->concat($this->invoiceLines('Purchase Invoice', $chunk))
                ->concat($this->journalLines($chunk));
        }

        $from = $window['from'] ?? null;
        $to = $window['to'] ?? null;

        return $lines->filter(fn (array $line) => (! $from || $line['date'] >= $from)
            && (! $to || $line['date'] <= $to))->values();
    }

    /**
     * Invoice lines. The project sits on the line, not the header — a single invoice
     * can be split across ships — so the child table is what gets queried, and the
     * parents are fetched afterwards only for their date and party.
     *
     * @param  array<int, string>  $projects
     * @return Collection<int, array<string, mixed>>
     */
    private function invoiceLines(string $doctype, array $projects): Collection
    {
        $sales = $doctype === 'Sales Invoice';

        $rows = collect($this->safely(fn () => $this->erpnext->listChildren(
            $doctype . ' Item',
            $doctype,
            ['parent', 'project', 'item_code', 'item_name', 'item_group', 'base_net_amount'],
            [['project', 'in', $projects], ['docstatus', '=', 1]],
            2000,
        )));

        if ($rows->isEmpty()) {
            return collect();
        }

        $parties = $sales ? 'customer_name' : 'supplier_name';

        $parents = collect($this->safely(fn () => $this->erpnext->list(
            $doctype,
            ['name', 'posting_date', $parties, 'is_return'],
            [['name', 'in', $rows->pluck('parent')->unique()->values()->all()]],
            2000,
        )))->keyBy('name');

        return $rows->map(function (array $row) use ($doctype, $sales, $parents, $parties) {
            $parent = $parents[$row['parent']] ?? [];

            return [
                'doctype' => $doctype,
                'document' => $row['parent'],
                'date' => (string) ($parent['posting_date'] ?? ''),
                'project' => (string) $row['project'],
                'kind' => $sales ? 'revenue' : 'cost',
                'component' => $this->component(
                    (string) ($row['item_group'] ?? ''),
                    'item_groups',
                    $sales ? 'revenue' : 'cost',
                ),
                // A credit note already carries negative amounts, so it subtracts itself.
                'amount' => (float) ($row['base_net_amount'] ?? 0),
                'party' => (string) ($parent[$parties] ?? ''),
                'description' => (string) (($row['item_name'] ?? '') ?: ($row['item_code'] ?? '')),
            ];
        });
    }

    /**
     * Journal entry lines — how crew cost reaches a vessel today. A line is a cost of
     * what it debits net of what it credits, so a correction posted the other way
     * takes the cost back off rather than adding to it.
     *
     * @param  array<int, string>  $projects
     * @return Collection<int, array<string, mixed>>
     */
    private function journalLines(array $projects): Collection
    {
        $rows = collect($this->safely(fn () => $this->erpnext->listChildren(
            'Journal Entry Account',
            'Journal Entry',
            ['parent', 'project', 'account', 'debit', 'credit', 'user_remark'],
            [['project', 'in', $projects], ['docstatus', '=', 1]],
            2000,
        )));

        if ($rows->isEmpty()) {
            return collect();
        }

        $parents = collect($this->safely(fn () => $this->erpnext->list(
            'Journal Entry',
            ['name', 'posting_date', 'title', 'user_remark'],
            [['name', 'in', $rows->pluck('parent')->unique()->values()->all()]],
            2000,
        )))->keyBy('name');

        return $rows->map(function (array $row) use ($parents) {
            $parent = $parents[$row['parent']] ?? [];
            $account = (string) ($row['account'] ?? '');

            return [
                'doctype' => 'Journal Entry',
                'document' => $row['parent'],
                'date' => (string) ($parent['posting_date'] ?? ''),
                'project' => (string) $row['project'],
                'kind' => 'cost',
                'component' => $this->component($this->accountName($account), 'accounts', 'journal'),
                'amount' => (float) ($row['debit'] ?? 0) - (float) ($row['credit'] ?? 0),
                'party' => $account,
                'description' => (string) (($row['user_remark'] ?? '') ?: ($parent['title'] ?? '')),
            ];
        })->filter(fn (array $line) => abs($line['amount']) > 0.001);
    }

    /**
     * Which bucket a line belongs in. Config holds keyword => component; the keyword
     * matches anywhere in the item group or account name, so "Bunker Fuel" and
     * "Crew Salaries Payable" both land where a reader expects them.
     */
    private function component(string $subject, string $map, string $fallback): string
    {
        $subject = mb_strtolower(trim($subject));

        foreach (config("profitability.{$map}", []) as $keyword => $component) {
            if ($subject !== '' && str_contains($subject, (string) $keyword)) {
                return $component;
            }
        }

        return config("profitability.defaults.{$fallback}", 'Other Cost');
    }

    /** An account reads "Crew Salaries - KBM"; the abbreviation is noise here. */
    private function accountName(string $account): string
    {
        return trim(preg_replace('/\s+-\s+[A-Z0-9]+$/', '', $account) ?: $account);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return array<string, float>
     */
    private function totals(Collection $lines): array
    {
        $revenue = (float) $lines->where('kind', 'revenue')->sum('amount');
        $cost = (float) $lines->where('kind', 'cost')->sum('amount');

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'margin' => $revenue - $cost,
            // No revenue yet means no percentage to quote — not a zero margin.
            'margin_pct' => abs($revenue) > 0.001 ? round((($revenue - $cost) / $revenue) * 100, 1) : null,
        ];
    }

    /**
     * The breakdown the whole module exists for: revenue and cost split by component,
     * in the reading order set in config, with anything unlisted after it.
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function components(Collection $lines): array
    {
        $order = array_flip(config('profitability.order', []));

        $split = fn (string $kind) => $lines->where('kind', $kind)
            ->groupBy('component')
            ->map(fn (Collection $group, string $component) => [
                'component' => $component,
                'amount' => (float) $group->sum('amount'),
                'documents' => $group->pluck('document')->unique()->count(),
            ])
            ->sort(fn (array $a, array $b) => [$order[$a['component']] ?? 99, abs($b['amount'])]
                <=> [$order[$b['component']] ?? 99, abs($a['amount'])])
            ->values();

        return [
            'revenue' => $split('revenue'),
            'cost' => $split('cost'),
        ];
    }

    private function outsideCompany(array $document): bool
    {
        $company = $this->erpnext->company();

        return $company && ($document['company'] ?? null) !== $company;
    }

    /**
     * One dead doctype must not take the page down: a instance with no Journal Entry
     * permission, or no Purchase Invoices at all, should still show its revenue.
     *
     * @param  \Closure(): array<int, array<string, mixed>>  $read
     * @return array<int, array<string, mixed>>
     */
    private function safely(\Closure $read): array
    {
        try {
            return $read();
        } catch (ErpnextSessionExpired $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
