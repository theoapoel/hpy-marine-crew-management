<?php

namespace App\Console\Commands;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Console\Command;

/**
 * Reproduces KBM's Word manning invoice as a Sales Invoice print format in ERP HPY.
 *
 * The layout needs four things a stock Sales Invoice has nowhere to put — which
 * vessel is being billed, whether the charges are for crew onboard or for a joiner,
 * who the letter is addressed to, and the crew the invoice covers. Those become
 * custom fields plus a child table, so the printed sheet is rendered from the
 * document rather than retyped in Word.
 *
 * The company letterhead is the scanned "kop surat": its top band and bottom band
 * become the Letter Head header and footer, and the signature block is embedded in
 * the format itself.
 *
 * Idempotent: anything already present is left alone.
 */
class SeedInvoicePrintFormat extends Command
{
    protected $signature = 'erp:seed-invoice-print-format
                            {--dry-run : show what would be written, write nothing}
                            {--force-html : rewrite the print format html even when it exists}';

    protected $description = 'Create the KBM manning invoice print format, letter head and its Sales Invoice fields';

    private const FORMAT = 'KBM Manning Invoice';

    private const LETTER_HEAD = 'Keenindo Bintas Marine';

    private const CREW_TABLE = 'Sales Invoice Crew';

    private const COMPANY = 'Keenindo Bintas Marine';

    public function handle(ErpnextClient $client): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->crewTable($client, $dry);
        $this->customFields($client, $dry);
        $this->letterHead($client, $dry);
        $this->printFormat($client, $dry);

        if ($dry) {
            $this->newLine();
            $this->comment('Dry run: nothing was written to ERP HPY.');
        }

        return self::SUCCESS;
    }

    /** The crew a given invoice covers, snapshotted on the invoice itself. */
    private function crewTable(ErpnextClient $client, bool $dry): void
    {
        if ($client->exists('DocType', self::CREW_TABLE)) {
            $this->line('= DocType ' . self::CREW_TABLE);

            return;
        }

        if (! $dry) {
            $client->create('DocType', [
                'name' => self::CREW_TABLE,
                'module' => 'HR',
                'custom' => 1,
                'istable' => 1,
                'editable_grid' => 1,
                'fields' => [
                    ['fieldname' => 'crew', 'label' => 'Crew', 'fieldtype' => 'Link', 'options' => 'Employee', 'in_list_view' => 1, 'columns' => 3],
                    ['fieldname' => 'crew_name', 'label' => 'Name', 'fieldtype' => 'Data', 'in_list_view' => 1, 'columns' => 5, 'reqd' => 1,
                        'fetch_from' => 'crew.employee_name'],
                    ['fieldname' => 'rank', 'label' => 'Rank', 'fieldtype' => 'Data', 'in_list_view' => 1, 'columns' => 3,
                        'fetch_from' => 'crew.custom_rank'],
                ],
                'permissions' => [],
            ]);
        }

        $this->info('+ DocType ' . self::CREW_TABLE . ' (child table)');
    }

    /** @return array<int, array<string, mixed>> */
    private function fields(): array
    {
        return [
            [
                'fieldname' => 'custom_vessel',
                'label' => 'Vessel',
                'fieldtype' => 'Link',
                'options' => 'Vessel',
                'insert_after' => 'customer_name',
                'description' => 'Printed as "To.: Owner Of <vessel>".',
            ],
            [
                'fieldname' => 'custom_invoice_scope',
                'label' => 'Invoice Scope',
                'fieldtype' => 'Select',
                'options' => "Onboard Crews\nOn Signer",
                'default' => 'Onboard Crews',
                'insert_after' => 'custom_vessel',
                'description' => 'Onboard Crews bills the monthly manning fee; On Signer bills the cost of a joining crew.',
            ],
            [
                'fieldname' => 'custom_attn',
                'label' => 'Attention',
                'fieldtype' => 'Data',
                'default' => 'Finance Department',
                'insert_after' => 'custom_invoice_scope',
            ],
            [
                'fieldname' => 'custom_cc',
                'label' => 'Cc',
                'fieldtype' => 'Data',
                'insert_after' => 'custom_attn',
            ],
            [
                'fieldname' => 'custom_crew_list',
                'label' => 'Crew Covered',
                'fieldtype' => 'Table',
                'options' => self::CREW_TABLE,
                'insert_after' => 'items',
                'description' => 'The crew this invoice covers, listed above the charges on the printed sheet.',
            ],
        ];
    }

    private function customFields(ErpnextClient $client, bool $dry): void
    {
        foreach ($this->fields() as $field) {
            $name = 'Sales Invoice-' . $field['fieldname'];

            if ($client->exists('Custom Field', $name)) {
                $this->line("= Custom Field {$field['fieldname']}");

                continue;
            }

            if (! $dry) {
                $client->create('Custom Field', ['dt' => 'Sales Invoice'] + $field);
            }

            $this->info("+ Custom Field {$field['fieldname']} ({$field['fieldtype']})");
        }
    }

    /**
     * Header and footer bands cropped out of the scanned letterhead. Uploading them
     * as public files keeps them reachable from the generated PDF.
     */
    private function letterHead(ErpnextClient $client, bool $dry): void
    {
        if ($client->exists('Letter Head', self::LETTER_HEAD)) {
            $this->line('= Letter Head ' . self::LETTER_HEAD);

            return;
        }

        if ($dry) {
            $this->info('+ Letter Head ' . self::LETTER_HEAD . ' (header + footer band)');

            return;
        }

        // Created empty first: a file can only be uploaded against a document that exists.
        $client->create('Letter Head', [
            'letter_head_name' => self::LETTER_HEAD,
            'source' => 'HTML',
            'footer_source' => 'HTML',
            'disabled' => 0,
        ]);

        $header = $this->upload($client, 'kbm-letterhead-header.png', 'Letter Head', self::LETTER_HEAD);
        $footer = $this->upload($client, 'kbm-letterhead-footer.png', 'Letter Head', self::LETTER_HEAD);

        $client->update('Letter Head', self::LETTER_HEAD, [
            'content' => '<div style="text-align:center"><img src="' . $header . '" style="width:100%"></div>',
            'footer' => '<div style="text-align:center"><img src="' . $footer . '" style="width:100%"></div>',
        ]);

        $this->info('+ Letter Head ' . self::LETTER_HEAD);

        $client->update('Company', self::COMPANY, ['default_letter_head' => self::LETTER_HEAD]);
        $this->info('  set as default letter head for ' . self::COMPANY);
    }

    private function printFormat(ErpnextClient $client, bool $dry): void
    {
        $exists = $client->exists('Print Format', self::FORMAT);

        if ($exists && ! $this->option('force-html')) {
            $this->line('= Print Format ' . self::FORMAT . ' (use --force-html to rewrite)');

            return;
        }

        if ($dry) {
            $this->info(($exists ? '~ ' : '+ ') . 'Print Format ' . self::FORMAT);

            return;
        }

        $payload = [
            'doc_type' => 'Sales Invoice',
            'module' => 'HR',
            'standard' => 'No',
            'custom_format' => 1,
            'print_format_type' => 'Jinja',
            'disabled' => 0,
            'font_size' => 11,
            'margin_top' => 45,
            'margin_bottom' => 30,
            'margin_left' => 15,
            'margin_right' => 15,
            'default_print_language' => 'en',
        ];

        $template = $this->template();

        if (! $exists) {
            // Created with the html already in place: Print Format will not accept a
            // blank one. The signature is filled in on the update below.
            $client->create('Print Format', ['name' => self::FORMAT] + $payload + [
                'html' => str_replace('__SIGNATURE_URL__', '', $template),
            ]);
            $this->info('+ Print Format ' . self::FORMAT);
        }

        // The signature is an image, so it can only be embedded once the format it
        // hangs off exists and the file has somewhere to attach.
        $signature = $this->signatureUrl($client);

        $client->update('Print Format', self::FORMAT, $payload + [
            'html' => str_replace('__SIGNATURE_URL__', $signature, $template),
        ]);

        $this->info('  html written' . ($exists ? ' (rewritten)' : ''));
    }

    /** Reuse the signature already uploaded, so reruns do not pile up copies. */
    private function signatureUrl(ErpnextClient $client): string
    {
        $existing = $client->list('File', ['file_url'], [
            ['attached_to_doctype', '=', 'Print Format'],
            ['attached_to_name', '=', self::FORMAT],
            ['file_name', 'like', 'kbm-signature%'],
        ], 1);

        return $existing[0]['file_url']
            ?? $this->upload($client, 'kbm-signature.png', 'Print Format', self::FORMAT);
    }

    private function upload(ErpnextClient $client, string $file, string $doctype, string $docname): string
    {
        $path = resource_path('brand/' . $file);

        if (! is_file($path)) {
            throw new \RuntimeException("Missing brand asset: {$path}");
        }

        $url = $client->upload(file_get_contents($path), $file, $doctype, $docname, private: false);
        $this->line("  uploaded {$file} -> {$url}");

        return $url;
    }

    private function template(): string
    {
        $path = resource_path('print-formats/kbm-manning-invoice.html');

        if (! is_file($path)) {
            throw new \RuntimeException("Missing print format template: {$path}");
        }

        return file_get_contents($path);
    }
}
