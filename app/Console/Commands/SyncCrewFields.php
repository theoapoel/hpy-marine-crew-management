<?php

namespace App\Console\Commands;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Console\Command;

/**
 * Crew Master keeps marine-specific data on the Employee Doctype: crew status,
 * nationality, seaman book, rank, vessel and a certificate table (modelled on the
 * instance's own "Vessel Certificate" child table, attachment included).
 *
 * ERP HPY silently drops unknown fields on write and rejects them in queries, so all
 * of this must exist before Crew Master can be used. Additive and idempotent.
 */
class SyncCrewFields extends Command
{
    protected $signature = 'erp:sync-crew-fields';

    protected $description = 'Create the Employee custom fields and certificate table Crew Master relies on';

    private const CERT_DOCTYPE = 'Employee Certificate';

    /** Document types crew carry; the Documents menu is built from this list. */
    public const CERTIFICATE_TYPES = [
        'Passport', 'Seaman Book', 'Medical Certificate', 'Certificate of Competency',
        'Certificate of Proficiency', 'Basic Safety Training', 'Advanced Fire Fighting',
        'Survival Craft & Rescue Boat', 'Medical First Aid', 'GMDSS / Radio',
        'Yellow Fever', 'Visa', 'Training Certificate', 'Other',
    ];

    public const CERTIFICATE_STATUSES = ['Valid', 'Expiring Soon', 'Expired', 'Revoked'];

    /** @var array<int, array<string, mixed>> */
    private const FIELDS = [
        ['fieldname' => 'custom_crew_status', 'label' => 'Crew Status', 'fieldtype' => 'Select', 'options' => "\nOnboard\nStandby\nSign Off"],
        ['fieldname' => 'custom_nationality', 'label' => 'Nationality', 'fieldtype' => 'Data'],
        ['fieldname' => 'custom_rank', 'label' => 'Rank', 'fieldtype' => 'Link', 'options' => 'Rank'],
        ['fieldname' => 'custom_seaman_book_no', 'label' => 'Seaman Book No.', 'fieldtype' => 'Data'],
        ['fieldname' => 'custom_seaman_book_expiry', 'label' => 'Seaman Book Expiry', 'fieldtype' => 'Date'],
        ['fieldname' => 'custom_vessel', 'label' => 'Vessel', 'fieldtype' => 'Link', 'options' => 'Vessel'],
        ['fieldname' => 'custom_sign_on_date', 'label' => 'Sign On Date', 'fieldtype' => 'Date'],
        ['fieldname' => 'custom_contract_end_date', 'label' => 'Contract End Date', 'fieldtype' => 'Date'],
        ['fieldname' => 'custom_certificates', 'label' => 'Certificates', 'fieldtype' => 'Table', 'options' => self::CERT_DOCTYPE],
    ];

    public function handle(ErpnextClient $client): int
    {
        $doctype = (string) config('services.erpnext.crew_doctype', 'Employee');

        $this->ensureCertificateDoctype($client);
        $this->ensureCustomFields($client, $doctype);

        return self::SUCCESS;
    }

    /** The child table holding one certificate per row, with its own file attachment. */
    private function ensureCertificateDoctype(ErpnextClient $client): void
    {
        if ($client->exists('DocType', self::CERT_DOCTYPE)) {
            $this->line('= ' . self::CERT_DOCTYPE . ' (already there)');

            return;
        }

        $client->create('DocType', [
            'name' => self::CERT_DOCTYPE,
            'module' => 'HR',
            'custom' => 1,
            'istable' => 1,
            'editable_grid' => 1,
            'fields' => [
                ['fieldname' => 'certificate_type', 'label' => 'Certificate Type', 'fieldtype' => 'Select', 'reqd' => 1, 'in_list_view' => 1, 'options' => implode("\n", self::CERTIFICATE_TYPES)],
                ['fieldname' => 'certificate_number', 'label' => 'Certificate Number', 'fieldtype' => 'Data', 'in_list_view' => 1],
                ['fieldname' => 'issued_by', 'label' => 'Issued By', 'fieldtype' => 'Data'],
                ['fieldname' => 'issue_date', 'label' => 'Issue Date', 'fieldtype' => 'Date'],
                ['fieldname' => 'expiry_date', 'label' => 'Expiry Date', 'fieldtype' => 'Date', 'in_list_view' => 1],
                ['fieldname' => 'status', 'label' => 'Status', 'fieldtype' => 'Select', 'options' => implode("\n", self::CERTIFICATE_STATUSES)],
                ['fieldname' => 'attachment', 'label' => 'Attachment', 'fieldtype' => 'Attach', 'in_list_view' => 1],
                ['fieldname' => 'remarks', 'label' => 'Remarks', 'fieldtype' => 'Small Text'],
            ],
            'permissions' => [],
        ]);

        $this->info('+ ' . self::CERT_DOCTYPE . ' (child table)');
    }

    private function ensureCustomFields(ErpnextClient $client, string $doctype): void
    {
        $existing = collect($client->list('Custom Field', ['name', 'fieldname', 'fieldtype'], [['dt', '=', $doctype]], 500))
            ->keyBy('fieldname');

        foreach (self::FIELDS as $field) {
            $current = $existing->get($field['fieldname']);

            if (! $current) {
                $client->create('Custom Field', $field + ['dt' => $doctype, 'insert_after' => 'designation']);
                $this->info("+ {$field['fieldname']}");

                continue;
            }

            // An earlier run may have created it as a plain Data field. ERP HPY refuses
            // to change a fieldtype in place, so the field is replaced.
            if ($current['fieldtype'] !== $field['fieldtype']) {
                $client->delete('Custom Field', $current['name']);
                $client->create('Custom Field', $field + ['dt' => $doctype, 'insert_after' => 'designation']);
                $this->info("~ {$field['fieldname']} ({$current['fieldtype']} -> {$field['fieldtype']}, recreated)");

                continue;
            }

            $this->line("= {$field['fieldname']} (already there)");
        }
    }
}
