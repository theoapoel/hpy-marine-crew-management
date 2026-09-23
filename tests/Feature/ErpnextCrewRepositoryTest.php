<?php

namespace Tests\Feature;

use App\Repositories\ErpnextCrewRepository;
use App\Services\Erpnext\ErpnextClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ErpnextCrewRepositoryTest extends TestCase
{
    private function repository(string $company = 'Keenindo Bintas Marine'): ErpnextCrewRepository
    {
        config()->set('services.erpnext.url', 'https://kbm.hpy.co.id');

        // Boot a request carrying an ERP HPY session for the given company.
        $this->withSession([
            ErpnextClient::SESSION_KEY => [
                'sid' => 'abc123', 'user' => 'budi@hpy.co.id', 'full_name' => 'Budi Santoso',
            ],
            ErpnextClient::COMPANY_KEY => $company,
        ])->get('/');

        return new ErpnextCrewRepository(ErpnextClient::fromConfig());
    }

    public function test_adding_crew_posts_to_the_employee_doctype(): void
    {
        Http::fake([
            '*/api/resource/Employee' => Http::response(['data' => [
                'name' => 'HR-EMP-0001', 'employee_name' => 'Budi Santoso', 'designation' => 'Master',
            ]]),
        ]);

        $crew = $this->repository()->create([
            'name' => 'Budi Santoso',
            'first_name' => 'Budi',
            'last_name' => 'Santoso',
            'rank' => 'Master',
            'status' => 'Onboard',
            'nationality' => 'Indonesia',
            'gender' => 'Male',
            'date_of_birth' => '1990-05-01',
            'sign_on_date' => '2026-07-01',
            'passport_number' => 'X1234567',
        ]);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/api/resource/Employee')
                // ERP HPY composes employee_name from these two.
                && $request['first_name'] === 'Budi'
                && $request['last_name'] === 'Santoso'
                && $request['gender'] === 'Male'
                && $request['date_of_birth'] === '1990-05-01'
                && $request['date_of_joining'] === '2026-07-01'
                && $request['company'] === 'Keenindo Bintas Marine'
                // Rank is a Link to the instance's own Rank doctype.
                && $request['custom_rank'] === 'Master'
                && $request['passport_number'] === 'X1234567'
                && $request['custom_sign_on_date'] === '2026-07-01'
                // Employee.status only knows Active/Left; the crew status rides along.
                && $request['status'] === 'Active'
                && $request['custom_crew_status'] === 'Onboard'
                && $request['custom_nationality'] === 'Indonesia';
        });

        $this->assertSame('Budi Santoso', $crew->name);
        $this->assertSame('Master', $crew->rank);
        $this->assertSame('HR-EMP-0001', $crew->id);
    }

    public function test_crew_list_reads_the_employee_doctype_scoped_to_the_session_company(): void
    {
        Http::fake([
            '*/api/resource/Employee*' => Http::response(['data' => [
                ['name' => 'HR-EMP-0001', 'employee_name' => 'Budi Santoso', 'designation' => 'Master'],
            ]]),
        ]);

        $page = $this->repository('Hasta Panca Yasa')->paginate();

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/api/resource/Employee')
                && str_contains(urldecode($request->url()), '["company","=","Hasta Panca Yasa"]');
        });

        $this->assertSame('Budi Santoso', $page->items()[0]->name);
    }

    public function test_the_rank_filter_narrows_the_list_to_one_rank(): void
    {
        Http::fake(['*/api/resource/Employee*' => Http::response(['data' => []])]);

        $this->repository()->paginate(['rank' => 'Able Seaman']);
        $this->repository()->paginate(['rank' => 'Unspecified']);

        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), '["custom_rank","=","Able Seaman"]'));
        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), '["custom_rank","is","not set"]'));
    }

    public function test_rank_summary_counts_every_crew_per_rank_and_status(): void
    {
        Http::fake(['*/api/resource/Employee*' => Http::response(['data' => [
            ['custom_rank' => 'Able Seaman', 'custom_crew_status' => 'Onboard'],
            ['custom_rank' => 'Able Seaman', 'custom_crew_status' => 'Onboard'],
            ['custom_rank' => 'Able Seaman', 'custom_crew_status' => 'Standby'],
            ['custom_rank' => 'Oiler', 'custom_crew_status' => 'Sign Off'],
            ['custom_rank' => null, 'custom_crew_status' => 'Standby'],
        ]])]);

        $summary = $this->repository()->rankSummary();

        $this->assertSame(['total' => 3, 'Onboard' => 2, 'Standby' => 1], $summary['Able Seaman']);
        $this->assertSame(['total' => 1, 'Sign Off' => 1], $summary['Oiler']);
        $this->assertSame(['total' => 1, 'Standby' => 1], $summary['Unspecified']);

        // Scoped like the list: only the session company.
        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), '["company","=","Keenindo Bintas Marine"]'));
    }
}
