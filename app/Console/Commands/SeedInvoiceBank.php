<?php

namespace App\Console\Commands;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Console\Command;

/**
 * The remittance details printed on every manning invoice, held as masters rather
 * than typed into the print format.
 *
 * KBM bills in USD but its chart of accounts had only IDR bank ledgers, so this
 * also opens the USD bank ledger the Bank Account hangs off. Nothing is posted to
 * it here — it exists so the account number, bank and SWIFT code have one home.
 *
 * The branch address is an Address linked to the Bank Account, which is where
 * ERP HPY expects it; the print format reads it back through that link.
 *
 * Idempotent.
 */
class SeedInvoiceBank extends Command
{
    protected $signature = 'erp:seed-invoice-bank {--dry-run : show what would be written, write nothing}';

    protected $description = 'Create the BCA USD ledger, Bank, Bank Account and branch Address used on KBM invoices';

    private const COMPANY = 'Keenindo Bintas Marine';

    private const BANK = 'Bank Central Asia';

    private const SWIFT = 'CENAIDJA';

    private const ACCOUNT_NO = '3720244399';

    private const BENEFICIARY = 'KEENINDO BINTAS MARINE PT';

    private const BRANCH = 'KCU Kedoya Permai Wilayah XII - Wisma Asia';

    /** Ledger under the existing "1120.000 - Bank - KBM" group. */
    private const LEDGER_NUMBER = '1120.001';

    private const LEDGER_NAME = 'Bank BCA USD';

    private const PARENT_LEDGER = '1120.000 - Bank - KBM';

    public function handle(ErpnextClient $client): int
    {
        $dry = (bool) $this->option('dry-run');

        $ledger = $this->ledger($client, $dry);
        $this->bank($client, $dry);
        $account = $this->bankAccount($client, $dry, $ledger);
        $this->address($client, $dry, $account);

        if ($dry) {
            $this->newLine();
            $this->comment('Dry run: nothing was written to ERP HPY.');
        }

        return self::SUCCESS;
    }

    private function ledger(ErpnextClient $client, bool $dry): string
    {
        $name = self::LEDGER_NUMBER . ' - ' . self::LEDGER_NAME . ' - KBM';

        if ($client->exists('Account', $name)) {
            $this->line("= Account {$name}");

            return $name;
        }

        if (! $dry) {
            $client->create('Account', [
                'account_name' => self::LEDGER_NAME,
                'account_number' => self::LEDGER_NUMBER,
                'parent_account' => self::PARENT_LEDGER,
                'company' => self::COMPANY,
                'account_type' => 'Bank',
                'account_currency' => 'USD',
                'is_group' => 0,
            ]);
        }

        $this->info("+ Account {$name} (USD)");

        return $name;
    }

    private function bank(ErpnextClient $client, bool $dry): void
    {
        if ($client->exists('Bank', self::BANK)) {
            $this->line('= Bank ' . self::BANK);

            return;
        }

        if (! $dry) {
            $client->create('Bank', [
                'bank_name' => self::BANK,
                'swift_number' => self::SWIFT,
            ]);
        }

        $this->info('+ Bank ' . self::BANK . ' (SWIFT ' . self::SWIFT . ')');
    }

    /** ERP HPY names a Bank Account "<account name> - <bank>". */
    private function bankAccount(ErpnextClient $client, bool $dry, string $ledger): string
    {
        $name = self::BENEFICIARY . ' - ' . self::BANK;

        if ($client->exists('Bank Account', $name)) {
            $this->line("= Bank Account {$name}");

            return $name;
        }

        if (! $dry) {
            $client->create('Bank Account', [
                'account_name' => self::BENEFICIARY,
                'bank' => self::BANK,
                'account' => $ledger,
                'company' => self::COMPANY,
                'is_company_account' => 1,
                'is_default' => 1,
                'bank_account_no' => self::ACCOUNT_NO,
                'branch_code' => self::BRANCH,
            ]);
        }

        $this->info("+ Bank Account {$name} (no. " . self::ACCOUNT_NO . ')');

        return $name;
    }

    private function address(ErpnextClient $client, bool $dry, string $bankAccount): void
    {
        $title = self::BANK . ' ' . self::BRANCH;

        if ($client->list('Address', ['name'], [['address_title', '=', $title]], 1) !== []) {
            $this->line("= Address {$title}");

            return;
        }

        if (! $dry) {
            $client->create('Address', [
                'address_title' => $title,
                'address_type' => 'Other',
                'address_line1' => 'Jl. Raya Perjuangan, Ruko Prima Kedoya Plaza Blok A1 No. 4-5',
                'city' => 'Jakarta Barat',
                'country' => 'Indonesia',
                'links' => [
                    ['link_doctype' => 'Bank Account', 'link_name' => $bankAccount],
                ],
            ]);
        }

        $this->info("+ Address {$title}");
    }
}
