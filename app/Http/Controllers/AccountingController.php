<?php

namespace App\Http\Controllers;

use App\Services\Erpnext\AccountingCharts;
use App\Services\Erpnext\ErpnextClient;
use App\Services\Erpnext\FinancialReports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The accounting screens: General Ledger, Trial Balance, Balance Sheet, Profit & Loss.
 *
 * One controller for all four, because they are one thing — ERP HPY's own reports,
 * run over the company picked in the header and rendered as they came back. The
 * differences between them are filters, and those live in FinancialReports::REPORTS.
 */
class AccountingController extends Controller
{
    public function __construct(
        private readonly FinancialReports $reports,
        private readonly ErpnextClient $erpnext,
        private readonly AccountingCharts $charts,
    ) {}

    public function show(Request $request, string $report)
    {
        Gate::authorize('accounting.viewAny');

        $definition = FinancialReports::REPORTS[$report];
        $input = $this->input($request, $definition['filters']);

        $result = $this->reports->run($report, $input);

        return view('accounting.report', $result + $this->charts->build($report, $result['columns'], $result['rows']) + [
            'reports' => FinancialReports::REPORTS,
            'accounts' => in_array('account', $definition['filters'], true) ? $this->accounts() : [],
            'projects' => in_array('project', $definition['filters'], true) ? $this->projects() : [],
        ]);
    }

    /**
     * The form. Dates default to the fiscal year so far — the period someone opening
     * a ledger on a Tuesday morning almost always means.
     *
     * @param  array<int, string>  $wanted
     * @return array<string, mixed>
     */
    private function input(Request $request, array $wanted): array
    {
        $data = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'account' => ['nullable', 'string', 'max:150'],
            'project' => ['nullable', 'string', 'max:150'],
            'periodicity' => ['nullable', 'in:Monthly,Quarterly,Half-Yearly,Yearly'],
        ]);

        $period = $this->reports->defaultPeriod();

        return [
            'wanted' => $wanted,
            'from_date' => ($data['from_date'] ?? null) ?: $period['from_date'],
            'to_date' => ($data['to_date'] ?? null) ?: $period['to_date'],
            'account' => $data['account'] ?? null,
            'project' => $data['project'] ?? null,
            'periodicity' => $data['periodicity'] ?? 'Monthly',
        ];
    }

    /**
     * Postable accounts of this company — the group nodes are headings, not something
     * a ledger can be filtered to.
     *
     * @return array<int, string>
     */
    private function accounts(): array
    {
        return array_column($this->safely(fn () => $this->erpnext->list('Account', ['name'], array_filter([
            $this->companyFilter(),
            ['is_group', '=', 0],
        ]), 2000)), 'name');
    }

    /** @return array<int, string> */
    private function projects(): array
    {
        return array_column($this->safely(fn () => $this->erpnext->list('Project', ['name'], array_filter([
            $this->companyFilter(),
        ]), 500)), 'name');
    }

    /** @return array<int, string>|null */
    private function companyFilter(): ?array
    {
        $company = $this->erpnext->company();

        return $company ? ['company', '=', $company] : null;
    }

    /**
     * A filter dropdown that cannot be filled is not worth a 500 — the report itself
     * is the page, and it runs without them.
     *
     * @param  \Closure(): array<int, array<string, mixed>>  $read
     * @return array<int, array<string, mixed>>
     */
    private function safely(\Closure $read): array
    {
        try {
            return $read();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
