<?php

namespace Tests\Feature;

use App\Models\Principal;
use App\Services\Erpnext\ErpnextClient;
use App\Support\ErpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrincipalTest extends TestCase
{
    use RefreshDatabase;

    private const KBM = 'Keenindo Bintas Marine';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.erpnext.url', 'https://kbm.hpy.co.id');
        config()->set('services.erpnext.company', null);

        $this->workingIn(self::KBM);
    }

    private function workingIn(string $company, array $roles = ['HR Manager']): void
    {
        $this->withSession([
            ErpnextClient::SESSION_KEY => ['sid' => 'abc123', 'user' => 'budi@hpy.co.id', 'full_name' => 'Budi'],
            ErpnextClient::COMPANY_KEY => $company,
            ErpUser::ROLES_KEY => $roles,
        ]);
    }

    /** @param array<string, mixed> $stubs */
    private function fakeErp(array $stubs = []): void
    {
        Http::fake($stubs + ['*' => Http::response(['data' => []])]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'principal_name' => 'MOL Chemical Tankers Indonesia',
            'principal_type' => 'shipowner',
            'status' => 'active',
            'country' => 'Indonesia',
            'contact_persons' => [
                ['name' => 'Hiroshi Tanaka', 'position' => 'Crew Superintendent', 'is_primary' => '1'],
            ],
        ], $overrides);
    }

    public function test_it_creates_a_principal_with_a_running_code_and_pushes_it_to_erp_hpy(): void
    {
        $this->fakeErp([
            '*/api/resource/Principal' => Http::response(['data' => ['name' => 'PRN-0001']]),
        ]);

        $this->post('/principals', $this->payload())->assertRedirect();

        $principal = Principal::sole();

        $this->assertSame('PRN-0001', $principal->principal_code);
        $this->assertSame(self::KBM, $principal->company);
        $this->assertSame('budi@hpy.co.id', $principal->created_by);
        $this->assertSame('synced', $principal->erpnext_sync_status);
        $this->assertSame('PRN-0001', $principal->erpnext_name);
        $this->assertSame('Hiroshi Tanaka', $principal->primary_contact->name);
    }

    public function test_a_principal_needs_a_primary_contact_and_a_sane_contract_window(): void
    {
        $this->fakeErp();

        $this->post('/principals', $this->payload([
            'contact_persons' => [['name' => 'Hiroshi Tanaka', 'is_primary' => '0']],
        ]))->assertSessionHasErrors('contact_persons');

        $this->post('/principals', $this->payload([
            'contract_start_date' => '2026-06-01',
            'contract_end_date' => '2026-01-01',
        ]))->assertSessionHasErrors('contract_end_date');
    }

    public function test_a_failed_push_leaves_the_record_and_says_so(): void
    {
        $this->fakeErp([
            '*/api/resource/Principal' => Http::response(['exception' => 'frappe.exceptions.ValidationError: nope'], 417),
        ]);

        $this->post('/principals', $this->payload())->assertRedirect();

        $principal = Principal::sole();

        $this->assertSame('failed', $principal->erpnext_sync_status);
        $this->assertNotNull($principal->erpnext_sync_error);
    }

    public function test_a_principal_that_still_has_vessels_cannot_be_deleted(): void
    {
        $this->fakeErp([
            '*/api/resource/Vessel*' => Http::response(['data' => [
                ['name' => 'MT Bintang Samudera', 'vessel_name' => 'MT Bintang Samudera'],
            ]]),
        ]);

        $principal = Principal::create($this->payload() + ['erpnext_name' => 'PRN-0001']);

        $this->delete('/principals/' . $principal->uuid)->assertSessionHasErrors('principal');
        $this->assertNotSoftDeleted($principal);
    }

    public function test_a_crewing_officer_may_edit_but_not_delete(): void
    {
        $this->fakeErp();

        $principal = Principal::create($this->payload());

        $this->workingIn(self::KBM, ['Crewing Officer']);
        $this->get('/principals/' . $principal->uuid . '/edit')->assertOk();
        $this->delete('/principals/' . $principal->uuid)->assertForbidden();

        $this->workingIn(self::KBM, ['HR Manager']);
        $this->delete('/principals/' . $principal->uuid)->assertRedirect(route('principals.index'));
        $this->assertSoftDeleted($principal);
    }

    public function test_principals_stay_inside_their_company(): void
    {
        $this->fakeErp();

        Principal::create($this->payload());

        $this->workingIn('Donmar Jaladri Utama');
        Principal::create($this->payload(['principal_name' => 'Bahari Nusantara Charter']));

        $this->get('/principals')->assertOk()
            ->assertSee('Bahari Nusantara Charter')
            ->assertDontSee('MOL Chemical Tankers Indonesia');

        $this->workingIn(self::KBM);
        $this->get('/principals')->assertOk()
            ->assertSee('MOL Chemical Tankers Indonesia')
            ->assertDontSee('Bahari Nusantara Charter');
    }

    public function test_the_vessel_form_offers_principals_and_saves_the_link(): void
    {
        $this->fakeErp([
            '*/api/resource/Vessel' => Http::response(['data' => ['name' => 'TB Keenindo 02']]),
        ]);

        Principal::create($this->payload() + ['erpnext_name' => 'PRN-0001']);

        $this->get('/vessels/create')->assertOk()->assertSee('MOL Chemical Tankers Indonesia');

        $this->post('/vessels', [
            'vessel_name' => 'TB Keenindo 02',
            'vessel_type' => 'Tugboat',
            'principal' => 'PRN-0001',
        ])->assertRedirect();

        Http::assertSent(fn ($request) => $request->method() !== 'POST'
            || ! str_ends_with($request->url(), '/api/resource/Vessel')
            || $request['principal'] === 'PRN-0001');
    }

    public function test_the_sync_command_only_picks_up_what_is_pending(): void
    {
        $this->fakeErp([
            '*/api/resource/Principal' => Http::response(['data' => ['name' => 'PRN-0001']]),
        ]);

        Principal::create($this->payload()); // pending
        Principal::create($this->payload(['principal_name' => 'Already Synced']))
            ->forceFill(['erpnext_sync_status' => 'synced', 'erpnext_name' => 'PRN-0002'])->saveQuietly();

        $this->artisan('erpnext:sync Principal --pending')
            ->expectsOutputToContain('1 synced, 0 failed.')
            ->assertSuccessful();
    }
}
