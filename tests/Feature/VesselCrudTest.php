<?php

namespace Tests\Feature;

use App\Services\Erpnext\ErpnextClient;
use App\Support\ErpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VesselCrudTest extends TestCase
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
            'vessel_name' => 'TB Keenindo 02',
            'vessel_type' => 'Tugboat',
            'imo_number' => '9711223',
            'gross_tonnage' => 245,
        ], $overrides);
    }

    public function test_it_creates_a_vessel_under_the_session_company(): void
    {
        $this->fakeErp([
            '*/api/resource/Vessel' => Http::response(['data' => ['name' => 'TB Keenindo 02']]),
        ]);

        $this->post('/vessels', $this->payload())->assertRedirect(route('vessels.show', 'TB Keenindo 02'));

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/resource/Vessel')
            && $request['vessel_name'] === 'TB Keenindo 02'
            && $request['vessel_type'] === 'Tugboat'
            // The company is never taken from the form.
            && $request['company'] === self::KBM);
    }

    public function test_the_company_cannot_be_forced_from_the_form(): void
    {
        $this->fakeErp([
            '*/api/resource/Vessel' => Http::response(['data' => ['name' => 'TB Keenindo 02']]),
        ]);

        $this->post('/vessels', $this->payload(['company' => 'Donmar Jaladri Utama']))->assertRedirect();

        Http::assertSent(fn ($request) => $request->method() !== 'POST' || $request['company'] === self::KBM);
    }

    public function test_a_certificate_scan_is_uploaded_and_stored_on_the_row(): void
    {
        $this->fakeErp([
            '*/api/resource/Vessel' => Http::response(['data' => ['name' => 'TB Keenindo 02']]),
            '*/api/method/upload_file' => Http::response(['message' => ['file_url' => '/private/files/class.pdf']]),
        ]);

        $this->post('/vessels', $this->payload([
            'certificates' => [[
                'certificate_type' => 'Class Certificate',
                'certificate_number' => 'BKI-CL-1',
                'expiry_date' => now()->addYears(3)->toDateString(),
                'file' => UploadedFile::fake()->create('class.pdf', 12),
            ]],
        ]))->assertRedirect();

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains(urldecode($request->url()), '/api/resource/Vessel/TB Keenindo 02')
            && $request['certificates_table'][0]['attachment'] === '/private/files/class.pdf'
            // No status was given, so it is read off the expiry date.
            && $request['certificates_table'][0]['status'] === 'Valid');
    }

    public function test_an_expiring_certificate_is_flagged_without_being_told(): void
    {
        $this->fakeErp([
            '*/api/resource/Vessel' => Http::response(['data' => ['name' => 'TB Keenindo 02']]),
        ]);

        $this->post('/vessels', $this->payload([
            'certificates' => [
                ['certificate_type' => 'Load Line', 'expiry_date' => now()->addDays(20)->toDateString()],
                ['certificate_type' => 'Safety Radio', 'expiry_date' => now()->subDay()->toDateString()],
                ['certificate_type' => '', 'certificate_number' => 'baris kosong'],
            ],
        ]))->assertRedirect();

        Http::assertSent(function ($request) {
            if ($request->method() !== 'PUT') {
                return false;
            }
            $rows = $request['certificates_table'];

            return count($rows) === 2 // the blank row is dropped
                && $rows[0]['status'] === 'Expiring Soon'
                && $rows[1]['status'] === 'Expired';
        });
    }

    public function test_editing_or_deleting_another_companys_vessel_is_refused(): void
    {
        $this->fakeErp([
            '*/api/resource/Vessel/*' => Http::response(['data' => [
                'name' => 'BG Donmar Jaya 12', 'vessel_name' => 'BG Donmar Jaya 12', 'company' => 'Donmar Jaladri Utama',
            ]]),
        ]);

        $this->get('/vessels/BG Donmar Jaya 12/edit')->assertNotFound();
        $this->put('/vessels/BG Donmar Jaya 12', $this->payload())->assertNotFound();
        $this->delete('/vessels/BG Donmar Jaya 12')->assertNotFound();
    }

    public function test_a_crewing_officer_may_edit_but_not_delete(): void
    {
        $this->fakeErp([
            '*/api/resource/Vessel/*' => Http::response(['data' => [
                'name' => 'TB Keenindo 01', 'vessel_name' => 'TB Keenindo 01', 'company' => self::KBM,
            ]]),
        ]);

        $this->workingIn(self::KBM, ['Crewing Officer']);
        $this->get('/vessels/TB Keenindo 01/edit')->assertOk();
        $this->delete('/vessels/TB Keenindo 01')->assertForbidden();

        $this->workingIn(self::KBM, ['HR Manager']);
        $this->delete('/vessels/TB Keenindo 01')->assertRedirect(route('vessels.index'));
    }

    public function test_erp_hpy_validation_comes_back_on_the_form(): void
    {
        $this->fakeErp([
            '*/api/resource/Vessel' => Http::response([
                'exception' => 'frappe.exceptions.MandatoryError: Vessel Type is required',
            ], 417),
        ]);

        $this->post('/vessels', $this->payload())->assertSessionHasErrors('erpnext');
    }

    public function test_a_new_vessel_type_is_added_to_the_erp_master_from_the_form(): void
    {
        $this->fakeErp([
            '*/api/resource/DocType/Vessel' => Http::response(['data' => ['fields' => [
                ['fieldname' => 'vessel_type', 'fieldtype' => 'Link', 'options' => 'Vessel Type'],
            ]]]),
            '*/api/resource/Vessel%20Type' => Http::response(['data' => ['name' => 'Dredger']]),
        ]);

        $this->get('/vessels/create')->assertOk()
            ->assertSee('data-type-new="vessel"', false)
            ->assertSee('name="gross_tonnage"', false);

        $this->postJson('/vessels/types', ['name' => 'Dredger'])->assertCreated()->assertJson(['name' => 'Dredger']);

        Http::assertSent(fn ($r) => $r->method() === 'POST'
            && str_ends_with(urldecode($r->url()), '/api/resource/Vessel Type')
            && $r['type_name'] === 'Dredger');
    }

    public function test_a_new_vessel_certificate_type_is_added_to_the_erp_master_from_the_form(): void
    {
        $this->fakeErp([
            '*/api/resource/DocType/Vessel%20Certificate' => Http::response(['data' => ['fields' => [
                ['fieldname' => 'certificate_type', 'fieldtype' => 'Link', 'options' => 'Vessel Certificate Type'],
            ]]]),
            '*/api/resource/Vessel%20Certificate%20Type' => Http::response(['data' => ['name' => 'Polar Ship Certificate']]),
        ]);

        $this->get('/vessels/create')->assertOk()->assertSee('data-type-new="vcert"', false);

        $this->postJson('/vessels/certificate-types', ['name' => 'Polar Ship Certificate'])
            ->assertCreated()->assertJson(['name' => 'Polar Ship Certificate']);

        Http::assertSent(fn ($r) => $r->method() === 'POST'
            && str_ends_with(urldecode($r->url()), '/api/resource/Vessel Certificate Type')
            && $r['certificate_name'] === 'Polar Ship Certificate');
    }
}
