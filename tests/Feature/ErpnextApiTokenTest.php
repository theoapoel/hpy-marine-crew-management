<?php

namespace Tests\Feature;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The server's own credentials. A key pair is what a deployed install should carry:
 * no login round-trip, no session to expire, no cookie to lose behind a proxy.
 */
class ErpnextApiTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.erpnext.enabled', true);
        config()->set('services.erpnext.url', 'https://kbm.hpy.co.id');
        config()->set('services.erpnext.username', null);
        config()->set('services.erpnext.password', null);
        config()->set('services.erpnext.api_key', 'KEY123');
        config()->set('services.erpnext.api_secret', 'SECRET456');
    }

    public function test_the_token_is_sent_and_no_login_is_needed(): void
    {
        Http::fake(['*/api/resource/Vessel*' => Http::response(['data' => [['name' => 'MT Bernice']]])]);

        $vessels = ErpnextClient::fromConfig()->list('Vessel', ['name']);

        $this->assertSame([['name' => 'MT Bernice']], $vessels);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'token KEY123:SECRET456'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/method/login'));
    }

    public function test_a_token_alone_is_enough_to_be_configured(): void
    {
        $this->assertTrue(ErpnextClient::fromConfig()->isConfigured());
    }

    /** A user's own session still wins, so ERP HPY applies that user's permissions. */
    public function test_a_logged_in_user_is_served_by_their_own_session(): void
    {
        Http::fake(['*/api/resource/Vessel*' => Http::response(['data' => []])]);

        $this->withSession([
            ErpnextClient::SESSION_KEY => [
                'sid' => 'abc123', 'user' => 'budi@hpy.co.id', 'full_name' => 'Budi Santoso',
            ],
        ])->get('/');

        ErpnextClient::fromConfig()->list('Vessel', ['name']);

        Http::assertSent(fn ($request) => ! $request->hasHeader('Authorization')
            && str_contains((string) $request->header('Cookie')[0] ?? '', 'sid=abc123'));
    }

    /** A wrong key pair cannot be repaired by retrying, unlike an expired session. */
    public function test_a_rejected_token_is_not_retried(): void
    {
        Http::fake(['*/api/resource/Vessel*' => Http::response(['exc' => 'not permitted'], 401)]);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        try {
            ErpnextClient::fromConfig()->list('Vessel', ['name']);
        } finally {
            Http::assertSentCount(1);
        }
    }
}
