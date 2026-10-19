<?php

namespace Tests\Feature;

use App\Models\CrewAssignment;
use App\Services\Erpnext\ErpnextClient;
use App\Support\ErpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The Performance Appraisal tab on Crew Master: the sea service record and its grades. */
class CrewSeaServiceTest extends TestCase
{
    use RefreshDatabase;

    private const KBM = 'Keenindo Bintas Marine';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.erpnext.enabled', true);
        config()->set('services.erpnext.url', 'https://kbm.hpy.co.id');
        config()->set('services.erpnext.api_key', null);
        config()->set('services.erpnext.api_secret', null);
        Cache::flush();

        $this->withSession([
            ErpnextClient::SESSION_KEY => ['sid' => 'abc123', 'user' => 'budi@hpy.co.id', 'full_name' => 'Budi'],
            ErpnextClient::COMPANY_KEY => self::KBM,
            ErpUser::ROLES_KEY => ['HR Manager'],
        ]);

        Http::fake(function (Request $request) {
            $url = urldecode($request->url());

            if (str_contains($url, '/api/resource/Employee/HR-EMP-0042')) {
                return Http::response(['data' => ['name' => 'HR-EMP-0042', 'employee_name' => 'Irfandi Saputra', 'custom_rank' => 'Second Engineer', 'company' => self::KBM]]);
            }
            if (preg_match('#/api/resource/Vessel\?#', $url)) {
                return Http::response(['data' => [
                    ['name' => 'MV Nusantara', 'vessel_name' => 'MV Nusantara', 'vessel_type' => 'Bulk Carrier', 'gross_tonnage' => 25063, 'principal' => 'PT Pelayaran Jaya'],
                ]]);
            }

            return Http::response(['data' => []]);
        });
    }

    private function berth(array $overrides = []): CrewAssignment
    {
        return CrewAssignment::create(array_replace([
            'crew_name' => 'Irfandi Saputra',
            'employee_id' => 'HR-EMP-0042',
            'vessel' => 'MV Nusantara',
            'rank' => 'Second Engineer',
            'company' => self::KBM,
            'status' => 'signed_off',
            'sign_on_date' => '2025-01-10',
            'sign_off_date' => '2025-09-02',
        ], $overrides));
    }

    public function test_the_sea_service_record_lists_each_berth_with_the_ships_particulars(): void
    {
        $this->berth();
        $this->berth(['vessel' => 'MT Bernice', 'rank' => 'Third Engineer', 'status' => 'onboard', 'sign_on_date' => '2026-03-01', 'sign_off_date' => null]);
        // Not yet service, so not a row.
        $this->berth(['status' => 'planned', 'sign_on_date' => null]);

        $response = $this->get('/crew/HR-EMP-0042')->assertOk()
            ->assertSee('Performance Appraisal')
            ->assertSee('Sea Service Record')
            ->assertSee('Bulk Carrier')
            ->assertSee('25,063')
            ->assertSee('PT Pelayaran Jaya')
            ->assertSee('10 Jan 2025')
            ->assertSee('02 Sep 2025')
            ->assertSee('2 contracts');

        // Latest berth first; the vessel ERP HPY does not know still lists by its name.
        $this->assertLessThan(strpos($response->getContent(), 'MV Nusantara'), strpos($response->getContent(), 'MT Bernice'));
    }

    public function test_a_berth_can_be_graded_from_the_tab(): void
    {
        $berth = $this->berth();

        $this->post("/crew/HR-EMP-0042/appraisal/{$berth->uuid}", ['appraisal' => 'Very Good', 'appraisal_notes' => 'Reliable on watch.'])
            ->assertRedirect(route('crew.show', 'HR-EMP-0042').'#performance-appraisal');

        $this->assertSame('Very Good', $berth->fresh()->appraisal);
        $this->assertSame('Reliable on watch.', $berth->fresh()->appraisal_notes);
    }

    public function test_only_the_known_grades_are_accepted(): void
    {
        $berth = $this->berth();

        $this->post("/crew/HR-EMP-0042/appraisal/{$berth->uuid}", ['appraisal' => 'Superb'])
            ->assertSessionHasErrors('appraisal');
    }

    public function test_a_berth_of_another_crew_member_cannot_be_graded_through_this_one(): void
    {
        $berth = $this->berth(['employee_id' => 'HR-EMP-0099']);

        $this->post("/crew/HR-EMP-0042/appraisal/{$berth->uuid}", ['appraisal' => 'Poor'])->assertNotFound();

        $this->assertNull($berth->fresh()->appraisal);
    }
}
