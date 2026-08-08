<?php

namespace Tests\Feature;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ErpnextLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.erpnext.url', 'https://kbm.hpy.co.id');
    }

    public function test_guests_are_sent_to_the_login_form(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/crew')->assertRedirect(route('login'));
    }

    /** @param array<int, string> $companies */
    private function fakeErpnext(array $companies = ['Keenindo Bintas Marine']): void
    {
        Http::fake([
            '*/api/method/login' => Http::response(
                ['message' => 'Logged In', 'full_name' => 'Budi Santoso'],
                200,
                ['Set-Cookie' => 'sid=abc123; Path=/'],
            ),
            '*/api/method/frappe.auth.get_logged_user' => Http::response(['message' => 'budi@hpy.co.id']),
            '*/api/resource/Company*' => Http::response([
                'data' => array_map(fn ($c) => ['name' => $c], $companies),
            ]),
            '*' => Http::response(['data' => []]),
        ]);
    }

    public function test_login_with_a_single_company_lands_on_the_dashboard(): void
    {
        $this->fakeErpnext();

        $response = $this->post('/login', ['usr' => 'budi@hpy.co.id', 'pwd' => 'secret']);

        $response->assertRedirect(route('company.select'));
        $this->assertSame('abc123', session(ErpnextClient::SESSION_KEY . '.sid'));
        $this->assertSame('Budi Santoso', session(ErpnextClient::SESSION_KEY . '.full_name'));

        // Only one company, so it is picked automatically.
        $this->get(route('company.select'))->assertRedirect(route('dashboard'));
        $this->assertSame('Keenindo Bintas Marine', session(ErpnextClient::COMPANY_KEY));

        $this->get('/')->assertOk()->assertSee('Budi Santoso')->assertSee('Keenindo Bintas Marine');
    }

    public function test_login_with_two_companies_asks_which_one(): void
    {
        $this->fakeErpnext(['Keenindo Bintas Marine', 'Hasta Panca Yasa']);

        $this->post('/login', ['usr' => 'budi@hpy.co.id', 'pwd' => 'secret'])
            ->assertRedirect(route('company.select'));

        $this->get(route('company.select'))
            ->assertOk()
            ->assertSee('Pilih Company')
            ->assertSee('Hasta Panca Yasa');

        $this->assertNull(session(ErpnextClient::COMPANY_KEY));

        // The dashboard stays out of reach until a company is chosen.
        $this->get('/')->assertRedirect(route('company.select'));

        $this->post('/company', ['company' => 'Hasta Panca Yasa'])->assertRedirect(route('dashboard'));
        $this->assertSame('Hasta Panca Yasa', session(ErpnextClient::COMPANY_KEY));
    }

    public function test_an_unknown_company_is_rejected(): void
    {
        $this->fakeErpnext(['Keenindo Bintas Marine', 'Hasta Panca Yasa']);

        $this->withSession([ErpnextClient::SESSION_KEY => ['sid' => 'abc123', 'user' => 'budi@hpy.co.id', 'full_name' => 'Budi']])
            ->post('/company', ['company' => 'PT Bukan Punya HPY'])
            ->assertSessionHasErrors('company');

        $this->assertNull(session(ErpnextClient::COMPANY_KEY));
    }

    public function test_login_keeps_the_page_the_guest_was_heading_for(): void
    {
        $this->fakeErpnext();

        $this->get('/crew')->assertRedirect(route('login'));

        $this->post('/login', ['usr' => 'budi@hpy.co.id', 'pwd' => 'secret'])
            ->assertRedirect(route('company.select'));

        $this->get(route('company.select'))->assertRedirect(url('/crew'));
    }

    public function test_a_session_that_expired_on_the_chooser_still_lands_on_the_dashboard(): void
    {
        $this->fakeErpnext(['Keenindo Bintas Marine', 'Hasta Panca Yasa']);

        // The chooser was on screen when the ERP HPY session ran out, so /company is
        // what gets remembered as the page to come back to.
        $this->get(route('company.select'))->assertRedirect(route('login'));

        $this->post('/login', ['usr' => 'budi@hpy.co.id', 'pwd' => 'secret'])
            ->assertRedirect(route('company.select'));

        // Following that would only show the chooser again.
        $this->post('/company', ['company' => 'Hasta Panca Yasa'])->assertRedirect(route('dashboard'));
        $this->assertSame('Hasta Panca Yasa', session(ErpnextClient::COMPANY_KEY));
    }

    public function test_switching_company_from_a_page_stays_on_that_page(): void
    {
        $this->fakeErpnext(['Keenindo Bintas Marine', 'Hasta Panca Yasa']);

        $this->withSession([
            ErpnextClient::SESSION_KEY => ['sid' => 'abc123', 'user' => 'budi@hpy.co.id', 'full_name' => 'Budi'],
            ErpnextClient::COMPANY_KEY => 'Keenindo Bintas Marine',
        ])->post('/company', ['company' => 'Hasta Panca Yasa', 'redirect_to' => url('/crew')])
            ->assertRedirect(url('/crew'));
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        Http::fake(['*/api/method/login' => Http::response(['message' => 'Invalid login'], 401)]);

        $this->post('/login', ['usr' => 'budi@hpy.co.id', 'pwd' => 'salah'])
            ->assertRedirect()
            ->assertSessionHasErrors('usr');

        $this->assertNull(session(ErpnextClient::SESSION_KEY . '.sid'));
    }

    public function test_logout_clears_the_session(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Logged Out'])]);

        $this->withSession([ErpnextClient::SESSION_KEY => [
            'sid' => 'abc123', 'user' => 'budi@hpy.co.id', 'full_name' => 'Budi Santoso',
        ]])->post('/logout')->assertRedirect(route('login'));

        $this->assertNull(session(ErpnextClient::SESSION_KEY . '.sid'));
    }
}
