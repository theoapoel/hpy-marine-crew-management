<?php

namespace Tests\Feature;

use App\Services\Erpnext\ErpnextClient;
use App\Support\ErpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccountingReportTest extends TestCase
{
    use RefreshDatabase;

    private const KBM = 'Keenindo Bintas Marine';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.erpnext.url', 'https://kbm.hpy.co.id');
        config()->set('services.erpnext.company', null);

        $this->withSession([
            ErpnextClient::SESSION_KEY => ['sid' => 'abc123', 'user' => 'budi@hpy.co.id', 'full_name' => 'Budi'],
            ErpnextClient::COMPANY_KEY => self::KBM,
            ErpUser::ROLES_KEY => ['HR Manager'],
        ]);
    }

    /** @param array<string, mixed> $message */
    private function fakeReport(array $message): void
    {
        Http::fake([
            '*frappe.desk.query_report.run*' => Http::response(['message' => $message]),
            '*' => Http::response(['data' => []]),
        ]);
    }

    public function test_it_renders_a_statement_with_dict_columns_and_dict_rows(): void
    {
        $this->fakeReport([
            'columns' => [
                ['fieldname' => 'account', 'label' => 'Account', 'fieldtype' => 'Link'],
                ['fieldname' => 'dec_2026', 'label' => '2026', 'fieldtype' => 'Currency'],
            ],
            'result' => [
                ['account' => '4000.000 - Penjualan', 'dec_2026' => 4730000000, 'indent' => 0, 'is_group' => 1],
                ['account' => '4310.000 - Pendapatan Service', 'dec_2026' => 4730000000, 'indent' => 2],
            ],
        ]);

        $this->get('/accounting/profit-and-loss')
            ->assertOk()
            ->assertSee('Profit &amp; Loss', false)
            ->assertSee('4310.000 - Pendapatan Service')
            ->assertSee('4,730,000,000');
    }

    public function test_it_renders_the_older_string_columns_and_list_rows(): void
    {
        $this->fakeReport([
            'columns' => ['Posting Date:Date:100', 'Account:Link/Account:240', 'Debit:Currency:120'],
            'result' => [
                ['2026-04-21', '5110.001 - Biaya BBM - KBM', 356000000],
            ],
        ]);

        $this->get('/accounting/general-ledger')
            ->assertOk()
            ->assertSee('Posting Date')
            ->assertSee('5110.001 - Biaya BBM - KBM')
            ->assertSee('356,000,000');
    }

    public function test_a_quoted_label_is_a_total_row_not_a_quoted_string(): void
    {
        $this->fakeReport([
            'columns' => [
                ['fieldname' => 'account', 'label' => 'Account', 'fieldtype' => 'Data'],
                ['fieldname' => 'total', 'label' => 'Total', 'fieldtype' => 'Currency'],
            ],
            'result' => [
                ['account' => "'Profit for the year'", 'total' => 715001000],
            ],
        ]);

        $this->get('/accounting/profit-and-loss')
            ->assertOk()
            ->assertSee('Profit for the year')
            ->assertDontSee("'Profit for the year'");
    }

    public function test_every_report_in_the_menu_runs(): void
    {
        $this->fakeReport(['columns' => [], 'result' => []]);

        foreach (['general-ledger', 'trial-balance', 'balance-sheet', 'profit-and-loss'] as $report) {
            $this->get("/accounting/{$report}")->assertOk();
        }
    }

    public function test_a_report_erp_hpy_refuses_is_shown_as_a_message_not_a_500(): void
    {
        Http::fake([
            '*frappe.desk.query_report.run*' => Http::response(['exception' => 'frappe.exceptions.ValidationError: Fiscal Year is mandatory'], 417),
            '*' => Http::response(['data' => []]),
        ]);

        $this->get('/accounting/trial-balance')
            ->assertOk()
            ->assertSee('Fiscal Year is mandatory');
    }

    public function test_the_period_and_filters_reach_erp_hpy(): void
    {
        $this->fakeReport(['columns' => [], 'result' => []]);

        $this->get('/accounting/general-ledger?from_date=2026-01-01&to_date=2026-06-30&account=5110.001+-+Biaya+BBM+-+KBM')
            ->assertOk();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'query_report.run')) {
                return false;
            }

            $filters = json_decode($request['filters'], true);

            return $request['report_name'] === 'General Ledger'
                && $filters['company'] === self::KBM
                && $filters['from_date'] === '2026-01-01'
                && $filters['to_date'] === '2026-06-30'
                && $filters['account'] === ['5110.001 - Biaya BBM - KBM'];
        });
    }

    public function test_an_unknown_report_is_not_a_page(): void
    {
        $this->fakeReport(['columns' => [], 'result' => []]);

        $this->get('/accounting/cash-flow')->assertNotFound();
    }

    public function test_profit_and_loss_gets_headline_figures_and_monthly_charts(): void
    {
        $this->fakeReport([
            'columns' => [
                ['fieldname' => 'account', 'label' => 'Account', 'fieldtype' => 'Link'],
                ['fieldname' => 'jan_2026', 'label' => 'Jan 2026', 'fieldtype' => 'Currency'],
                ['fieldname' => 'feb_2026', 'label' => 'Feb 2026', 'fieldtype' => 'Currency'],
                ['fieldname' => 'total', 'label' => 'Total', 'fieldtype' => 'Currency'],
            ],
            'result' => [
                ['account' => '4000.000 - Penjualan - KBM', 'account_name' => '4000.000 - Penjualan', 'indent' => 0, 'is_group' => 1, 'jan_2026' => 200000000, 'feb_2026' => 100000000, 'total' => 300000000],
                ['account' => "'Total Income (Credit)'", 'jan_2026' => 200000000, 'feb_2026' => 100000000, 'total' => 300000000],
                ['account' => '5000.000 - Beban - KBM', 'account_name' => '5000.000 - Beban', 'indent' => 0, 'is_group' => 1, 'jan_2026' => 50000000, 'feb_2026' => 150000000, 'total' => 200000000],
                ['account' => '5120.000 - Biaya Gaji - KBM', 'account_name' => '5120.000 - Biaya Gaji', 'indent' => 1, 'parent_account' => '5000.000 - Beban - KBM', 'jan_2026' => 50000000, 'feb_2026' => 150000000, 'total' => 200000000],
                ['account' => "'Total Expense (Debit)'", 'jan_2026' => 50000000, 'feb_2026' => 150000000, 'total' => 200000000],
                ['account' => "'Profit for the year'", 'jan_2026' => 150000000, 'feb_2026' => -50000000, 'total' => 100000000],
            ],
        ]);

        $this->get('/accounting/profit-and-loss')
            ->assertOk()
            ->assertSee('Rp 300M')            // income
            ->assertSee('Net profit')
            ->assertSee('33.3%')              // net margin
            ->assertSee('data-column-chart', false)
            ->assertSee('Income vs expenses by period')
            ->assertSee('Top expenses')
            ->assertSee('5120.000 Biaya Gaji');

        // Profit and loss is asked per period, not accumulated.
        Http::assertSent(fn ($r) => str_contains($r->url(), 'query_report.run') && ($r['filters'] ?? '') !== ''
            && json_decode($r['filters'], true)['accumulated_values'] === 0);
    }
}
