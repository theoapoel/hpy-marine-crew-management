<?php

namespace Tests\Feature;

use App\Services\Erpnext\ErpnextClient;
use App\Support\ErpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrandLogoTest extends TestCase
{
    use RefreshDatabase;

    private function workingIn(string $company): void
    {
        config()->set('services.erpnext.url', 'https://kbm.hpy.co.id');
        Http::fake(['*/api/resource/Company*' => Http::response(['data' => [
            ['name' => 'Keenindo Bintas Marine', 'company_logo' => '/files/kbm-logo-transparent.png', 'abbr' => 'KBM'],
            ['name' => 'Donmar Jaladri Utama', 'company_logo' => null, 'abbr' => 'DJU'],
        ]]), '*' => Http::response(['data' => []])]);

        $this->withSession([
            ErpnextClient::SESSION_KEY => ['sid' => 'abc123', 'user' => 'budi@hpy.co.id', 'full_name' => 'Budi'],
            ErpnextClient::COMPANY_KEY => $company,
            ErpUser::ROLES_KEY => ['HR Manager'],
        ]);
    }

    public function test_a_company_shows_its_own_logo_from_erp_hpy(): void
    {
        $this->workingIn('Keenindo Bintas Marine');

        $this->get('/principals')->assertOk()
            ->assertSee('erp-file?path=%2Ffiles%2Fkbm-logo-transparent.png', false)
            ->assertDontSee('brand/hpy-logo.png');
    }

    public function test_a_company_without_a_logo_keeps_the_hpy_logo(): void
    {
        $this->workingIn('Donmar Jaladri Utama');

        $this->get('/principals')->assertOk()->assertSee('brand/hpy-logo.png');
    }
}
