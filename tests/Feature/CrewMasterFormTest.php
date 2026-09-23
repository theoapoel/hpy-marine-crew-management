<?php

namespace Tests\Feature;

use App\Repositories\ErpnextCrewRepository;
use App\Services\Erpnext\ErpnextClient;
use App\Support\ErpUser;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CrewMasterFormTest extends TestCase
{
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
    }

    /** ERP HPY with Employee Certificate.certificate_type as a Link (sync run) or a Select (not yet). */
    private function fakeErp(bool $typesAreMaster = true): void
    {
        Http::fake(function (Request $request) use ($typesAreMaster) {
            $url = urldecode($request->url());

            if (str_contains($url, '/api/resource/DocType/Employee Certificate')) {
                return Http::response(['data' => ['fields' => [
                    $typesAreMaster
                        ? ['fieldname' => 'certificate_type', 'fieldtype' => 'Link', 'options' => 'Crew Certificate Type']
                        : ['fieldname' => 'certificate_type', 'fieldtype' => 'Select', 'options' => "Passport\nSeaman Book"],
                ]]]);
            }

            // The master exists once erp:sync-crew-fields has run.
            if ($request->method() === 'GET' && preg_match('#/api/resource/DocType\?#', $url) && str_contains($url, 'Crew Certificate Type')) {
                return Http::response(['data' => $typesAreMaster ? [['name' => 'Crew Certificate Type']] : []]);
            }
            if ($request->method() === 'GET' && preg_match('#/api/resource/Crew Certificate Type\?#', $url) && ! str_contains($url, '"name","="')) {
                return Http::response(['data' => [
                    ['name' => 'Passport', 'certificate_name' => 'Passport', 'category' => 'Identity', 'validity_months' => 60, 'is_active' => 1],
                    ['name' => 'Tanker Familiarization', 'certificate_name' => 'Tanker Familiarization', 'category' => 'Training', 'validity_months' => 0, 'is_active' => 1],
                    ['name' => 'Old Radio Licence', 'certificate_name' => 'Old Radio Licence', 'category' => 'Other', 'validity_months' => 0, 'is_active' => 0],
                ]]);
            }

            if ($request->method() === 'POST' && str_ends_with($url, '/api/resource/Employee')) {
                return Http::response(['data' => ['name' => 'HR-EMP-0100', 'employee_name' => 'Budi Santoso']]);
            }

            if ($request->method() === 'POST' && str_ends_with($url, '/api/resource/Crew Certificate Type')) {
                return Http::response(['data' => ['name' => $request['certificate_name']]]);
            }

            return Http::response(['data' => []]);
        });
    }

    /** @return array<string, mixed> */
    private function form(array $overrides = []): array
    {
        return array_replace([
            'first_name' => 'Budi', 'last_name' => 'Santoso', 'gender' => 'Male',
            'date_of_birth' => '1990-05-01', 'date_of_joining' => '2026-07-01',
            'rank' => 'Master', 'status' => 'Standby',
            'certificates' => [
                ['certificate_type' => 'Passport', 'certificate_number' => 'X1234567', 'issued_by' => 'Jakarta',
                    'issue_date' => '2024-01-10', 'expiry_date' => '2034-01-10'],
                ['certificate_type' => 'Seaman Book', 'certificate_number' => 'SB-778899', 'expiry_date' => '2028-03-01'],
                ['certificate_type' => ''], // the blank row the form always carries
            ],
            'bank_accounts' => [
                ['bank_name' => 'Bank Mandiri', 'account_holder' => 'Budi Santoso', 'account_number' => '1230009876', 'bank_address' => 'Jl. Sudirman, Jakarta'],
                ['bank_name' => 'BCA', 'account_holder' => 'Budi Santoso', 'account_number' => '5550001111'],
                ['bank_name' => '', 'account_number' => ''],
            ],
            'last_salary' => '12500000',
            'last_salary_currency' => 'IDR',
        ], $overrides);
    }

    public function test_passport_and_seaman_book_rows_are_copied_onto_the_employee_fields(): void
    {
        $this->fakeErp();

        $this->post('/crew', $this->form())->assertRedirect(route('crew.index'));

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/resource/Employee')
            && $request['passport_number'] === 'X1234567'
            && $request['date_of_issue'] === '2024-01-10'
            && $request['valid_upto'] === '2034-01-10'
            && $request['place_of_issue'] === 'Jakarta'
            && $request['custom_seaman_book_no'] === 'SB-778899'
            && $request['custom_seaman_book_expiry'] === '2028-03-01'
            && ! isset($request['branch']));
    }

    public function test_bank_accounts_are_saved_as_rows_and_the_first_is_the_primary(): void
    {
        $this->fakeErp();

        $this->post('/crew', $this->form())->assertRedirect(route('crew.index'));

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/resource/Employee')
            && count($request['custom_bank_accounts']) === 2 // the blank row is dropped
            && $request['custom_bank_accounts'][0] === [
                'bank_name' => 'Bank Mandiri', 'account_holder' => 'Budi Santoso',
                'account_number' => '1230009876', 'bank_address' => 'Jl. Sudirman, Jakarta',
            ]
            && $request['bank_name'] === 'Bank Mandiri'
            && $request['bank_ac_no'] === '1230009876'
            && (float) $request['custom_last_salary'] === 12500000.0
            && $request['custom_last_salary_currency'] === 'IDR');
    }

    public function test_a_bank_row_needs_a_bank_name(): void
    {
        $this->fakeErp();

        $this->post('/crew', $this->form(['bank_accounts' => [['account_number' => '123']]]))
            ->assertSessionHasErrors('bank_accounts.0.bank_name');
    }

    public function test_records_from_before_the_change_show_their_passport_and_bank_as_rows(): void
    {
        Http::fake(['*' => Http::response(['data' => [
            'name' => 'HR-EMP-0007', 'employee_name' => 'Agus Salim',
            'passport_number' => 'P998877', 'valid_upto' => '2030-01-01', 'place_of_issue' => 'Surabaya',
            'custom_seaman_book_no' => 'SB-1',
            'bank_name' => 'BRI', 'bank_ac_no' => '000111',
            'custom_certificates' => [['certificate_type' => 'Medical Certificate', 'certificate_number' => 'M-1']],
        ]])]);
        $this->get('/login');

        $crew = app(ErpnextCrewRepository::class)->find('HR-EMP-0007');

        $this->assertSame(['Passport', 'Seaman Book', 'Medical Certificate'], array_map(fn ($c) => $c->certificate_type, $crew->certificates));
        $this->assertSame('Surabaya', $crew->certificates[0]->issued_by);
        $this->assertSame('BRI', $crew->bank_accounts[0]->bank_name);
        $this->assertSame('000111', $crew->bank_accounts[0]->account_number);
    }

    public function test_a_new_certificate_type_is_created_in_the_master(): void
    {
        $this->fakeErp();

        $this->postJson('/crew/certificate-types', ['name' => 'Tanker Familiarization'])
            ->assertCreated()
            ->assertJson(['name' => 'Tanker Familiarization']);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with(urldecode($request->url()), '/api/resource/Crew Certificate Type')
            && $request['certificate_name'] === 'Tanker Familiarization');
    }

    public function test_new_types_wait_for_the_sync_while_types_are_still_a_select(): void
    {
        $this->fakeErp(typesAreMaster: false);

        $this->postJson('/crew/certificate-types', ['name' => 'Tanker Familiarization'])->assertStatus(409);

        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_the_document_types_page_lists_the_master_and_adds_to_it(): void
    {
        $this->fakeErp();

        $this->get('/crew/document-types')->assertOk()
            ->assertSee('Tanker Familiarization')->assertSee('Old Radio Licence')
            ->assertSee('name="certificate_name"', false);

        // Only active types reach the crew form.
        $this->get('/crew/create')->assertOk()
            ->assertSee('<option value="Tanker Familiarization"', false)
            ->assertDontSee('<option value="Old Radio Licence"', false);

        $this->post('/crew/document-types', ['certificate_name' => 'STCW Refresher', 'category' => 'Training', 'validity_months' => 60])
            ->assertSessionHasNoErrors()->assertRedirect();

        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with(urldecode($r->url()), '/api/resource/Crew Certificate Type')
            && $r['certificate_name'] === 'STCW Refresher' && $r['validity_months'] === 60);

        $this->patch('/crew/document-types/Old Radio Licence', ['is_active' => '1', 'category' => 'Other'])->assertRedirect();

        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_contains(urldecode($r->url()), '/api/resource/Crew Certificate Type/Old Radio Licence')
            && $r['is_active'] === 1);
    }

    public function test_before_the_sync_the_page_shows_the_shipped_list_without_an_add_form(): void
    {
        $this->fakeErp(typesAreMaster: false);

        $this->get('/crew/document-types')->assertOk()
            ->assertSee('erp:sync-crew-fields')->assertSee('Seaman Book')
            ->assertDontSee('name="certificate_name"', false);
    }
}
