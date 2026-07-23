<?php

namespace Tests\Feature;

use App\Models\CrewApplication;
use App\Models\CrewAssignment;
use App\Models\CrewCandidate;
use App\Services\Erpnext\ErpnextClient;
use App\Support\ErpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecruitmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.erpnext.url', 'https://kbm.hpy.co.id');

        $this->withSession([
            ErpnextClient::SESSION_KEY => ['sid' => 'abc123', 'user' => 'budi@hpy.co.id', 'full_name' => 'Budi'],
            ErpnextClient::COMPANY_KEY => 'Keenindo Bintas Marine',
            ErpUser::ROLES_KEY => ['HR Manager'],
        ]);
    }

    /** @param array<string, mixed> $stubs */
    private function fakeErp(array $stubs = []): void
    {
        Http::fake($stubs + ['*' => Http::response(['data' => []])]);
    }

    private function candidate(array $overrides = []): CrewCandidate
    {
        return CrewCandidate::create(array_merge([
            'full_name' => 'Hendra Setiawan',
            'phone' => '081234500003',
            'date_of_birth' => '1990-09-03',
            'source' => 'referral',
            'status' => 'applicant',
            'applied_rank' => 'Chief Engineer',
        ], $overrides));
    }

    public function test_an_application_walks_the_pipeline_one_stage_at_a_time(): void
    {
        $this->fakeErp();

        $application = CrewApplication::create([
            'crew_candidate_id' => $this->candidate()->id,
            'applied_rank' => 'Chief Engineer',
            'applied_date' => now()->subWeek()->toDateString(),
        ]);

        $this->assertSame('applied', $application->stage);
        $this->assertSame('APP-' . now()->format('Y') . '-00001', $application->application_code);

        foreach (['screening', 'interview', 'mcu', 'offer'] as $stage) {
            $this->post("/applications/{$application->uuid}/advance", ['mcu_result' => 'fit'])->assertRedirect();
            $this->assertSame($stage, $application->refresh()->stage);
        }

        // Dates are stamped as the application passes each desk.
        $this->assertNotNull($application->screening_date);
        $this->assertNotNull($application->interview_date);
        $this->assertSame('fit', $application->mcu_result);
    }

    public function test_hiring_creates_the_employee_and_opens_an_assignment(): void
    {
        $this->fakeErp([
            '*/api/resource/Employee' => Http::response(['data' => [
                'name' => 'HR-EMP-00009', 'employee_name' => 'Hendra Setiawan',
            ]]),
        ]);

        $candidate = $this->candidate();
        $application = CrewApplication::create([
            'crew_candidate_id' => $candidate->id,
            'applied_rank' => 'Chief Engineer',
            'vessel' => 'MV Nusantara',
            'stage' => 'offer',
            'applied_date' => now()->toDateString(),
            'offered_salary' => 52000000,
        ]);

        $this->post("/applications/{$application->uuid}/advance")->assertRedirect();

        $this->assertSame('hired', $application->refresh()->stage);
        $this->assertSame('HR-EMP-00009', $candidate->refresh()->linked_employee_id);
        $this->assertSame('employed', $candidate->status);

        $assignment = $application->assignment;
        $this->assertNotNull($assignment);
        $this->assertSame('planned', $assignment->status);
        $this->assertSame('MV Nusantara', $assignment->vessel);
        $this->assertSame('HR-EMP-00009', $assignment->employee_id);
    }

    public function test_closing_an_application_records_the_reason(): void
    {
        $this->fakeErp();

        $application = CrewApplication::create([
            'crew_candidate_id' => $this->candidate()->id,
            'applied_date' => now()->toDateString(),
        ]);

        $this->post("/applications/{$application->uuid}/close", [
            'stage' => 'rejected',
            'rejection_reason' => 'MCU unfit',
        ])->assertRedirect();

        $application->refresh();
        $this->assertSame('rejected', $application->stage);
        $this->assertSame('MCU unfit', $application->rejection_reason);
        $this->assertFalse($application->is_open);
    }

    public function test_signing_on_sets_the_contract_end_and_tells_erp_hpy(): void
    {
        $this->fakeErp();

        $assignment = CrewAssignment::create([
            'crew_name' => 'Hendra Setiawan',
            'employee_id' => 'HR-EMP-00009',
            'vessel' => 'MV Nusantara',
            'rank' => 'Chief Engineer',
            'status' => 'planned',
        ]);

        $this->post("/assignments/{$assignment->uuid}/sign-on", [
            'sign_on_date' => '2026-07-01',
            'sign_on_port' => 'Tanjung Priok',
            'contract_months' => 6,
        ])->assertRedirect();

        $assignment->refresh();
        $this->assertSame('onboard', $assignment->status);
        $this->assertSame('2027-01-01', $assignment->planned_sign_off_date->toDateString());

        // The Employee in ERP HPY is moved along with it.
        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains($request->url(), '/api/resource/Employee/HR-EMP-00009')
            && $request['custom_crew_status'] === 'Onboard'
            && $request['custom_vessel'] === 'MV Nusantara');
    }

    public function test_signing_off_frees_the_crew_again(): void
    {
        $this->fakeErp();

        $candidate = $this->candidate(['status' => 'employed']);
        $assignment = CrewAssignment::create([
            'crew_candidate_id' => $candidate->id,
            'crew_name' => $candidate->full_name,
            'employee_id' => 'HR-EMP-00009',
            'vessel' => 'MV Nusantara',
            'status' => 'onboard',
            'sign_on_date' => '2026-01-01',
        ]);

        $this->post("/assignments/{$assignment->uuid}/sign-off", [
            'sign_off_date' => '2026-07-01',
            'sign_off_reason' => 'Kontrak selesai',
        ])->assertRedirect();

        $assignment->refresh();
        $this->assertSame('signed_off', $assignment->status);
        $this->assertSame('on_leave', $candidate->refresh()->status);

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request['custom_crew_status'] === 'Standby'
            && $request['custom_vessel'] === '');
    }

    public function test_assigning_a_crew_to_a_vessel_reaches_erp_hpy_without_signing_on(): void
    {
        $this->fakeErp();

        CrewAssignment::create([
            'crew_name' => 'Agus Salim',
            'employee_id' => 'HR-EMP-00009',
            'vessel' => 'TB Keenindo 01',
            'rank' => 'Chief Officer',
            'status' => 'planned',
        ]);

        // The posting shows up in Crew Master straight away — the crew is still
        // Standby, but the ship is already recorded.
        Http::assertSent(function ($request) {
            if ($request->method() !== 'PUT') {
                return false;
            }

            return $request['custom_vessel'] === 'TB Keenindo 01'
                && $request['custom_crew_status'] === 'Standby'
                // A partial update must not blank the name ERP HPY insists on.
                && ! array_key_exists('first_name', $request->data());
        });
    }

    public function test_a_vessel_page_shows_crew_that_are_booked_but_not_aboard(): void
    {
        $this->fakeErp([
            '*/api/resource/Vessel/*' => Http::response(['data' => [
                'name' => 'TB Keenindo 01', 'vessel_name' => 'TB Keenindo 01',
                'company' => 'Keenindo Bintas Marine',
            ]]),
        ]);

        CrewAssignment::create(['crew_name' => 'Agus Salim', 'vessel' => 'TB Keenindo 01', 'status' => 'planned']);
        CrewAssignment::create(['crew_name' => 'Yusuf Ramadhan', 'vessel' => 'TB Keenindo 01', 'status' => 'onboard', 'sign_on_date' => now()->subMonth()]);

        $this->get('/vessels/TB Keenindo 01')
            ->assertOk()
            ->assertSee('Crew Onboard (1)')
            ->assertSee('Joining Soon (1)')
            ->assertSee('Agus Salim')
            ->assertSee('Yusuf Ramadhan');
    }

    public function test_the_sign_on_desk_lists_only_planned_and_sign_off_only_onboard(): void
    {
        $this->fakeErp();

        CrewAssignment::create(['crew_name' => 'Menunggu Naik', 'status' => 'planned']);
        CrewAssignment::create(['crew_name' => 'Sedang Berlayar', 'status' => 'onboard', 'sign_on_date' => now()->subMonths(2)]);

        $this->get('/sign-on')->assertOk()->assertSee('Menunggu Naik')->assertDontSee('Sedang Berlayar');
        $this->get('/sign-off')->assertOk()->assertSee('Sedang Berlayar')->assertDontSee('Menunggu Naik');
    }

    public function test_the_fleet_list_reads_vessels_from_erp_hpy(): void
    {
        $this->fakeErp([
            '*/api/resource/Vessel*' => Http::response(['data' => [
                ['name' => 'MV Nusantara', 'vessel_name' => 'MV Nusantara', 'vessel_type' => 'Bulk Carrier', 'imo_number' => '9876543'],
            ]]),
        ]);

        CrewAssignment::create(['crew_name' => 'Hendra', 'vessel' => 'MV Nusantara', 'status' => 'onboard', 'sign_on_date' => now()]);

        $this->get('/vessels')->assertOk()->assertSee('MV Nusantara')->assertSee('Bulk Carrier');
    }
}
