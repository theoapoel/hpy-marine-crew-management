<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncCrewFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.erpnext.enabled', true);
        config()->set('services.erpnext.url', 'https://kbm.hpy.co.id');
        config()->set('services.erpnext.api_key', 'key');
        config()->set('services.erpnext.api_secret', 'secret');
    }

    public function test_it_makes_certificate_types_master_data_and_adds_the_bank_table(): void
    {
        // A fresh-ish instance: the certificate table exists with a hardcoded Select,
        // nothing else from this change does.
        Http::fake(function (Request $request) {
            $url = urldecode($request->url());

            if ($request->method() === 'GET' && str_contains($url, '/api/resource/DocType/Employee Certificate')) {
                return Http::response(['data' => ['fields' => [
                    ['fieldname' => 'certificate_type', 'fieldtype' => 'Select', 'options' => "Passport\nSeaman Book"],
                    ['fieldname' => 'certificate_number', 'fieldtype' => 'Data'],
                ]]]);
            }

            if ($request->method() === 'GET' && str_contains($url, '/api/resource/DocType') && str_contains($url, 'Employee Certificate')) {
                return Http::response(['data' => [['name' => 'Employee Certificate']]]); // exists()
            }

            return Http::response(['data' => []]);
        });

        $this->artisan('erp:sync-crew-fields')->assertSuccessful();

        $posted = fn (string $doctype, callable $check) => Http::assertSent(fn ($r) => $r->method() === 'POST'
            && str_ends_with(urldecode($r->url()), "/api/resource/{$doctype}") && $check($r));

        // The master, seeded — Passport and Seaman Book included, the Documents menu needs them.
        $posted('DocType', fn ($r) => $r['name'] === 'Crew Certificate Type' && $r['autoname'] === 'field:certificate_name');
        $posted('Crew Certificate Type', fn ($r) => $r['certificate_name'] === 'Passport' && $r['category'] === 'Identity');
        $posted('Crew Certificate Type', fn ($r) => $r['certificate_name'] === 'Seaman Book');

        // The old Select re-pointed at it.
        Http::assertSent(fn ($r) => $r->method() === 'PUT'
            && str_contains(urldecode($r->url()), '/api/resource/DocType/Employee Certificate')
            && collect($r['fields'])->firstWhere('fieldname', 'certificate_type') === [
                'fieldname' => 'certificate_type', 'fieldtype' => 'Link', 'options' => 'Crew Certificate Type',
            ]);

        // Bank accounts as a child table, and the Employee fields that hold them.
        $posted('DocType', fn ($r) => $r['name'] === 'Employee Bank Account' && $r['istable'] === 1
            && collect($r['fields'])->pluck('fieldname')->all() === ['bank_name', 'account_holder', 'account_number', 'bank_address']);
        $posted('Custom Field', fn ($r) => $r['fieldname'] === 'custom_bank_accounts' && $r['options'] === 'Employee Bank Account');
        $posted('Custom Field', fn ($r) => $r['fieldname'] === 'custom_last_salary' && $r['fieldtype'] === 'Currency');
        $posted('Custom Field', fn ($r) => $r['fieldname'] === 'custom_last_salary_currency' && $r['options'] === 'Currency');
    }
}
