<?php

namespace Tests\Feature;

use App\Services\Erpnext\ErpnextClient;
use App\Support\ErpUser;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class CrewExportTest extends TestCase
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

    public function test_crew_master_downloads_as_an_excel_workbook_with_every_page(): void
    {
        // Two full pages of 500 and a short third one: the export must walk them all.
        Http::fake(function (Request $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);
            $start = (int) ($query['limit_start'] ?? 0);

            $rows = match ($start) {
                0 => array_map(fn ($i) => $this->employee($i), range(1, 500)),
                500 => array_map(fn ($i) => $this->employee($i), range(501, 1000)),
                default => [$this->employee(1001)],
            };

            return Http::response(['data' => $rows]);
        });

        $response = $this->get(route('crew.export', ['status' => 'Onboard']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));

        // The workbook is streamed, so ERP HPY is only called once the body is read.
        $rows = $this->sheetRows($response->streamedContent());

        Http::assertSent(fn (Request $r) => str_contains(urldecode($r->url()), '["company","=","Keenindo Bintas Marine"]')
            && str_contains(urldecode($r->url()), '["custom_crew_status","=","Onboard"]'));
        Http::assertSentCount(3);

        $this->assertCount(1002, $rows); // header + 1001 crew
        $this->assertSame('Employee ID', $rows[0][0]);
        $this->assertSame('Name', $rows[0][1]);
        $this->assertSame(['HR-EMP-00001', 'Crew 1', 'Master', 'Deck', 'Onboard', 'MV Nusantara'], array_slice($rows[1], 0, 6));
        $this->assertSame('ID12345', $rows[1][11]);
        $this->assertSame('2026-07-01', $rows[1][6]->format('Y-m-d'));
        $this->assertSame('HR-EMP-01001', $rows[1001][0]);
    }

    public function test_export_needs_an_erp_session(): void
    {
        $this->flushSession();

        $this->get(route('crew.export'))->assertRedirect(route('login'));
    }

    /** @return array<string, mixed> */
    private function employee(int $i): array
    {
        return [
            'name' => sprintf('HR-EMP-%05d', $i),
            'employee_name' => "Crew {$i}",
            'status' => 'Active',
            'company' => self::KBM,
            'department' => 'Deck',
            'custom_rank' => 'Master',
            'custom_crew_status' => 'Onboard',
            'custom_vessel' => 'MV Nusantara',
            'custom_nationality' => 'Indonesia',
            'custom_seaman_book_no' => 'ID12345',
            'custom_sign_on_date' => '2026-07-01',
            'contract_end_date' => '2027-01-01',
            'custom_last_salary' => 1500,
            'custom_last_salary_currency' => 'USD',
        ];
    }

    /** @return array<int, array<int, mixed>> */
    private function sheetRows(string $xlsx): array
    {
        $path = tempnam(sys_get_temp_dir(), 'crew').'.xlsx';
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
