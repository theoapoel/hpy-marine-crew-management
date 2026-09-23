<?php

namespace Tests\Feature;

use App\Models\CrewApplication;
use App\Models\CrewAssignment;
use App\Models\CrewCandidate;
use App\Services\Erpnext\ErpnextClient;
use App\Support\ErpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CrewAssignmentFormTest extends TestCase
{
    use RefreshDatabase;

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
            ErpnextClient::COMPANY_KEY => 'Keenindo Bintas Marine',
            ErpUser::ROLES_KEY => ['HR Manager'],
        ]);
    }

    /** What ERP HPY's Employee list currently returns; tests change it mid-way. */
    private array $employees = [];

    private bool $faked = false;

    /** ERP HPY knows these Employees (list calls); every other call succeeds quietly. */
    private function fakeEmployees(array $employees = []): void
    {
        $this->employees = $employees;

        if ($this->faked) {
            return; // a second Http::fake() would not replace the first callback
        }
        $this->faked = true;

        Http::fake(function (Request $request) {
            $isEmployeeList = $request->method() === 'GET' && preg_match('#/api/resource/Employee(\?|$)#', $request->url());

            return Http::response(['data' => $isEmployeeList ? $this->employees : []]);
        });
    }

    /** @return array<string, mixed> */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'crew_name' => 'Irfandi Saputra',
            'vessel' => 'Tonda',
            'rank' => 'Second Engineer',
            'status' => 'planned',
            'wage' => 9000,
            'wage_currency' => 'USD',
        ], $overrides);
    }

    public function test_contract_months_give_the_planned_sign_off_without_spilling_a_month(): void
    {
        $this->fakeEmployees();

        $this->post('/assignments', $this->form(['planned_sign_on_date' => '2026-08-31', 'contract_months' => 6]));

        $assignment = CrewAssignment::sole();
        $this->assertSame('2027-02-28', $assignment->planned_sign_off_date->toDateString());
        $this->assertSame('USD', $assignment->wage_currency);
    }

    public function test_a_typed_planned_sign_off_is_kept(): void
    {
        $this->fakeEmployees();

        $this->post('/assignments', $this->form([
            'planned_sign_on_date' => '2026-09-22', 'contract_months' => 6, 'planned_sign_off_date' => '2027-04-01',
        ]));

        $this->assertSame('2027-04-01', CrewAssignment::sole()->planned_sign_off_date->toDateString());
    }

    public function test_an_unlinked_assignment_finds_its_employee_candidate_and_application_by_name(): void
    {
        $this->fakeEmployees([
            ['name' => 'HR-EMP-0042', 'employee_name' => 'Irfandi Saputra', 'custom_rank' => 'Second Engineer'],
            ['name' => 'HR-EMP-0043', 'employee_name' => 'Budi Santoso'],
        ]);

        $candidate = CrewCandidate::create([
            'full_name' => 'Irfandi Saputra', 'phone' => '0811', 'date_of_birth' => '1991-01-01',
            'source' => 'referral', 'status' => 'in_process',
        ]);
        CrewApplication::create(['crew_candidate_id' => $candidate->id, 'applied_date' => '2026-06-01', 'stage' => 'interview']);
        $hired = CrewApplication::create(['crew_candidate_id' => $candidate->id, 'applied_date' => '2026-05-01', 'stage' => 'hired']);

        $this->post('/assignments', $this->form());

        $assignment = CrewAssignment::sole();
        $this->assertSame('HR-EMP-0042', $assignment->employee_id);
        $this->assertSame($candidate->id, $assignment->crew_candidate_id);
        $this->assertSame($hired->id, $assignment->crew_application_id, 'the hired application wins');

        // …and with the Employee known, the posting reaches Crew Master.
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_contains($r->url(), '/api/resource/Employee/HR-EMP-0042'));
    }

    public function test_a_name_shared_by_two_employees_is_not_guessed(): void
    {
        $this->fakeEmployees([
            ['name' => 'HR-EMP-0042', 'employee_name' => 'Irfandi Saputra'],
            ['name' => 'HR-EMP-0099', 'employee_name' => 'Irfandi Saputra'],
        ]);

        $this->post('/assignments', $this->form());

        $this->assertNull(CrewAssignment::sole()->employee_id);
    }

    public function test_setting_onboard_in_the_form_is_a_sign_on(): void
    {
        $this->fakeEmployees();
        $this->post('/assignments', $this->form(['planned_sign_on_date' => '2026-09-22']));
        $assignment = CrewAssignment::sole();

        $this->put("/assignments/{$assignment->uuid}", $this->form([
            'status' => 'onboard', 'planned_sign_on_date' => '2026-09-22', 'contract_months' => 6,
        ]));

        $assignment->refresh();
        $this->assertSame('2026-09-22', $assignment->sign_on_date->toDateString());
        $this->assertSame('2027-03-22', $assignment->planned_sign_off_date->toDateString());
    }

    public function test_the_sign_on_desk_also_lists_who_boarded_lately(): void
    {
        $this->fakeEmployees();
        $this->post('/assignments', $this->form(['status' => 'onboard', 'sign_on_date' => now()->subDays(3)->toDateString()]));
        $this->post('/assignments', $this->form(['crew_name' => 'Waiting Crew']));

        $this->get('/sign-on')
            ->assertOk()
            ->assertSee('Waiting Crew')
            ->assertSee('Recently Signed On')
            ->assertSee('Irfandi Saputra');
    }

    public function test_linking_later_repairs_an_assignment_and_pushes_it_to_erp(): void
    {
        $this->fakeEmployees();
        $this->post('/assignments', $this->form(['status' => 'onboard', 'sign_on_date' => '2026-09-22']));
        $assignment = CrewAssignment::sole();
        $this->assertNull($assignment->employee_id);

        // The Employee exists in ERP HPY now.
        $this->fakeEmployees([['name' => 'HR-EMP-0042', 'employee_name' => 'Irfandi Saputra']]);
        Cache::flush();

        $this->post("/assignments/{$assignment->uuid}/link")->assertSessionHas('success');

        $this->assertSame('HR-EMP-0042', $assignment->refresh()->employee_id);
        Http::assertSent(fn ($r) => $r->method() === 'PUT'
            && str_contains($r->url(), '/api/resource/Employee/HR-EMP-0042')
            && $r['custom_crew_status'] === 'Onboard'
            && $r['custom_vessel'] === 'Tonda');
    }
}
