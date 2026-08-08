<?php

namespace App\Console\Commands;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Sample money for the Vessel Profitability module, so the screens show something
 * real before the finance team has posted anything of their own.
 *
 * It builds the whole chain the module reads, per vessel:
 *
 *   Project (one per charter)  <- the ship, through the custom Project.vessel field
 *     Sales Invoice            charter hire, manning fee        -> revenue
 *     Purchase Invoice         bunker, port charges, docking    -> cost
 *     Journal Entry            crew wages, travel, medical      -> cost
 *
 * Everything is submitted, because unsubmitted documents are deliberately invisible
 * to the module. Idempotent: a project that is already there is left alone, and so
 * are its documents.
 *
 * This one does touch the chart of accounts — it adds crew expense accounts when the
 * instance has none — which is why it is a command you run on purpose, not a seeder
 * that fires on migrate.
 */
class SeedVesselProfitability extends Command
{
    protected $signature = 'erp:seed-vessel-profitability
                            {--company= : only this company (default: every company that has vessels)}
                            {--vessels=2 : how many vessels to seed, most recent first}
                            {--reset : cancel the sample documents first, and delete them where ERP HPY allows}';

    protected $description = 'Create sample Projects, invoices and journal entries so Vessel Profitability has data';

    private ErpnextClient $erp;

    /** Resolved once per company: the accounts the sample documents post to. */
    private array $accounts = [];

    public function handle(ErpnextClient $client): int
    {
        $this->erp = $client;

        if (! $client->exists('DocType', 'Project')) {
            $this->error('This instance has no Project doctype — install the Projects app first.');

            return self::FAILURE;
        }

        $this->ensureVesselField();

        $vessels = collect($client->list('Vessel', ['name', 'vessel_name', 'company'], $this->vesselFilters(), 50))
            ->take(max((int) $this->option('vessels'), 1));

        if ($vessels->isEmpty()) {
            $this->error('No vessels found. Run `php artisan erp:seed-vessels` first.');

            return self::FAILURE;
        }

        foreach ($vessels as $vessel) {
            if ($this->option('reset')) {
                $this->resetVessel($vessel);
            }

            $this->seedVessel($vessel);
        }

        $this->newLine();
        $this->info('Done. Open /profitability to see it.');

        return self::SUCCESS;
    }

    /** @return array<int, array<int, string>> */
    private function vesselFilters(): array
    {
        $company = $this->option('company') ?: $this->erp->company();

        return $company ? [['company', '=', $company]] : [];
    }

    /** The module is useless without it, so seeding puts it there if it is missing. */
    private function ensureVesselField(): void
    {
        $existing = $this->erp->list('Custom Field', ['name'], [
            ['dt', '=', 'Project'],
            ['fieldname', '=', 'vessel'],
        ], 1);

        if ($existing === []) {
            $this->call('erp:sync-project-fields');
        }
    }

    /**
     * Take the sample charters back out again: cancel every document posted against
     * them, then delete it and the project. Only ever touches projects this command
     * created — it matches on the exact titles in charters().
     *
     * Cancelling is the part that counts, because the module only reads submitted
     * documents. The delete that follows is best-effort: ERP HPY holds on to anything
     * its ledger still references, and a cancelled document left behind is harmless.
     *
     * @param  array<string, mixed>  $vessel
     */
    private function resetVessel(array $vessel): void
    {
        $label = ($vessel['vessel_name'] ?? null) ?: $vessel['name'];
        $titles = array_column($this->charters($label), 'title');

        $projects = collect($this->erp->list('Project', ['name'], [
            ['project_name', 'in', $titles],
            ['company', '=', $vessel['company'] ?? ''],
        ], 50))->pluck('name');

        if ($projects->isEmpty()) {
            return;
        }

        $this->line("<comment>reset</comment> {$label}");

        foreach ([
            'Sales Invoice' => 'Sales Invoice Item',
            'Purchase Invoice' => 'Purchase Invoice Item',
            'Journal Entry' => 'Journal Entry Account',
        ] as $doctype => $child) {
            $documents = collect($this->erp->listChildren($child, $doctype, ['parent'], [
                ['project', 'in', $projects->all()],
            ], 500))->pluck('parent')->unique();

            foreach ($documents as $document) {
                // Cancel through the server method, not by writing docstatus: only the
                // real cancel reverses the ledger entries, and until they are reversed
                // the delete is refused.
                $cancelled = $this->attempt($doctype, fn () => $this->erp->call('frappe.client.cancel', [
                    'doctype' => $doctype,
                    'name' => $document,
                ]));

                if (! $cancelled) {
                    continue;
                }

                if ($this->attempt($doctype, function () use ($doctype, $document) {
                    $this->erp->delete($doctype, $document);

                    return ['name' => $document];
                })) {
                    $this->line("    - {$doctype} {$document}");
                }
            }
        }

        foreach ($projects as $project) {
            if ($this->attempt('Project', function () use ($project) {
                $this->erp->delete('Project', $project);

                return ['name' => $project];
            })) {
                $this->line("    - Project {$project}");
            }
        }
    }

    /** @param array<string, mixed> $vessel */
    private function seedVessel(array $vessel): void
    {
        $company = (string) ($vessel['company'] ?? '');
        $label = ($vessel['vessel_name'] ?? null) ?: $vessel['name'];

        if ($company === '') {
            $this->warn("- {$label}: no company set on the vessel, skipped");

            return;
        }

        $this->newLine();
        $this->line("<comment>{$label}</comment> ({$company})");

        $this->accounts = $this->resolveAccounts($company);
        $customer = $this->ensureParty('Customer', 'Samudera Chartering Nusantara');
        $supplier = $this->ensureParty('Supplier', 'Bahari Marine Supply');

        foreach ($this->charters($label) as $index => $charter) {
            $project = $this->ensureProject($vessel, $company, $customer, $charter);

            if (! $project) {
                continue;
            }

            foreach ($charter['revenue'] as $line) {
                $this->salesInvoice($company, $customer, $project, $charter['month'], $line);
            }

            foreach ($charter['cost'] as $line) {
                $this->purchaseInvoice($company, $supplier, $project, $charter['month'], $line);
            }

            foreach ($charter['crew'] as $line) {
                $this->journalEntry($company, $project, $charter['month'], $line);
            }
        }
    }

    /**
     * Two charters per ship, a quarter apart: one comfortably profitable, one where
     * an unplanned docking eats the margin — the two cases the module has to show
     * clearly apart.
     *
     * @return array<int, array<string, mixed>>
     */
    private function charters(string $vessel): array
    {
        $month = Carbon::today()->startOfMonth();

        return [
            [
                'title' => "Time Charter {$vessel} " . $month->copy()->subMonths(4)->format('M Y'),
                'month' => $month->copy()->subMonths(4),
                'revenue' => [
                    ['item' => 'Charter Hire', 'group' => 'Charter Hire', 'qty' => 30, 'rate' => 42_000_000],
                    ['item' => 'Manning Fee', 'group' => 'Manning Fee', 'qty' => 1, 'rate' => 85_000_000],
                ],
                'cost' => [
                    ['item' => 'Marine Fuel Oil', 'group' => 'Bunker', 'qty' => 40, 'rate' => 8_900_000],
                    ['item' => 'Port Charges', 'group' => 'Port Charges', 'qty' => 1, 'rate' => 96_000_000],
                    ['item' => 'Provisions', 'group' => 'Provision & Stores', 'qty' => 1, 'rate' => 38_000_000],
                ],
                'crew' => [
                    ['account' => 'crew_wages', 'amount' => 310_000_000, 'remark' => 'Crew wages allocation'],
                    ['account' => 'crew_travel', 'amount' => 42_000_000, 'remark' => 'Crew change flights and transport'],
                    ['account' => 'crew_medical', 'amount' => 12_500_000, 'remark' => 'Pre-employment medical checks'],
                ],
            ],
            [
                'title' => "Voyage Charter {$vessel} " . $month->copy()->subMonth()->format('M Y'),
                'month' => $month->copy()->subMonth(),
                'revenue' => [
                    ['item' => 'Charter Hire', 'group' => 'Charter Hire', 'qty' => 24, 'rate' => 39_500_000],
                    ['item' => 'Manning Fee', 'group' => 'Manning Fee', 'qty' => 1, 'rate' => 72_000_000],
                ],
                'cost' => [
                    ['item' => 'Marine Fuel Oil', 'group' => 'Bunker', 'qty' => 35, 'rate' => 9_400_000],
                    ['item' => 'Port Charges', 'group' => 'Port Charges', 'qty' => 1, 'rate' => 88_000_000],
                    // The unplanned one: this is what turns the charter around.
                    ['item' => 'Dry Docking', 'group' => 'Docking & Repair', 'qty' => 1, 'rate' => 410_000_000],
                ],
                'crew' => [
                    ['account' => 'crew_wages', 'amount' => 295_000_000, 'remark' => 'Crew wages allocation'],
                    ['account' => 'crew_travel', 'amount' => 31_000_000, 'remark' => 'Crew change flights and transport'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $vessel
     * @param  array<string, mixed>  $charter
     */
    private function ensureProject(array $vessel, string $company, string $customer, array $charter): ?string
    {
        $existing = $this->erp->list('Project', ['name'], [
            ['project_name', '=', $charter['title']],
            ['company', '=', $company],
        ], 1);

        if ($existing !== []) {
            $this->line("  = {$charter['title']}");

            return $existing[0]['name'];
        }

        $project = $this->attempt('Project', fn () => $this->erp->create('Project', [
            'project_name' => $charter['title'],
            'company' => $company,
            'customer' => $customer,
            'vessel' => $vessel['name'],
            'status' => 'Open',
            'is_active' => 'Yes',
            'expected_start_date' => $charter['month']->toDateString(),
            'expected_end_date' => $charter['month']->copy()->endOfMonth()->toDateString(),
        ]));

        if ($project) {
            $this->info("  + {$charter['title']} ({$project['name']})");
        }

        return $project['name'] ?? null;
    }

    /** @param array<string, mixed> $line */
    private function salesInvoice(string $company, string $customer, string $project, Carbon $month, array $line): void
    {
        $item = $this->ensureItem($line['item'], $line['group']);
        $date = $month->copy()->endOfMonth();

        $this->submit('Sales Invoice', [
            'company' => $company,
            'customer' => $customer,
            'posting_date' => $date->toDateString(),
            'set_posting_time' => 1,
            'due_date' => $date->copy()->addDays(30)->toDateString(),
            'debit_to' => $this->accounts['receivable'],
            'items' => [[
                'item_code' => $item,
                'qty' => $line['qty'],
                'rate' => $line['rate'],
                'project' => $project,
                'income_account' => $this->accounts['income'],
                'cost_center' => $this->accounts['cost_center'],
            ]],
        ], "{$line['item']} · " . number_format($line['qty'] * $line['rate']));
    }

    /** @param array<string, mixed> $line */
    private function purchaseInvoice(string $company, string $supplier, string $project, Carbon $month, array $line): void
    {
        $item = $this->ensureItem($line['item'], $line['group']);
        $date = $month->copy()->addDays(20);

        $this->submit('Purchase Invoice', [
            'company' => $company,
            'supplier' => $supplier,
            'posting_date' => $date->toDateString(),
            'set_posting_time' => 1,
            'due_date' => $date->copy()->addDays(30)->toDateString(),
            'bill_no' => 'INV/' . $month->format('Ym') . '/' . strtoupper(substr(md5($project . $line['item']), 0, 6)),
            'credit_to' => $this->accounts['payable'],
            'items' => [[
                'item_code' => $item,
                'qty' => $line['qty'],
                'rate' => $line['rate'],
                'project' => $project,
                'expense_account' => $this->accounts['expense'],
                'cost_center' => $this->accounts['cost_center'],
            ]],
        ], "{$line['item']} · " . number_format($line['qty'] * $line['rate']));
    }

    /**
     * Crew cost, the way the finance team allocates it today: a journal entry that
     * debits the crew account against the project and credits the payable.
     *
     * @param  array<string, mixed>  $line
     */
    private function journalEntry(string $company, string $project, Carbon $month, array $line): void
    {
        $account = $this->accounts[$line['account']] ?? null;

        if (! $account) {
            $this->warn("    ! no account for {$line['account']}, skipped");

            return;
        }

        $date = $month->copy()->endOfMonth();

        $this->submit('Journal Entry', [
            'company' => $company,
            'voucher_type' => 'Journal Entry',
            'posting_date' => $date->toDateString(),
            'user_remark' => $line['remark'],
            'accounts' => [
                [
                    'account' => $account,
                    'debit_in_account_currency' => $line['amount'],
                    'project' => $project,
                    'cost_center' => $this->accounts['cost_center'],
                    'user_remark' => $line['remark'],
                ],
                [
                    // The other side carries no project: it is not a cost of the ship.
                    'account' => $this->accounts['payable'],
                    'party_type' => 'Supplier',
                    'party' => $this->ensureParty('Supplier', 'Bahari Marine Supply'),
                    'credit_in_account_currency' => $line['amount'],
                ],
            ],
        ], "{$line['remark']} · " . number_format($line['amount']));
    }

    /**
     * Create a document and submit it in one go. Unsubmitted documents are invisible
     * to the module, so a draft left behind would be worse than nothing.
     *
     * @param  array<string, mixed>  $payload
     */
    private function submit(string $doctype, array $payload, string $label): void
    {
        $document = $this->attempt($doctype, fn () => $this->erp->create($doctype, $payload));

        if (! $document) {
            return;
        }

        $submitted = $this->attempt($doctype, fn () => $this->erp->update($doctype, $document['name'], ['docstatus' => 1]));

        if ($submitted) {
            $this->line("    + {$doctype} {$document['name']} — {$label}");
        }
    }

    private function ensureItem(string $name, string $group): string
    {
        $this->ensureItemGroup($group);

        if ($this->erp->exists('Item', $name)) {
            return $name;
        }

        $this->attempt('Item', fn () => $this->erp->create('Item', [
            'item_code' => $name,
            'item_name' => $name,
            'item_group' => $group,
            // Services, so no warehouse, stock ledger or valuation gets in the way.
            'is_stock_item' => 0,
            'stock_uom' => 'Nos',
        ]));

        return $name;
    }

    private function ensureItemGroup(string $group): void
    {
        if ($this->erp->exists('Item Group', $group)) {
            return;
        }

        $this->attempt('Item Group', fn () => $this->erp->create('Item Group', [
            'item_group_name' => $group,
            'parent_item_group' => 'All Item Groups',
            'is_group' => 0,
        ]));
    }

    private function ensureParty(string $doctype, string $name): string
    {
        if ($this->erp->exists($doctype, $name)) {
            return $name;
        }

        $field = strtolower($doctype) . '_name';

        // ERP HPY refuses a party filed under a group node, and which leaf groups a
        // company uses is its own business — so take whatever this instance has.
        $group = $doctype === 'Customer'
            ? ['customer_group' => $this->leaf('Customer Group'), 'territory' => $this->leaf('Territory')]
            : ['supplier_group' => $this->leaf('Supplier Group')];

        $this->attempt($doctype, fn () => $this->erp->create($doctype, array_filter([$field => $name] + $group)));

        return $name;
    }

    /** The first non-group node of a tree doctype. */
    private function leaf(string $doctype): ?string
    {
        $rows = $this->erp->list($doctype, ['name'], [['is_group', '=', 0]], 1);

        return $rows[0]['name'] ?? null;
    }

    /**
     * The accounts the sample documents post to. Existing ones are reused; the crew
     * accounts are created only when the instance genuinely has nothing like them,
     * because without distinct accounts every journal line would collapse into one
     * "Crew Cost" bar and the drill-down would have nothing to show.
     *
     * @return array<string, ?string>
     */
    private function resolveAccounts(string $company): array
    {
        $accounts = collect($this->erp->list(
            'Account',
            ['name', 'account_name', 'root_type', 'account_type', 'is_group', 'parent_account'],
            [['company', '=', $company], ['is_group', '=', 0]],
            2000,
        ));

        $pick = function (string $rootType, array $keywords, ?string $accountType = null) use ($accounts) {
            $pool = $accounts->where('root_type', $rootType);

            if ($accountType) {
                $pool = $pool->where('account_type', $accountType);
            }

            foreach ($keywords as $keyword) {
                $match = $pool->first(fn ($a) => str_contains(mb_strtolower($a['account_name']), $keyword));

                if ($match) {
                    return $match['name'];
                }
            }

            return $pool->first()['name'] ?? null;
        };

        $expenseParent = $accounts->where('root_type', 'Expense')->first()['parent_account'] ?? null;

        $resolved = [
            'income' => $pick('Income', ['sales', 'service', 'freight']),
            'expense' => $pick('Expense', ['cost of goods sold', 'operating', 'direct']),
            'receivable' => $pick('Asset', ['debtor', 'receivable'], 'Receivable'),
            'payable' => $pick('Liability', ['creditor', 'payable'], 'Payable'),
            'cost_center' => $this->costCenter($company),
            'crew_wages' => $pick('Expense', ['crew wages', 'crew', 'salar', 'wages', 'payroll']),
            'crew_travel' => $pick('Expense', ['crew travel', 'travel', 'repatriation']),
            'crew_medical' => $pick('Expense', ['crew medical', 'medical']),
        ];

        // Only when the keyword search fell through to "any expense account" do we add
        // a proper one — a chart of accounts that already names crew cost is left be.
        $crew = ['crew_wages' => ['Crew Wages', 'wage'], 'crew_travel' => ['Crew Travel', 'travel'], 'crew_medical' => ['Crew Medical', 'medical']];

        foreach ($crew as $key => [$label, $keyword]) {
            $current = $accounts->firstWhere('name', $resolved[$key]);
            $alreadyNamed = $current && str_contains(mb_strtolower((string) $current['account_name']), $keyword);

            if ($alreadyNamed || ! $expenseParent) {
                continue;
            }

            $resolved[$key] = $this->ensureAccount($company, $label, $expenseParent) ?? $resolved[$key];
        }

        foreach (['income', 'expense', 'receivable', 'payable'] as $required) {
            if (! $resolved[$required]) {
                $this->warn("  ! no {$required} account found for {$company}; invoices will probably be rejected");
            }
        }

        return $resolved;
    }

    private function ensureAccount(string $company, string $label, string $parent): ?string
    {
        $existing = $this->erp->list('Account', ['name'], [
            ['company', '=', $company],
            ['account_name', '=', $label],
        ], 1);

        if ($existing !== []) {
            return $existing[0]['name'];
        }

        $account = $this->attempt('Account', fn () => $this->erp->create('Account', [
            'account_name' => $label,
            'parent_account' => $parent,
            'company' => $company,
            'root_type' => 'Expense',
            'report_type' => 'Profit and Loss',
            'is_group' => 0,
        ]));

        if ($account) {
            $this->line("  + account {$account['name']}");
        }

        return $account['name'] ?? null;
    }

    private function costCenter(string $company): ?string
    {
        $centers = $this->erp->list('Cost Center', ['name'], [
            ['company', '=', $company],
            ['is_group', '=', 0],
        ], 10);

        return $centers[0]['name'] ?? null;
    }

    /**
     * ERP HPY validates hard and refuses a lot — a missing default, a closed period.
     * One refusal should cost one document, not the whole run, so the message is
     * printed and seeding carries on.
     *
     * @param  \Closure(): array<string, mixed>  $write
     * @return array<string, mixed>|null
     */
    private function attempt(string $doctype, \Closure $write): ?array
    {
        try {
            return $write();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $message = (string) ($e->response->json('exception') ?: $e->response->json('_server_messages') ?: 'status ' . $e->response->status());
            $this->warn("    ! {$doctype} refused: " . trim(preg_replace('/\s+/', ' ', $message)));

            return null;
        } catch (\Throwable $e) {
            $this->warn("    ! {$doctype} failed: {$e->getMessage()}");

            return null;
        }
    }
}
