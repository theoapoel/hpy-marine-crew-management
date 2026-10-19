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

/** The CV PDF in the agency's Template CV layout. */
class CrewCvTest extends TestCase
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
                return Http::response(['data' => [
                    'name' => 'HR-EMP-0042', 'employee_name' => 'Irfandi Saputra', 'custom_rank' => 'Second Engineer',
                    'company' => self::KBM, 'gender' => 'Male', 'date_of_birth' => '1990-05-01',
                    'cell_number' => '0812345678', 'personal_email' => 'irfandi@example.com',
                    'person_to_be_contacted' => 'Siti Saputra', 'relation' => 'Wife',
                    'custom_certificates' => [
                        ['certificate_type' => 'Passport', 'certificate_number' => 'X1234567', 'issued_by' => 'Jakarta', 'issue_date' => '2024-01-10', 'expiry_date' => '2034-01-10'],
                        ['certificate_type' => 'Certificate of Competency', 'certificate_number' => 'COC-77', 'issued_by' => 'Jakarta', 'issue_date' => '2022-03-01', 'expiry_date' => '2027-03-01'],
                        ['certificate_type' => 'Basic Safety Training', 'certificate_number' => 'BST-55', 'issued_by' => 'Semarang', 'issue_date' => '2023-06-15'],
                    ],
                ]]);
            }
            if (preg_match('#/api/resource/Vessel\?#', $url)) {
                return Http::response(['data' => [
                    ['name' => 'MV Nusantara', 'vessel_name' => 'MV Nusantara', 'vessel_type' => 'Bulk Carrier', 'gross_tonnage' => 25063, 'principal' => 'PT Pelayaran Jaya'],
                ]]);
            }

            return Http::response(['data' => []]);
        });
    }

    public function test_the_cv_streams_as_a_pdf_with_the_crew_members_data(): void
    {
        CrewAssignment::create([
            'crew_name' => 'Irfandi Saputra', 'employee_id' => 'HR-EMP-0042', 'vessel' => 'MV Nusantara',
            'rank' => 'Second Engineer', 'company' => self::KBM, 'status' => 'signed_off',
            'sign_on_date' => '2025-01-10', 'sign_off_date' => '2025-09-02',
        ]);

        $response = $this->get('/crew/HR-EMP-0042/cv')->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
    }

    public function test_download_sends_the_pdf_as_an_attachment(): void
    {
        $this->get('/crew/HR-EMP-0042/cv?download=1')->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="CV - Irfandi Saputra.pdf"');
    }

    public function test_the_view_fills_the_forms_sections_from_the_record(): void
    {
        CrewAssignment::create([
            'crew_name' => 'Irfandi Saputra', 'employee_id' => 'HR-EMP-0042', 'vessel' => 'MV Nusantara',
            'rank' => 'Second Engineer', 'company' => self::KBM, 'status' => 'signed_off',
            'sign_on_date' => '2025-01-10', 'sign_off_date' => '2025-09-02',
        ]);

        $crew = app(\App\Repositories\CrewRepositoryInterface::class)->find('HR-EMP-0042');
        $html = view('crew.cv', app(\App\Services\CrewCv::class)->data($crew))->render();

        $this->assertStringContainsString('Post Applied: Second Engineer', $html);
        $this->assertStringContainsString('X1234567', $html);              // passport row
        $this->assertStringContainsString('COC-77', $html);                // competency table
        $this->assertStringContainsString('BST-55', $html);                // fills the fixed BST row
        $this->assertStringContainsString('Ship Security Officer', $html); // blank fixed rows still print
        $this->assertStringContainsString('25,063', $html);                // sea service GRT
        $this->assertStringContainsString('PT Pelayaran Jaya', $html);
        $this->assertStringContainsString('Siti Saputra', $html);
        $this->assertStringContainsString('Relationship: Wife', $html);
    }
}
