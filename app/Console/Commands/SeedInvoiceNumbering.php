<?php

namespace App\Console\Commands;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Console\Command;

/**
 * KBM numbers its manning invoices INV/076/PTKBM/VI/2026 — a running counter, then
 * the month in Roman numerals, then the year.
 *
 * ERP HPY naming series have no Roman month placeholder, so there is one series per
 * month. They all share a single counter because a series' counter is keyed on
 * whatever precedes the "###" — here always "INV/" — and the month sits after it.
 * That is what keeps June running on from May instead of restarting at 001.
 *
 * A Before Insert script can pick the month's series from the posting date so nobody
 * has to choose it by hand, but server scripts are switched off on this site, and an
 * enabled script there makes every Sales Invoice insert fail. So it is created
 * disabled; enable it only once the site allows server scripts. Until then the month
 * is chosen from the naming series dropdown.
 *
 * Idempotent.
 */
class SeedInvoiceNumbering extends Command
{
    protected $signature = 'erp:seed-invoice-numbering
                            {--dry-run : show what would be written, write nothing}
                            {--force : rewrite the series list and script even when present}
                            {--start= : set the INV/ counter, so the next invoice is this number plus one}
                            {--enable-script : enable the naming script (only works if the site allows server scripts)}';

    protected $description = 'Set up the INV/###/PTKBM/<month>/<year> numbering for KBM sales invoices';

    private const COMPANY = 'Keenindo Bintas Marine';

    private const SCRIPT = 'KBM Sales Invoice Naming';

    private const PROPERTY_SETTER = 'Sales Invoice-naming_series-options';

    private const MONTHS = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    public function handle(ErpnextClient $client): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->series($client, $dry);
        $this->script($client, $dry);
        $this->counter($client, $dry);

        if ($dry) {
            $this->newLine();
            $this->comment('Dry run: nothing was written to ERP HPY.');
        }

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function kbmSeries(): array
    {
        return array_map(fn ($month) => "INV/.###./PTKBM/{$month}/.YYYY.", self::MONTHS);
    }

    /**
     * The options list is appended to, never replaced: the stock series still names
     * invoices for the other company, and existing documents reference it.
     */
    private function series(ErpnextClient $client, bool $dry): void
    {
        $current = $client->list('Property Setter', ['name', 'value'], [
            ['name', '=', self::PROPERTY_SETTER],
        ], 1);

        $existing = $current === []
            ? ['ACC-SINV-.YYYY.-']
            : array_filter(array_map('trim', explode("\n", (string) $current[0]['value'])));

        $missing = array_diff($this->kbmSeries(), $existing);

        if ($missing === [] && ! $this->option('force')) {
            $this->line('= naming series (12 KBM series already listed)');

            return;
        }

        $options = implode("\n", array_merge($existing, $missing));

        if (! $dry) {
            if ($current === []) {
                $client->create('Property Setter', [
                    'doctype_or_field' => 'DocField',
                    'doc_type' => 'Sales Invoice',
                    'field_name' => 'naming_series',
                    'property' => 'options',
                    'property_type' => 'Text',
                    'value' => $options,
                ]);
            } else {
                $client->update('Property Setter', self::PROPERTY_SETTER, ['value' => $options]);
            }
        }

        foreach ($missing as $series) {
            $this->info("+ series {$series}");
        }
    }

    private function script(ErpnextClient $client, bool $dry): void
    {
        if ($client->exists('Server Script', self::SCRIPT) && ! $this->option('force')) {
            $this->line('= Server Script ' . self::SCRIPT . ' (use --force to rewrite)');

            return;
        }

        $payload = [
            'script_type' => 'DocType Event',
            'reference_doctype' => 'Sales Invoice',
            'doctype_event' => 'Before Insert',
            // Enabled only on demand: while the site has server scripts switched off,
            // an enabled script aborts every Sales Invoice insert.
            'disabled' => $this->option('enable-script') ? 0 : 1,
            'script' => $this->body(),
        ];

        if ($dry) {
            $this->info('+ Server Script ' . self::SCRIPT);

            return;
        }

        if ($client->exists('Server Script', self::SCRIPT)) {
            $client->update('Server Script', self::SCRIPT, $payload);
            $this->info('~ Server Script ' . self::SCRIPT . ' (rewritten)');

            return;
        }

        $client->create('Server Script', ['name' => self::SCRIPT] + $payload);
        $this->info('+ Server Script ' . self::SCRIPT . ($payload['disabled'] ? ' (disabled)' : ''));

        if ($payload['disabled']) {
            $this->comment('  needs `bench set-config -g server_script_enabled 1` on the ERP HPY server,');
            $this->comment('  then rerun with --force --enable-script. Until then pick the month series by hand.');
        }
    }

    /**
     * The counter is shared by all twelve series, because it is keyed on what comes
     * before the digits — always "INV/". Continuing an existing sequence therefore
     * means setting that one counter.
     */
    private function counter(ErpnextClient $client, bool $dry): void
    {
        $start = $this->option('start');

        if ($start === null) {
            return;
        }

        if (! $dry) {
            $client->update('Document Naming Settings', 'Document Naming Settings', [
                'prefix' => 'INV/',
                'current_value' => (int) $start,
            ]);
            $client->call('run_doc_method', [
                'dt' => 'Document Naming Settings',
                'dn' => 'Document Naming Settings',
                'method' => 'update_series_start',
            ]);
        }

        $this->info('~ INV/ counter set to ' . (int) $start . ' (next invoice: ' . str_pad((string) ((int) $start + 1), 3, '0', STR_PAD_LEFT) . ')');
    }

    /**
     * Runs before naming, so setting naming_series here decides the number.
     * Amended documents keep ERP HPY's own amended naming.
     */
    private function body(): string
    {
        $months = "['" . implode("', '", self::MONTHS) . "']";

        return <<<PY
        # Numbers KBM manning invoices as INV/###/PTKBM/<roman month>/<year>.
        # Managed by erp:seed-invoice-numbering — edit that command, not this script.
        if doc.company == "{$this->company()}" and not doc.get("amended_from"):
            roman = {$months}
            posting = frappe.utils.getdate(doc.posting_date or frappe.utils.nowdate())
            doc.naming_series = "INV/.###./PTKBM/" + roman[posting.month - 1] + "/.YYYY."
        PY;
    }

    private function company(): string
    {
        return self::COMPANY;
    }
}
