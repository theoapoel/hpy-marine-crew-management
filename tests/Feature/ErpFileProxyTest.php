<?php

namespace Tests\Feature;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ErpFileProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.erpnext.url', 'https://kbm.hpy.co.id');

        $this->withSession([
            ErpnextClient::SESSION_KEY => ['sid' => 'abc123', 'user' => 'budi@hpy.co.id', 'full_name' => 'Budi'],
            ErpnextClient::COMPANY_KEY => 'Keenindo Bintas Marine',
        ]);
    }

    public function test_it_serves_a_private_erpnext_file_the_browser_cannot_reach(): void
    {
        Http::fake([
            '*/private/files/coc.pdf' => Http::response('%PDF-1.4 fake', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $this->get('/erp-file?path=/private/files/coc.pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertSee('%PDF-1.4 fake');
    }

    public function test_it_refuses_paths_outside_the_erpnext_file_folders(): void
    {
        Http::fake();

        $this->get('/erp-file?path=/api/method/frappe.client.get_password')->assertNotFound();
        $this->get('/erp-file?path=https://example.com/evil')->assertNotFound();
        $this->get('/erp-file?path=/files/../../etc/passwd')->assertNotFound();
    }
}
