<?php

namespace Tests\Feature;

use App\Services\Erpnext\ErpnextClient;
use App\Support\ErpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VesselProfitabilityTest extends TestCase
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

    /**
     * One charter: 500 revenue against 300 of cost, of which 100 arrives as a journal
     * entry — the crew cost channel.
     */
    private function fakeCharter(array $before = []): void
    {
        // Order matters: the first pattern that matches wins, so a caller wanting a
        // single document out of a doctype has to get in front of the list stub.
        Http::fake($before + [
            '*/api/resource/Project*' => Http::response(['data' => [[
                'name' => 'PROJ-0001',
                'project_name' => 'Time Charter TB Keenindo 01',
                'vessel' => 'VSL-0001',
                'company' => self::KBM,
                'customer' => 'Samudera Chartering Nusantara',
                'status' => 'Open',
                'expected_start_date' => '2026-04-01',
                'expected_end_date' => '2026-04-30',
            ]]]),
            '*/api/resource/Vessel*' => Http::response(['data' => [
                ['name' => 'VSL-0001', 'vessel_name' => 'TB Keenindo 01'],
            ]]),
            '*/api/resource/Sales%20Invoice%20Item*' => Http::response(['data' => [
                ['parent' => 'SINV-0001', 'project' => 'PROJ-0001', 'item_code' => 'Charter Hire',
                    'item_name' => 'Charter Hire', 'item_group' => 'Charter Hire', 'base_net_amount' => 500],
            ]]),
            '*/api/resource/Sales%20Invoice*' => Http::response(['data' => [
                ['name' => 'SINV-0001', 'posting_date' => '2026-04-30', 'customer_name' => 'Samudera Chartering Nusantara'],
            ]]),
            '*/api/resource/Purchase%20Invoice%20Item*' => Http::response(['data' => [
                ['parent' => 'PINV-0001', 'project' => 'PROJ-0001', 'item_code' => 'Marine Fuel Oil',
                    'item_name' => 'Marine Fuel Oil', 'item_group' => 'Bunker', 'base_net_amount' => 200],
            ]]),
            '*/api/resource/Purchase%20Invoice*' => Http::response(['data' => [
                ['name' => 'PINV-0001', 'posting_date' => '2026-04-20', 'supplier_name' => 'Bahari Marine Supply'],
            ]]),
            '*/api/resource/Journal%20Entry%20Account*' => Http::response(['data' => [
                ['parent' => 'ACC-JV-0001', 'project' => 'PROJ-0001', 'account' => 'Crew Wages - KBM',
                    'debit' => 100, 'credit' => 0, 'user_remark' => 'Crew wages allocation'],
            ]]),
            '*/api/resource/Journal%20Entry*' => Http::response(['data' => [
                ['name' => 'ACC-JV-0001', 'posting_date' => '2026-04-30', 'title' => 'Crew wages'],
            ]]),
            '*' => Http::response(['data' => []]),
        ]);
    }

    public function test_the_fleet_page_shows_revenue_cost_and_margin_per_vessel(): void
    {
        $this->fakeCharter();

        $this->get('/profitability')
            ->assertOk()
            ->assertSee('TB Keenindo 01')
            ->assertSee('500')      // revenue
            ->assertSee('300')      // 200 purchase + 100 journal
            ->assertSee('200');     // margin
    }

    public function test_it_splits_cost_into_components_from_item_group_and_account(): void
    {
        $this->fakeCharter();

        $this->get('/profitability')
            ->assertOk()
            ->assertSee('Charter Hire')
            ->assertSee('Bunker')
            ->assertSee('Crew Cost');   // from the "Crew Wages - KBM" account
    }

    public function test_a_vessel_page_lists_its_projects(): void
    {
        $this->fakeCharter();

        $this->get('/profitability/vessel/VSL-0001')
            ->assertOk()
            ->assertSee('Time Charter TB Keenindo 01')
            ->assertSee('PROJ-0001');
    }

    public function test_a_project_page_drills_down_to_the_documents(): void
    {
        $this->fakeCharter([
            '*/api/resource/Project/PROJ-0001*' => Http::response(['data' => [
                'name' => 'PROJ-0001',
                'project_name' => 'Time Charter TB Keenindo 01',
                'company' => self::KBM,
                'vessel' => 'VSL-0001',
            ]]),
        ]);

        $this->get('/profitability/project/PROJ-0001')
            ->assertOk()
            ->assertSee('SINV-0001')
            ->assertSee('PINV-0001')
            ->assertSee('ACC-JV-0001');
    }

    public function test_it_refuses_a_project_belonging_to_another_company(): void
    {
        $this->fakeCharter([
            '*/api/resource/Project/PROJ-0001*' => Http::response(['data' => [
                'name' => 'PROJ-0001',
                'company' => 'Donmar Jaladri Utama',
            ]]),
        ]);

        $this->get('/profitability/project/PROJ-0001')->assertNotFound();
    }

    public function test_a_dead_journal_entry_query_still_leaves_the_page_standing(): void
    {
        $this->fakeCharter();

        Http::fake(['*Journal%20Entry%20Account*' => Http::response(['exc' => 'no permission'], 403)]);

        $this->get('/profitability')
            ->assertOk()
            ->assertSee('500');
    }
}
