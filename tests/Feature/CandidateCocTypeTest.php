<?php

namespace Tests\Feature;

use App\Models\CrewCandidate;
use App\Services\Erpnext\ErpnextClient;
use App\Support\ErpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CandidateCocTypeTest extends TestCase
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

    /** ERP HPY after the sync: Crew Candidate.coc_type links to the COC Type master. */
    private function fakeMaster(array $types = ['ANT-II', 'ATT-III', 'Rating']): void
    {
        Http::fake(function (Request $request) use ($types) {
            $url = urldecode($request->url());

            if (str_contains($url, '/api/resource/DocType/Crew Candidate')) {
                return Http::response(['data' => ['fields' => [
                    ['fieldname' => 'coc_type', 'fieldtype' => 'Link', 'options' => 'COC Type'],
                ]]]);
            }
            if ($request->method() === 'GET' && preg_match('#/api/resource/COC Type(\?|$)#', $url)) {
                // exists() asks for one name; the dropdown asks for all of them.
                $rows = array_map(fn ($t) => ['name' => $t], $types);
                if (preg_match('#\["name","=","([^"]+)"\]#', $url, $m)) {
                    $rows = array_values(array_filter($rows, fn ($row) => $row['name'] === $m[1]));
                }

                return Http::response(['data' => $rows]);
            }
            if ($request->method() === 'POST' && str_ends_with($url, '/api/resource/COC Type')) {
                return Http::response(['data' => ['name' => $request['coc_type_name']]]);
            }

            return Http::response(['data' => []]);
        });
    }

    /** @return array<string, mixed> */
    private function candidate(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Slamet Riyadi', 'gender' => 'male', 'date_of_birth' => '1992-03-15',
            'phone' => '081298765432', 'source' => 'walk_in', 'status' => 'applicant',
        ], $overrides);
    }

    public function test_a_type_added_in_the_erp_master_can_be_chosen(): void
    {
        $this->fakeMaster(['ANT-II', 'ETO']);

        $this->post('/candidates', $this->candidate(['coc_type' => 'ETO']))->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('ETO', CrewCandidate::sole()->coc_type);
    }

    public function test_a_type_outside_the_master_is_rejected(): void
    {
        $this->fakeMaster(['ANT-II']);

        $this->post('/candidates', $this->candidate(['coc_type' => 'Made Up']))->assertSessionHasErrors('coc_type');
    }

    public function test_a_new_coc_type_is_created_from_the_form(): void
    {
        $this->fakeMaster();

        $this->postJson('/candidates/coc-types', ['name' => 'ETO'])->assertCreated()->assertJson(['name' => 'ETO']);

        Http::assertSent(fn ($r) => $r->method() === 'POST'
            && str_ends_with(urldecode($r->url()), '/api/resource/COC Type')
            && $r['coc_type_name'] === 'ETO');
    }

    public function test_the_form_offers_the_master_list_and_the_new_type_button(): void
    {
        $this->fakeMaster(['ANT-II', 'ETO']);

        $this->get('/candidates/create')
            ->assertOk()
            ->assertSee('<option value="ETO"', false)
            ->assertSee('+ New COC type')
            // Seaman book now lives with the certificates; personal, identity and contact share one tab.
            ->assertSee('data-tab-target="personal"', false)
            ->assertDontSee('data-tab-target="identity"', false)
            ->assertDontSee('data-tab-target="contact"', false);
    }

    public function test_the_sync_creates_the_master_and_links_the_field(): void
    {
        config()->set('services.erpnext.api_key', 'key');
        config()->set('services.erpnext.api_secret', 'secret');

        Http::fake(function (Request $request) {
            $url = urldecode($request->url());

            // Crew Candidate already exists with coc_type as a Select.
            if ($request->method() === 'GET' && str_contains($url, '/api/resource/DocType/Crew Candidate')) {
                return Http::response(['data' => ['fields' => [
                    ['fieldname' => 'coc_type', 'fieldtype' => 'Select', 'options' => "ANT-II\nRating"],
                ]]]);
            }
            if ($request->method() === 'GET' && str_contains($url, '/api/resource/DocType') && str_contains($url, 'Crew Candidate')) {
                return Http::response(['data' => [['name' => 'Crew Candidate']]]);
            }

            return Http::response(['data' => []]);
        });

        $this->artisan('erp:sync-candidate-doctype')->assertSuccessful();

        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with(urldecode($r->url()), '/api/resource/DocType')
            && $r['name'] === 'COC Type' && $r['autoname'] === 'field:coc_type_name');
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with(urldecode($r->url()), '/api/resource/COC Type')
            && $r['coc_type_name'] === 'ANT-II' && $r['department'] === 'Deck');
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_contains(urldecode($r->url()), '/api/resource/DocType/Crew Candidate')
            && collect($r['fields'])->firstWhere('fieldname', 'coc_type')['fieldtype'] === 'Link');
    }
}
