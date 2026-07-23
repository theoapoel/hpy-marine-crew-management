<?php

namespace Tests\Feature;

use App\Models\CrewCandidate;
use App\Services\Erpnext\ErpnextClient;
use App\Support\ErpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CrewCandidateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.erpnext.url', 'https://kbm.hpy.co.id');

        $this->signIn();
    }

    /**
     * Stubs ERP HPY. Called per test rather than in setUp: Http::fake merges stubs, so
     * a catch-all registered first would swallow the specific ones.
     *
     * @param  array<string, mixed>  $stubs
     */
    private function fakeErp(array $stubs = []): void
    {
        Http::fake($stubs + ['*' => Http::response(['data' => []])]);
    }

    /** @param array<int, string> $roles */
    private function signIn(array $roles = ['HR Manager'], string $user = 'budi@hpy.co.id'): void
    {
        $this->withSession([
            ErpnextClient::SESSION_KEY => ['sid' => 'abc123', 'user' => $user, 'full_name' => 'Budi'],
            ErpnextClient::COMPANY_KEY => 'Keenindo Bintas Marine',
            ErpUser::ROLES_KEY => $roles,
        ]);
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Slamet Riyadi',
            'gender' => 'male',
            'date_of_birth' => '1992-03-15',
            'phone' => '081298765432',
            'source' => 'walk_in',
            'status' => 'applicant',
            'coc_type' => 'ANT-II',
        ], $overrides);
    }

    public function test_it_creates_a_candidate_with_an_auto_generated_code(): void
    {
        $this->fakeErp();
        $this->post('/candidates', $this->validPayload())->assertRedirect();

        $candidate = CrewCandidate::sole();

        $this->assertSame('CND-' . now()->format('Y') . '-00001', $candidate->candidate_code);
        $this->assertNotNull($candidate->uuid);
        $this->assertSame('budi@hpy.co.id', $candidate->created_by);
        $this->assertSame('Slamet', $candidate->first_name);
        $this->assertSame('Riyadi', $candidate->last_name);
    }

    public function test_it_rejects_a_bad_phone_a_minor_and_a_duplicate_nik(): void
    {
        $this->fakeErp();
        CrewCandidate::create($this->validPayload(['nik' => '3374011204850001', 'full_name' => 'Bagus']));

        $this->post('/candidates', $this->validPayload([
            'phone' => '123',
            'date_of_birth' => now()->subYears(15)->toDateString(),
            'nik' => '3374011204850001',
        ]))->assertSessionHasErrors(['phone', 'date_of_birth', 'nik']);
    }

    public function test_expired_documents_are_accepted_not_rejected(): void
    {
        $this->fakeErp();
        $this->post('/candidates', $this->validPayload([
            'coc_expiry' => '2020-01-01',
            'mcu_expiry' => '2019-05-05',
            'passport_expiry' => '2018-01-01',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, CrewCandidate::count());
    }

    public function test_conditional_source_fields_are_cleared_when_they_do_not_apply(): void
    {
        $this->fakeErp();
        $this->post('/candidates', $this->validPayload([
            'source' => 'agency',
            'source_agency_id' => 'PT Bahari Manning',
            'source_cost' => 5000000,
            'source_school' => 'Should be dropped',
        ]))->assertSessionHasNoErrors();

        $candidate = CrewCandidate::sole();

        $this->assertSame('PT Bahari Manning', $candidate->source_agency_id);
        $this->assertNull($candidate->source_school);
        $this->assertSame('5000000.00', $candidate->source_cost);
    }

    public function test_referral_requires_who_referred_the_candidate(): void
    {
        $this->fakeErp();
        $this->post('/candidates', $this->validPayload(['source' => 'referral']))
            ->assertSessionHasErrors('referred_by_employee_id');
    }

    public function test_the_list_searches_and_filters(): void
    {
        $this->fakeErp();
        CrewCandidate::create($this->validPayload(['full_name' => 'Bagus Prasetyo', 'seaman_book_no' => 'SB-1', 'status' => 'applicant']));
        CrewCandidate::create($this->validPayload(['full_name' => 'Rudi Hartono', 'seaman_book_no' => 'SB-2', 'status' => 'blacklist']));

        $this->get('/candidates?search=SB-2')->assertOk()->assertSee('Rudi Hartono')->assertDontSee('Bagus Prasetyo');
        $this->get('/candidates?status=blacklist')->assertOk()->assertSee('Rudi Hartono')->assertDontSee('Bagus Prasetyo');
    }

    public function test_duplicate_lookup_points_at_the_existing_candidate(): void
    {
        $this->fakeErp();
        $existing = CrewCandidate::create($this->validPayload(['full_name' => 'Bagus', 'nik' => '3374011204850001']));

        $this->getJson('/candidates/duplicates?nik=3374011204850001')
            ->assertOk()
            ->assertJsonPath('matches.0.code', $existing->candidate_code)
            ->assertJsonPath('matches.0.field', 'NIK');
    }

    public function test_a_crewing_officer_may_edit_but_not_delete(): void
    {
        $this->fakeErp();
        $candidate = CrewCandidate::create($this->validPayload());

        $this->signIn(['Crewing Officer']);

        $this->get('/candidates/' . $candidate->uuid . '/edit')->assertOk();
        $this->delete('/candidates/' . $candidate->uuid)->assertForbidden();

        $this->signIn(['HR Manager']);
        $this->delete('/candidates/' . $candidate->uuid)->assertRedirect();
        $this->assertSoftDeleted($candidate);
    }

    public function test_promoting_creates_the_employee_in_erp_hpy_and_links_it(): void
    {
        $this->fakeErp([
            '*/api/resource/Employee' => Http::response(['data' => [
                'name' => 'HR-EMP-00009', 'employee_name' => 'Slamet Riyadi',
            ]]),
        ]);

        $candidate = CrewCandidate::create($this->validPayload(['applied_rank' => 'Second Officer']));

        $this->post('/candidates/' . $candidate->uuid . '/promote')->assertRedirect(route('crew.show', 'HR-EMP-00009'));

        $candidate->refresh();
        $this->assertSame('HR-EMP-00009', $candidate->linked_employee_id);
        $this->assertSame('employed', $candidate->status);
    }

    public function test_scopes_and_accessors(): void
    {
        $this->fakeErp();
        $available = CrewCandidate::create($this->validPayload(['availability_date' => now()->subDay()]));
        CrewCandidate::create($this->validPayload(['full_name' => 'Rudi', 'status' => 'blacklist']));
        CrewCandidate::create($this->validPayload(['full_name' => 'Yusuf', 'mcu_expiry' => now()->addDays(10)]));

        $this->assertSame(2, CrewCandidate::available()->count());
        $this->assertSame(1, CrewCandidate::expiringMcu(30)->count());
        $this->assertTrue($available->is_available);
        $this->assertSame((int) now()->diffInYears('1992-03-15', true), $available->age);
    }
}
