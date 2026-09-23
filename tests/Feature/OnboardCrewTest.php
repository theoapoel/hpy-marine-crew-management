<?php

namespace Tests\Feature;

use App\Models\CrewAssignment;
use App\Services\Erpnext\ErpnextClient;
use App\Services\OnboardCrew;
use App\Support\ErpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OnboardCrewTest extends TestCase
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

        $this->withSession([
            ErpnextClient::SESSION_KEY => ['sid' => 'abc123', 'user' => 'budi@hpy.co.id', 'full_name' => 'Budi'],
            ErpnextClient::COMPANY_KEY => self::KBM,
            ErpUser::ROLES_KEY => ['HR Manager'],
        ]);
    }

    /**
     * ERP HPY answers the "who is Onboard" query and the "who signed on since" query
     * with different Employees, like the real list endpoint would.
     *
     * @param  array<int, array<string, mixed>>  $onboard
     * @param  array<int, array<string, mixed>>  $signedOn
     */
    private function fakeEmployees(array $onboard, array $signedOn = []): void
    {
        Http::fake(function (Request $request) use ($onboard, $signedOn) {
            if (! str_contains($request->url(), '/api/resource/Employee')) {
                return Http::response(['data' => []]);
            }

            $filters = (string) ($request->data()['filters'] ?? urldecode((string) parse_url($request->url(), PHP_URL_QUERY)));

            return Http::response(['data' => str_contains($filters, 'custom_sign_on_date') ? $signedOn : $onboard]);
        });
    }

    private function assignment(array $attributes): CrewAssignment
    {
        return CrewAssignment::withoutEvents(fn () => CrewAssignment::forceCreate($attributes + [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'assignment_code' => 'ASG-TEST-' . \Illuminate\Support\Str::random(6),
            'company' => self::KBM,
            'status' => 'onboard',
        ]));
    }

    private function crew(): OnboardCrew
    {
        $this->get('/login'); // boot a request so the session above is live

        return app(OnboardCrew::class);
    }

    public function test_crew_put_aboard_in_erp_count_even_without_an_assignment(): void
    {
        $this->fakeEmployees([
            ['name' => 'HR-EMP-001', 'employee_name' => 'Agus Salim', 'custom_rank' => 'Master', 'custom_vessel' => 'MV Nusantara'],
            ['name' => 'HR-EMP-002', 'employee_name' => 'Budi Santoso', 'custom_rank' => 'Oiler', 'custom_vessel' => 'MT Bernice'],
        ]);

        // Known to both: ERP HPY says MT Bernice, the stale assignment says MV Nusantara.
        $this->assignment(['crew_name' => 'Budi Santoso', 'employee_id' => 'HR-EMP-002', 'vessel' => 'MV Nusantara', 'rank' => 'Oiler']);
        // Not in ERP HPY yet: only the assignment knows.
        $this->assignment(['crew_name' => 'Cahya Putra', 'employee_id' => null, 'vessel' => 'TB Keenindo 01', 'rank' => 'Cook']);

        $all = $this->crew()->all();

        $this->assertCount(3, $all, 'each person is counted once');
        $this->assertSame(['MV Nusantara' => 1, 'MT Bernice' => 1, 'TB Keenindo 01' => 1], $all->countBy('vessel')->all());
    }

    public function test_sign_ons_combine_assignments_and_erp_without_counting_twice(): void
    {
        $this->fakeEmployees([], [
            // Also recorded as an assignment below — must not count again.
            ['name' => 'HR-EMP-002', 'custom_sign_on_date' => '2026-08-01'],
            // Boarded in ERP HPY only.
            ['name' => 'HR-EMP-001', 'custom_sign_on_date' => '2026-09-01'],
        ]);

        $this->assignment(['crew_name' => 'Budi Santoso', 'employee_id' => 'HR-EMP-002', 'vessel' => 'MT Bernice', 'sign_on_date' => '2026-08-01']);
        $this->assignment(['crew_name' => 'Cahya Putra', 'vessel' => 'TB Keenindo 01', 'sign_on_date' => '2026-07-15', 'status' => 'signed_off']);

        $months = $this->crew()->signOnsSince(Carbon::parse('2026-01-01'))
            ->map(fn (Carbon $d) => $d->format('Y-m'))
            ->countBy()
            ->sortKeys()
            ->all();

        $this->assertSame(['2026-07' => 1, '2026-08' => 1, '2026-09' => 1], $months);
    }

    public function test_erp_being_down_still_leaves_the_local_crew(): void
    {
        Http::fake(['*' => Http::response('down', 500)]);

        $this->assignment(['crew_name' => 'Cahya Putra', 'employee_id' => 'HR-EMP-003', 'vessel' => 'TB Keenindo 01']);

        $this->assertSame(['TB Keenindo 01' => 1], $this->crew()->all()->countBy('vessel')->all());
    }
}
