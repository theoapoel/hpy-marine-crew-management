<?php

namespace Tests\Feature;

use App\Models\CrewAssignment;
use App\Services\Erpnext\ErpnextClient;
use App\Support\ErpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class CrewAssignmentExportTest extends TestCase
{
    use RefreshDatabase;

    private const KBM = 'Keenindo Bintas Marine';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.erpnext.enabled', true);
        config()->set('services.erpnext.url', 'https://kbm.hpy.co.id');
        Http::fake(['*' => Http::response(['data' => []])]);

        $this->withSession([
            ErpnextClient::SESSION_KEY => ['sid' => 'abc123', 'user' => 'budi@hpy.co.id', 'full_name' => 'Budi'],
            ErpnextClient::COMPANY_KEY => self::KBM,
            ErpUser::ROLES_KEY => ['HR Manager'],
        ]);
    }

    public function test_assignments_download_as_an_excel_workbook_with_the_list_filters(): void
    {
        CrewAssignment::create([
            'crew_name' => 'Budi Santoso', 'employee_id' => 'HR-EMP-00001', 'vessel' => 'MV Nusantara',
            'rank' => 'Master', 'status' => 'onboard', 'sign_on_date' => '2026-07-01',
            'planned_sign_off_date' => '2027-01-01', 'contract_months' => 6, 'wage' => 1500, 'wage_currency' => 'USD',
        ]);
        CrewAssignment::create(['crew_name' => 'Siti Aminah', 'vessel' => 'MV Nusantara', 'status' => 'planned']);
        CrewAssignment::create(['crew_name' => 'Other Company', 'status' => 'onboard', 'company' => 'Hasta Panca Yasa']);

        $response = $this->get(route('assignments.export', ['status' => 'onboard']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $rows = $this->sheetRows($response->streamedContent());

        $this->assertCount(2, $rows); // header + the one onboard assignment of this company
        $this->assertSame('Code', $rows[0][0]);
        $this->assertSame(['Budi Santoso', 'HR-EMP-00001'], array_slice($rows[1], 1, 2));
        $this->assertSame(['MV Nusantara', 'Master', 'Onboard'], array_slice($rows[1], 4, 3));
        $this->assertSame('2026-07-01', $rows[1][8]->format('Y-m-d'));
        $this->assertSame(6.0, (float) $rows[1][10]);
        $this->assertSame(1500.0, (float) $rows[1][16]);
    }

    public function test_the_list_offers_the_export(): void
    {
        $this->withoutVite()->get(route('assignments.index'))
            ->assertOk()
            ->assertSee(route('assignments.export'), false);
    }

    /** @return array<int, array<int, mixed>> */
    private function sheetRows(string $xlsx): array
    {
        $path = tempnam(sys_get_temp_dir(), 'asg').'.xlsx';
        file_put_contents($path, $xlsx);

        $reader = new Reader;
        $reader->open($path);

        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }

        $reader->close();
        @unlink($path);

        return $rows;
    }
}
