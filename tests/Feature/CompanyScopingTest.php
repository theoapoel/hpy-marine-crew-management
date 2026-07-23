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

/**
 * The company picked in the header decides what the whole app shows — local records
 * as much as ERP HPY documents.
 */
class CompanyScopingTest extends TestCase
{
    use RefreshDatabase;

    private const KBM = 'Keenindo Bintas Marine';

    private const DJU = 'Donmar Jaladri Utama';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.erpnext.url', 'https://kbm.hpy.co.id');
        config()->set('services.erpnext.company', null);
    }

    private function workingIn(string $company): void
    {
        $this->withSession([
            ErpnextClient::SESSION_KEY => ['sid' => 'abc123', 'user' => 'budi@hpy.co.id', 'full_name' => 'Budi'],
            ErpnextClient::COMPANY_KEY => $company,
            ErpUser::ROLES_KEY => ['HR Manager'],
        ]);
    }

    /** @param array<string, mixed> $stubs */
    private function fakeErp(array $stubs = []): void
    {
        Http::fake($stubs + ['*' => Http::response(['data' => []])]);
    }

    private function candidateFor(string $company, string $name): CrewCandidate
    {
        $this->workingIn($company);

        return CrewCandidate::create([
            'full_name' => $name,
            'phone' => '081234500001',
            'date_of_birth' => '1990-01-01',
            'source' => 'walk_in',
            'status' => 'applicant',
        ]);
    }

    public function test_a_record_is_stamped_with_the_company_of_the_session(): void
    {
        $this->fakeErp();

        $this->assertSame(self::KBM, $this->candidateFor(self::KBM, 'Agus Salim')->company);
        $this->assertSame(self::DJU, $this->candidateFor(self::DJU, 'Made Suardana')->company);
    }

    public function test_candidates_applications_and_assignments_stay_inside_their_company(): void
    {
        $this->fakeErp();

        $kbm = $this->candidateFor(self::KBM, 'Agus Salim');
        $dju = $this->candidateFor(self::DJU, 'Made Suardana');

        $this->workingIn(self::KBM);
        CrewApplication::create(['crew_candidate_id' => $kbm->id, 'applied_date' => now()]);
        CrewAssignment::create(['crew_name' => 'Agus Salim', 'status' => 'planned']);

        $this->workingIn(self::DJU);
        CrewApplication::create(['crew_candidate_id' => $dju->id, 'applied_date' => now()]);
        CrewAssignment::create(['crew_name' => 'Made Suardana', 'status' => 'onboard', 'sign_on_date' => now()->subMonth()]);

        // Working in Donmar: only Donmar's people.
        $this->get('/candidates')->assertOk()->assertSee('Made Suardana')->assertDontSee('Agus Salim');
        $this->get('/applications')->assertOk()->assertSee('Made Suardana')->assertDontSee('Agus Salim');
        $this->get('/assignments')->assertOk()->assertSee('Made Suardana')->assertDontSee('Agus Salim');
        $this->get('/sign-off')->assertOk()->assertSee('Made Suardana')->assertDontSee('Agus Salim');

        // Switch back and it flips.
        $this->workingIn(self::KBM);
        $this->get('/candidates')->assertOk()->assertSee('Agus Salim')->assertDontSee('Made Suardana');
        $this->get('/assignments')->assertOk()->assertSee('Agus Salim')->assertDontSee('Made Suardana');
        $this->get('/sign-on')->assertOk()->assertSee('Agus Salim')->assertDontSee('Made Suardana');
    }

    public function test_the_candidate_export_and_duplicate_check_are_scoped_too(): void
    {
        $this->fakeErp();

        $this->candidateFor(self::KBM, 'Agus Salim')->update(['nik' => '3374011204850001']);
        $this->candidateFor(self::DJU, 'Made Suardana');

        // A NIK belonging to another company must not surface here.
        $this->workingIn(self::DJU);
        $this->getJson('/candidates/duplicates?nik=3374011204850001')->assertOk()->assertJsonCount(0, 'matches');

        $this->workingIn(self::KBM);
        $this->getJson('/candidates/duplicates?nik=3374011204850001')->assertOk()->assertJsonCount(1, 'matches');
    }

    public function test_vessels_are_filtered_by_company_and_foreign_ones_are_hidden(): void
    {
        $this->fakeErp([
            '*/api/resource/Vessel/*' => Http::response(['data' => [
                'name' => 'MT Bintang Samudera', 'vessel_name' => 'MT Bintang Samudera', 'company' => self::KBM,
            ]]),
        ]);

        $this->workingIn(self::KBM);
        $this->get('/vessels/MT Bintang Samudera')->assertOk();

        // Same vessel, wrong company for this session.
        $this->workingIn(self::DJU);
        $this->get('/vessels/MT Bintang Samudera')->assertNotFound();

        Http::assertSent(fn ($request) => ! str_contains($request->url(), '/api/resource/Vessel?')
            || str_contains(urldecode($request->url()), '["company","="'));
    }
}
