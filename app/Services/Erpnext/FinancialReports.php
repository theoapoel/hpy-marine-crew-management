<?php

namespace App\Services\Erpnext;

use Illuminate\Support\Carbon;

/**
 * The four accounting screens — General Ledger, Trial Balance, Balance Sheet and
 * Profit & Loss — are ERP HPY's own reports, run through the API and rendered here.
 *
 * Nothing is recalculated. A ledger the app worked out for itself could disagree with
 * the ERP desk, and when two systems disagree about money neither one is trusted, so
 * the reports are asked and their answer is shown.
 *
 * What this class does own is the awkward part of that bargain: a query report answers
 * in whatever shape its author chose — columns as dicts or as "Label:Currency:120"
 * strings, rows as dicts or as bare lists — and every screen would otherwise have to
 * know which. They come out of here as one shape: columns with a fieldname and an
 * alignment, rows keyed by that fieldname.
 */
class FinancialReports
{
    /**
     * The reports, keyed by the slug in the url.
     *
     * `filters` names which inputs the form shows; `builder` turns them into what the
     * report actually wants, which is different for every one of them.
     */
    public const REPORTS = [
        'general-ledger' => [
            'report' => 'General Ledger',
            'title' => 'General Ledger',
            'blurb' => 'Every posting in the period, voucher by voucher.',
            'filters' => ['period', 'account', 'project'],
        ],
        'trial-balance' => [
            'report' => 'Trial Balance',
            'title' => 'Trial Balance',
            'blurb' => 'Opening, movement and closing per account — debits against credits.',
            'filters' => ['period'],
        ],
        'balance-sheet' => [
            'report' => 'Balance Sheet',
            'title' => 'Balance Sheet',
            'blurb' => 'What the company owns and owes at the end of the period.',
            'filters' => ['period', 'periodicity'],
        ],
        'profit-and-loss' => [
            'report' => 'Profit and Loss Statement',
            'title' => 'Profit & Loss',
            'blurb' => 'Income against expense over the period.',
            'filters' => ['period', 'periodicity'],
        ],
    ];

    public function __construct(private readonly ErpnextClient $erpnext)
    {
    }

    /**
     * Run one of them.
     *
     * @param  array<string, mixed>  $input  the form, already validated
     * @return array<string, mixed>
     */
    public function run(string $slug, array $input): array
    {
        $definition = self::REPORTS[$slug];
        $company = $this->erpnext->company();

        $filters = $this->filters($slug, $input, (string) $company);

        try {
            $report = $this->erpnext->report($definition['report'], $filters);
        } catch (ErpnextSessionExpired $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return $definition + [
                'slug' => $slug,
                'columns' => [],
                'rows' => [],
                'input' => $input,
                'error' => $this->message($e),
            ];
        }

        $columns = $this->columns($report['columns']);

        return $definition + [
            'slug' => $slug,
            'columns' => $columns,
            'rows' => $this->rows($report['result'], $columns),
            'input' => $input,
            'error' => null,
        ];
    }

    /**
     * What each report wants asked of it. They share almost nothing: the statements
     * work in periods, the trial balance wants a fiscal year, the ledger wants dates.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function filters(string $slug, array $input, string $company): array
    {
        $from = $input['from_date'];
        $to = $input['to_date'];

        $common = ['company' => $company, 'from_date' => $from, 'to_date' => $to];

        return match ($slug) {
            'general-ledger' => array_filter($common + [
                'account' => $input['account'] ? [$input['account']] : null,
                'project' => $input['project'] ? [$input['project']] : null,
                // One line per voucher rather than per ledger entry: a reader wants
                // the invoice, not its four halves.
                'group_by' => 'Group by Voucher (Consolidated)',
                'include_dimensions' => 1,
            ]),

            'trial-balance' => $common + [
                'fiscal_year' => $this->fiscalYear($to, $company),
                'with_period_closing_entry_for_opening' => 1,
                'show_unclosed_fy_pl_balances' => 0,
            ],

            // The statements are period reports, and say so in their own vocabulary.
            default => [
                'company' => $company,
                'filter_based_on' => 'Date Range',
                'period_start_date' => $from,
                'period_end_date' => $to,
                'periodicity' => $input['periodicity'] ?? 'Yearly',
                'accumulated_values' => 1,
            ],
        };
    }

    /** The fiscal year the period ends in; the trial balance refuses to run without one. */
    private function fiscalYear(string $date, string $company): ?string
    {
        try {
            $years = $this->erpnext->list('Fiscal Year', ['name'], [
                ['year_start_date', '<=', $date],
                ['year_end_date', '>=', $date],
            ], 1);

            return $years[0]['name'] ?? null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Columns, from either shape a query report uses: a dict, or the old
     * "Label:Fieldtype/Options:Width" string.
     *
     * @param  array<int, mixed>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function columns(array $columns): array
    {
        return collect($columns)->map(function ($column, $index) {
            $definition = is_array($column) ? $column : $this->parseColumn((string) $column);

            $type = (string) ($definition['fieldtype'] ?? 'Data');

            return [
                'fieldname' => (string) ($definition['fieldname'] ?? ($definition['label'] ?? 'col' . $index)),
                'label' => (string) ($definition['label'] ?? ($definition['fieldname'] ?? '')),
                'type' => $type,
                'numeric' => in_array($type, ['Currency', 'Float', 'Int', 'Percent'], true),
            ];
        })->values()->all();
    }

    /**
     * "Label:Currency/Account:120" — label, type, and a width nobody needs here.
     *
     * @return array<string, string>
     */
    private function parseColumn(string $column): array
    {
        [$label, $type] = array_pad(explode(':', $column), 2, 'Data');

        return [
            'label' => $label,
            'fieldname' => \Illuminate\Support\Str::snake($label),
            'fieldtype' => explode('/', (string) $type)[0] ?: 'Data',
        ];
    }

    /**
     * Rows keyed by fieldname, whichever way the report answered. A row of nulls is a
     * spacer the report drew for the eye and carries no data — but a total row, which
     * has a label and no account, has to survive.
     *
     * @param  array<int, mixed>  $result
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $result, array $columns): array
    {
        $fieldnames = array_column($columns, 'fieldname');

        return collect($result)
            ->map(fn ($row) => is_array($row) && ! $this->isList($row)
                ? $row
                : array_combine($fieldnames, array_pad(array_slice((array) $row, 0, count($fieldnames)), count($fieldnames), null)))
            ->map(function (array $row) {
                // ERP HPY marks its own total rows by wrapping the label in single
                // quotes — "'Total Income (Credit)'" — which its desk reads as "print
                // this in bold". Read it the same way rather than showing the quotes.
                $total = false;

                foreach ($row as $key => $value) {
                    if (is_string($value) && preg_match("/^'(.*)'$/s", $value, $match)) {
                        $row[$key] = $match[1];
                        $total = true;
                    }
                }

                return $row + [
                    // The statements indent to show the account tree; the ledger does not.
                    'indent' => (int) ($row['indent'] ?? 0),
                    'bold' => $total || (bool) ($row['is_group'] ?? false),
                ];
            })
            ->filter(fn (array $row) => collect($row)->except(['indent', 'bold'])->contains(fn ($value) => filled($value)))
            ->values()
            ->all();
    }

    /** @param array<mixed> $array */
    private function isList(array $array): bool
    {
        return array_is_list($array);
    }

    /** ERP HPY's refusals are worth reading; its stack traces are not. */
    private function message(\Throwable $e): string
    {
        if ($e instanceof \Illuminate\Http\Client\RequestException) {
            $message = (string) ($e->response->json('exception') ?: 'status ' . $e->response->status());

            return trim(preg_replace('/^[\w.]+Error:\s*/', '', strip_tags($message)));
        }

        return $e->getMessage();
    }

    /**
     * The period a screen opens on: this fiscal year so far, falling back to the
     * calendar year when the instance has no fiscal year set up.
     *
     * @return array{from_date: string, to_date: string}
     */
    public function defaultPeriod(): array
    {
        $today = Carbon::today();

        try {
            $years = $this->erpnext->list('Fiscal Year', ['year_start_date'], [
                ['year_start_date', '<=', $today->toDateString()],
                ['year_end_date', '>=', $today->toDateString()],
            ], 1);
        } catch (\Throwable) {
            $years = [];
        }

        return [
            'from_date' => (string) ($years[0]['year_start_date'] ?? $today->copy()->startOfYear()->toDateString()),
            'to_date' => $today->toDateString(),
        ];
    }
}
