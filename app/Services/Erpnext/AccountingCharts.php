<?php

namespace App\Services\Erpnext;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Headline figures and chart series for the accounting screens, read off the rows
 * ERP HPY's own report returned. Nothing is recomputed from the ledger: a chart only
 * regroups what the table beneath it already shows.
 *
 * Each builder returns ['kpis' => [...], 'charts' => [...]]. A chart is either
 *   ['type' => 'columns', 'title', 'labels', 'series' => [[name, color, values]], 'diverging'?]
 * or
 *   ['type' => 'bars', 'title', 'rows' => [[label, value, tip?]]].
 */
class AccountingCharts
{
    /** Categorical order from the dashboard palette, validated for colour-blind contrast. */
    public const BLUE = '#1a73e8';

    public const ORANGE = '#e8710a';

    public const PURPLE = '#9334e6';

    public const GREY = '#9aa0a6';

    /** Account classes of the chart of accounts, by leading digit. */
    private const CLASSES = ['1' => 'Assets', '2' => 'Liabilities', '3' => 'Equity', '4' => 'Income', '5' => 'Expenses'];

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{kpis: array<int, array<string, mixed>>, charts: array<int, array<string, mixed>>}
     */
    public function build(string $slug, array $columns, array $rows): array
    {
        if ($rows === []) {
            return ['kpis' => [], 'charts' => []];
        }

        return match ($slug) {
            'profit-and-loss' => $this->profitAndLoss($columns, $rows),
            'balance-sheet' => $this->balanceSheet($columns, $rows),
            'trial-balance' => $this->trialBalance($rows),
            'general-ledger' => $this->generalLedger($rows),
            default => ['kpis' => [], 'charts' => []],
        };
    }

    private function profitAndLoss(array $columns, array $rows): array
    {
        $periods = $this->periods($columns);
        $income = $this->totalRow($rows, 'income');
        $expense = $this->totalRow($rows, 'expense');
        $profit = $this->totalRow($rows, 'profit');

        $sum = fn (?array $row) => $row ? (float) ($row['total'] ?? collect($periods)->sum(fn ($p) => (float) ($row[$p['fieldname']] ?? 0))) : 0.0;
        [$in, $out, $net] = [$sum($income), $sum($expense), $sum($profit)];

        $kpis = [
            ['label' => 'Income', 'value' => $in, 'color' => self::BLUE],
            ['label' => 'Expenses', 'value' => $out, 'color' => self::ORANGE],
            ['label' => $net >= 0 ? 'Net profit' : 'Net loss', 'value' => $net, 'signed' => true],
            ['label' => 'Net margin', 'percent' => $in != 0.0 ? $net / $in * 100 : null, 'signed' => true],
        ];

        $charts = [];
        if (count($periods) > 1) {
            $charts[] = [
                'type' => 'columns', 'wide' => true,
                'title' => 'Income vs expenses by period',
                'labels' => array_column($periods, 'label'),
                'series' => [
                    ['name' => 'Income', 'color' => self::BLUE, 'values' => $this->values($income, $periods)],
                    ['name' => 'Expenses', 'color' => self::ORANGE, 'values' => $this->values($expense, $periods)],
                ],
            ];
            $charts[] = [
                'type' => 'columns', 'diverging' => true,
                'title' => 'Profit / loss by period',
                'labels' => array_column($periods, 'label'),
                'series' => [['name' => 'Profit / loss', 'color' => self::BLUE, 'values' => $this->values($profit, $periods)]],
            ];
        }

        $charts[] = $this->breakdownChart('Top expenses', $rows, '5', 'total', $periods, self::ORANGE);

        return ['kpis' => $kpis, 'charts' => array_values(array_filter($charts, fn ($c) => $c['type'] !== 'bars' || $c['rows'] !== []))];
    }

    private function balanceSheet(array $columns, array $rows): array
    {
        $periods = $this->periods($columns);
        $last = end($periods)['fieldname'] ?? null;
        $asset = $this->totalRow($rows, 'asset');
        $liability = $this->totalRow($rows, 'liability');
        $equity = $this->totalRow($rows, 'equity');
        $at = fn (?array $row) => $row && $last ? (float) ($row[$last] ?? 0) : 0.0;

        $kpis = [
            ['label' => 'Total assets', 'value' => $at($asset), 'color' => self::BLUE],
            ['label' => 'Liabilities', 'value' => $at($liability), 'color' => self::ORANGE],
            ['label' => 'Equity', 'value' => $at($equity), 'color' => self::PURPLE],
            ['label' => 'Debt to assets', 'percent' => $at($asset) != 0.0 ? $at($liability) / $at($asset) * 100 : null],
        ];

        $charts = [];
        if (count($periods) > 1) {
            $charts[] = [
                'type' => 'columns', 'wide' => true,
                'title' => 'Assets, liabilities and equity',
                'labels' => array_column($periods, 'label'),
                'series' => [
                    ['name' => 'Assets', 'color' => self::BLUE, 'values' => $this->values($asset, $periods)],
                    ['name' => 'Liabilities', 'color' => self::ORANGE, 'values' => $this->values($liability, $periods)],
                    ['name' => 'Equity', 'color' => self::PURPLE, 'values' => $this->values($equity, $periods)],
                ],
            ];
        }
        $charts[] = $this->breakdownChart('Asset composition', $rows, '1', $last, $periods, self::BLUE);
        $charts[] = $this->breakdownChart('Liability composition', $rows, '2', $last, $periods, self::ORANGE);

        return ['kpis' => $kpis, 'charts' => array_values(array_filter($charts, fn ($c) => $c['type'] !== 'bars' || $c['rows'] !== []))];
    }

    private function trialBalance(array $rows): array
    {
        $accounts = collect($rows)->filter(fn ($r) => preg_match('/^\d/', (string) ($r['account'] ?? '')) && empty($r['is_group']));
        $debit = (float) $accounts->sum(fn ($r) => (float) ($r['debit'] ?? 0));
        $credit = (float) $accounts->sum(fn ($r) => (float) ($r['credit'] ?? 0));

        $byClass = $accounts->groupBy(fn ($r) => self::CLASSES[substr((string) $r['account'], 0, 1)] ?? 'Other');
        $labels = array_values(array_filter([...array_values(self::CLASSES), 'Other'], fn ($c) => $byClass->has($c)));

        return [
            'kpis' => [
                ['label' => 'Total debit', 'value' => $debit, 'color' => self::BLUE],
                ['label' => 'Total credit', 'value' => $credit, 'color' => self::ORANGE],
                ['label' => 'Difference', 'value' => $debit - $credit, 'note' => abs($debit - $credit) < 1 ? 'Balanced' : 'Not balanced', 'ok' => abs($debit - $credit) < 1],
                ['label' => 'Accounts with movement', 'count' => $accounts->filter(fn ($r) => (float) ($r['debit'] ?? 0) || (float) ($r['credit'] ?? 0))->count()],
            ],
            'charts' => [
                [
                    'type' => 'columns', 'wide' => true,
                    'title' => 'Debit vs credit by account class',
                    'labels' => $labels,
                    'series' => [
                        ['name' => 'Debit', 'color' => self::BLUE, 'values' => array_map(fn ($c) => round((float) $byClass[$c]->sum(fn ($r) => (float) ($r['debit'] ?? 0))), $labels)],
                        ['name' => 'Credit', 'color' => self::ORANGE, 'values' => array_map(fn ($c) => round((float) $byClass[$c]->sum(fn ($r) => (float) ($r['credit'] ?? 0))), $labels)],
                    ],
                ],
            ],
        ];
    }

    private function generalLedger(array $rows): array
    {
        $entries = collect($rows)->filter(fn ($r) => filled($r['posting_date'] ?? null));
        if ($entries->isEmpty()) {
            return ['kpis' => [], 'charts' => []];
        }

        $dates = $entries->pluck('posting_date')->sort()->values();
        $daily = Carbon::parse($dates->first())->diffInDays(Carbon::parse($dates->last())) <= 62;
        $bucket = fn ($r) => $daily ? (string) $r['posting_date'] : substr((string) $r['posting_date'], 0, 7);
        $groups = $entries->groupBy($bucket)->sortKeys();
        $labels = $groups->keys()->map(fn ($k) => $daily ? Carbon::parse($k)->format('d M') : Carbon::parse($k.'-01')->format('M Y'))->all();

        $top = $entries->groupBy('account')
            ->map(fn ($g, $account) => ['account' => $account, 'debit' => (float) $g->sum('debit'), 'credit' => (float) $g->sum('credit')])
            ->sortByDesc(fn ($a) => $a['debit'] + $a['credit'])->take(8)->values();

        return [
            'kpis' => [
                ['label' => 'Total debit', 'value' => (float) $entries->sum('debit'), 'color' => self::BLUE],
                ['label' => 'Total credit', 'value' => (float) $entries->sum('credit'), 'color' => self::ORANGE],
                ['label' => 'Vouchers', 'count' => $entries->pluck('voucher_no')->filter()->unique()->count()],
                ['label' => 'Accounts', 'count' => $entries->pluck('account')->unique()->count()],
            ],
            'charts' => [
                [
                    'type' => 'columns', 'wide' => true,
                    'title' => $daily ? 'Movement by day' : 'Movement by month',
                    'labels' => $labels,
                    // Every voucher balances, so debit and credit per period are the same
                    // number twice; one series carries it, the tooltip adds the count.
                    'series' => [
                        ['name' => 'Total movement', 'color' => self::BLUE, 'values' => $groups->map(fn ($g) => round((float) $g->sum('debit')))->values()->all()],
                    ],
                    'notes' => $groups->map(fn ($g) => $g->pluck('voucher_no')->filter()->unique()->count().' vouchers')->values()->all(),
                ],
                [
                    'type' => 'bars', 'wide' => true,
                    'title' => 'Most active accounts',
                    'rows' => $top->map(fn ($a) => [
                        'label' => $this->short($a['account']),
                        'value' => (int) round($a['debit'] + $a['credit']),
                        'display' => self::money($a['debit'] + $a['credit']),
                        'color' => self::BLUE,
                        'tip' => 'Debit '.self::money($a['debit']).' · Credit '.self::money($a['credit']),
                    ])->all(),
                ],
            ],
        ];
    }

    /**
     * Period columns of a statement (Jan 2026, Q1…), without the Total ERP appends.
     *
     * @return array<int, array{fieldname: string, label: string}>
     */
    private function periods(array $columns): array
    {
        return collect($columns)
            ->filter(fn ($c) => $c['numeric'] && $c['fieldname'] !== 'total')
            ->map(fn ($c) => ['fieldname' => $c['fieldname'], 'label' => $c['label']])
            ->values()->all();
    }

    /** ERP's own total rows: "Total Income (Credit)", "Profit for the year", … */
    private function totalRow(array $rows, string $kind): ?array
    {
        $pattern = match ($kind) {
            'income' => '/^Total Income/i',
            'expense' => '/^Total Expense/i',
            'profit' => '/^(Profit for the year|Net Profit)/i',
            'asset' => '/^Total Asset/i',
            'liability' => '/^Total Liabilit/i',
            'equity' => '/^Total Equity/i',
        };

        return collect($rows)->first(fn ($r) => preg_match($pattern, trim((string) ($r['account'] ?? ''))));
    }

    /** @return array<int, float> */
    private function values(?array $row, array $periods): array
    {
        return array_map(fn ($p) => $row ? round((float) ($row[$p['fieldname']] ?? 0)) : 0, $periods);
    }

    /**
     * The biggest accounts one level under a root (5 = expenses, 1 = assets, …),
     * at two levels down where the tree has them. Top seven, the rest as "Other".
     *
     * @return array<int, array<string, mixed>>
     */
    private function breakdown(array $rows, string $class, ?string $field, array $periods): array
    {
        $value = fn ($r) => $field
            ? (float) ($r[$field] ?? 0)
            : (float) collect($periods)->sum(fn ($p) => (float) ($r[$p['fieldname']] ?? 0));

        $in = collect($rows)->filter(fn ($r) => str_starts_with((string) ($r['account'] ?? ''), $class) && filled($r['account_name'] ?? null));
        $depth = $in->where('indent', 2)->isNotEmpty() ? 2 : 1;
        $all = $in->where('indent', $depth)->map(fn ($r) => ['label' => $this->short($r['account']), 'value' => $value($r)]);
        $items = $all->filter(fn ($r) => $r['value'] > 0)->sortByDesc('value')->values();

        // A bar cannot show a credit balance on a debit account (or the reverse), yet
        // leaving it out makes the bars add up to more than the total. Say so instead.
        $this->negatives = $all->filter(fn ($r) => $r['value'] < 0)->sortBy('value')
            ->map(fn ($r) => $r['label'].' '.self::money($r['value']))->values()->all();

        $shown = $items->take(7)->all();
        if ($items->count() > 7) {
            $shown[] = ['label' => 'Other ('.($items->count() - 7).' accounts)', 'value' => $items->slice(7)->sum('value'), 'color' => self::GREY];
        }

        return array_map(fn ($r) => [
            'label' => $r['label'],
            'value' => (int) round($r['value']),
            'display' => self::money($r['value']),
        ] + array_intersect_key($r, ['color' => true]), $shown);
    }

    /** @var array<int, string> accounts the last breakdown left out for a negative balance */
    private array $negatives = [];

    /** A bars chart of the biggest accounts under a class, noting any left out as negative. */
    private function breakdownChart(string $title, array $rows, string $class, ?string $field, array $periods, string $color): array
    {
        $bars = array_map(fn ($r) => $r + ['color' => $color], $this->breakdown($rows, $class, $field, $periods));

        return ['type' => 'bars', 'title' => $title, 'rows' => $bars]
            + ($this->negatives ? ['note' => 'Negative balance, not drawn: '.implode(' · ', $this->negatives)] : []);
    }

    /** "5110.022 - Beban Usaha - KBM" → "5110.022 Beban Usaha" */
    private function short(string $account): string
    {
        $account = preg_replace('/\s+-\s+[A-Z]{2,6}$/', '', $account);

        return Str::limit((string) preg_replace('/^([\d.]+)\s+-\s+/', '$1 ', (string) $account), 42);
    }

    /** Rp in short scale, like the tables' English number format: 1.94B · 224M · 12K. */
    public static function money(float $value): string
    {
        $abs = abs($value);
        [$div, $unit] = match (true) {
            $abs >= 1e12 => [1e12, 'T'],
            $abs >= 1e9 => [1e9, 'B'],
            $abs >= 1e6 => [1e6, 'M'],
            $abs >= 1e3 => [1e3, 'K'],
            default => [1, ''],
        };
        $digits = $div === 1 ? 0 : ($abs / $div >= 100 ? 0 : ($abs / $div >= 10 ? 1 : 2));

        return ($value < 0 ? '−' : '').'Rp '.number_format($abs / $div, $digits).$unit;
    }
}
