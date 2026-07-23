<?php

namespace App\Console\Commands;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Creates the Principal doctype (and its contact person child table) in ERP HPY, plus
 * the Link field on Vessel that ties a ship to its principal.
 *
 * The same definitions are written to storage/app/erpnext/… as importable JSON, so the
 * doctypes can be reviewed, versioned or imported by hand on another instance.
 * Additive and idempotent.
 */
class SyncPrincipalDoctype extends Command
{
    protected $signature = 'erp:sync-principal-doctype {--json-only : write the JSON files without touching ERP HPY}';

    protected $description = 'Create the "Principal" doctype, its contact child table and the Vessel link field in ERP HPY';

    private const DOCTYPE = 'Principal';

    private const CHILD_DOCTYPE = 'Principal Contact Person';

    /**
     * Custom doctypes in this instance live in HR — every marine doctype they already
     * had (Vessel, Rank, Vessel Certificate) sits there, and a separate module would
     * scatter them.
     */
    private const MODULE = 'HR';

    public function handle(ErpnextClient $client): int
    {
        $this->writeJson('doctypes/principal_contact_person.json', $this->childDefinition());
        $this->writeJson('doctypes/principal.json', $this->definition());
        $this->writeJson('custom_fields/vessel_principal_field.json', $this->vesselField());

        if ($this->option('json-only')) {
            return self::SUCCESS;
        }

        $this->ensure($client, self::CHILD_DOCTYPE, $this->childDefinition());
        $this->ensure($client, self::DOCTYPE, $this->definition());
        $this->ensureVesselField($client);

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $definition */
    private function writeJson(string $path, array $definition): void
    {
        $full = storage_path('app/erpnext/' . $path);

        File::ensureDirectoryExists(dirname($full));
        File::put($full, json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        $this->line("  json: storage/app/erpnext/{$path}");
    }

    /** @param array<string, mixed> $definition */
    private function ensure(ErpnextClient $client, string $doctype, array $definition): void
    {
        if ($client->exists('DocType', $doctype)) {
            $this->line("= {$doctype} (already there)");

            return;
        }

        $client->create('DocType', $definition);
        $this->info("+ {$doctype}");
    }

    private function ensureVesselField(ErpnextClient $client): void
    {
        $document = $client->get('DocType', 'Vessel');
        $fields = $document['fields'] ?? [];

        if (collect($fields)->contains('fieldname', 'principal')) {
            $this->line('= Vessel.principal (already there)');

            return;
        }

        // Right after the identification block, where ownership belongs.
        $position = collect($fields)->search(fn ($f) => $f['fieldname'] === 'company');
        $position = $position === false ? count($fields) : $position + 1;

        array_splice($fields, $position, 0, [[
            'fieldname' => 'principal',
            'label' => 'Principal',
            'fieldtype' => 'Link',
            'options' => self::DOCTYPE,
            'in_list_view' => 1,
            'in_standard_filter' => 1,
        ]]);

        $client->update('DocType', 'Vessel', ['fields' => $fields]);
        $this->info('+ Vessel.principal (Link -> ' . self::DOCTYPE . ')');
    }

    /** @return array<string, mixed> */
    private function definition(): array
    {
        $select = fn (array $values) => implode("\n", $values);

        return [
            'doctype' => 'DocType',
            'name' => self::DOCTYPE,
            'module' => self::MODULE,
            'custom' => 1,
            'istable' => 0,
            'is_submittable' => 0,
            'track_changes' => 1,
            'allow_import' => 1,
            'allow_rename' => 1,
            'engine' => 'InnoDB',
            'autoname' => 'field:principal_code',
            'title_field' => 'principal_name',
            'search_fields' => 'principal_name,legal_entity_name,email',
            'fields' => [
                ['fieldname' => 'sb_basic', 'label' => 'Basic Info', 'fieldtype' => 'Section Break'],
                ['fieldname' => 'principal_code', 'label' => 'Principal Code', 'fieldtype' => 'Data', 'reqd' => 1, 'unique' => 1, 'in_list_view' => 1],
                ['fieldname' => 'principal_name', 'label' => 'Principal Name', 'fieldtype' => 'Data', 'reqd' => 1, 'in_list_view' => 1],
                ['fieldname' => 'legal_entity_name', 'label' => 'Legal Entity Name', 'fieldtype' => 'Data'],
                ['fieldname' => 'cb_basic', 'fieldtype' => 'Column Break'],
                ['fieldname' => 'principal_type', 'label' => 'Principal Type', 'fieldtype' => 'Select', 'reqd' => 1,
                    'in_list_view' => 1, 'in_standard_filter' => 1,
                    'options' => $select(['Shipowner', 'Ship Manager', 'Charterer', 'Operator'])],
                ['fieldname' => 'country', 'label' => 'Country', 'fieldtype' => 'Data', 'default' => 'Indonesia',
                    'in_list_view' => 1, 'in_standard_filter' => 1],
                ['fieldname' => 'status', 'label' => 'Status', 'fieldtype' => 'Select', 'default' => 'Active',
                    'in_list_view' => 1, 'in_standard_filter' => 1,
                    'options' => $select(['Prospect', 'Active', 'On Hold', 'Terminated'])],

                ['fieldname' => 'sb_contact', 'label' => 'Address & Contact', 'fieldtype' => 'Section Break'],
                ['fieldname' => 'head_office_address', 'label' => 'Head Office Address', 'fieldtype' => 'Small Text'],
                ['fieldname' => 'city', 'label' => 'City', 'fieldtype' => 'Data'],
                ['fieldname' => 'province', 'label' => 'Province', 'fieldtype' => 'Data'],
                ['fieldname' => 'postal_code', 'label' => 'Postal Code', 'fieldtype' => 'Data'],
                ['fieldname' => 'cb_contact', 'fieldtype' => 'Column Break'],
                ['fieldname' => 'phone', 'label' => 'Phone', 'fieldtype' => 'Data'],
                ['fieldname' => 'fax', 'label' => 'Fax', 'fieldtype' => 'Data'],
                ['fieldname' => 'email', 'label' => 'Email', 'fieldtype' => 'Data', 'options' => 'Email'],
                ['fieldname' => 'website', 'label' => 'Website', 'fieldtype' => 'Data'],

                ['fieldname' => 'sb_terms', 'label' => 'Business Terms', 'fieldtype' => 'Section Break'],
                ['fieldname' => 'contract_start_date', 'label' => 'Contract Start Date', 'fieldtype' => 'Date'],
                ['fieldname' => 'contract_end_date', 'label' => 'Contract End Date', 'fieldtype' => 'Date'],
                ['fieldname' => 'contract_type', 'label' => 'Contract Type', 'fieldtype' => 'Select',
                    'options' => $select(['', 'Exclusive', 'Non Exclusive'])],
                ['fieldname' => 'cb_terms', 'fieldtype' => 'Column Break'],
                ['fieldname' => 'manning_fee_type', 'label' => 'Manning Fee Type', 'fieldtype' => 'Select',
                    'options' => $select(['', 'Per Crew', 'Percentage', 'Flat Monthly'])],
                ['fieldname' => 'manning_fee_amount', 'label' => 'Manning Fee Amount', 'fieldtype' => 'Currency', 'options' => 'currency'],
                ['fieldname' => 'currency', 'label' => 'Currency', 'fieldtype' => 'Link', 'options' => 'Currency', 'default' => 'IDR'],
                ['fieldname' => 'payment_terms', 'label' => 'Payment Terms', 'fieldtype' => 'Data'],

                ['fieldname' => 'sb_compliance', 'label' => 'Compliance', 'fieldtype' => 'Section Break'],
                ['fieldname' => 'p_and_i_club', 'label' => 'P&I Club', 'fieldtype' => 'Data'],
                ['fieldname' => 'cb_compliance', 'fieldtype' => 'Column Break'],
                ['fieldname' => 'wage_scale_reference', 'label' => 'Wage Scale Reference', 'fieldtype' => 'Data'],

                ['fieldname' => 'sb_financial', 'label' => 'Financial', 'fieldtype' => 'Section Break'],
                ['fieldname' => 'billing_address', 'label' => 'Billing Address', 'fieldtype' => 'Small Text'],
                ['fieldname' => 'cb_financial', 'fieldtype' => 'Column Break'],
                ['fieldname' => 'tax_id', 'label' => 'Tax ID', 'fieldtype' => 'Data'],
                ['fieldname' => 'bank_details', 'label' => 'Bank Details', 'fieldtype' => 'Small Text'],

                ['fieldname' => 'sb_contacts', 'label' => 'Contact Persons', 'fieldtype' => 'Section Break'],
                ['fieldname' => 'contact_persons', 'label' => 'Contact Persons', 'fieldtype' => 'Table', 'options' => self::CHILD_DOCTYPE],

                ['fieldname' => 'sb_meta', 'label' => 'Notes', 'fieldtype' => 'Section Break'],
                ['fieldname' => 'notes', 'label' => 'Notes', 'fieldtype' => 'Small Text'],
            ],
            'permissions' => [
                ['role' => 'System Manager', 'read' => 1, 'write' => 1, 'create' => 1, 'delete' => 1, 'submit' => 0, 'cancel' => 0, 'amend' => 0, 'report' => 1, 'export' => 1, 'import' => 1, 'share' => 1, 'print' => 1, 'email' => 1],
                ['role' => 'HR Manager', 'read' => 1, 'write' => 1, 'create' => 1, 'delete' => 1, 'submit' => 0, 'cancel' => 0, 'amend' => 0, 'report' => 1, 'export' => 1, 'import' => 1, 'share' => 1, 'print' => 1, 'email' => 1],
                ['role' => 'HR User', 'read' => 1, 'write' => 1, 'create' => 1, 'delete' => 0, 'report' => 1, 'export' => 0, 'share' => 1, 'print' => 1, 'email' => 1],
            ],
            'states' => [
                ['title' => 'Prospect', 'color' => 'Gray'],
                ['title' => 'Active', 'color' => 'Green'],
                ['title' => 'On Hold', 'color' => 'Yellow'],
                ['title' => 'Terminated', 'color' => 'Red'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function childDefinition(): array
    {
        return [
            'doctype' => 'DocType',
            'name' => self::CHILD_DOCTYPE,
            'module' => self::MODULE,
            'custom' => 1,
            'istable' => 1,
            'editable_grid' => 1,
            'engine' => 'InnoDB',
            'fields' => [
                ['fieldname' => 'contact_name', 'label' => 'Name', 'fieldtype' => 'Data', 'reqd' => 1, 'in_list_view' => 1],
                ['fieldname' => 'position', 'label' => 'Position', 'fieldtype' => 'Data', 'in_list_view' => 1],
                ['fieldname' => 'email', 'label' => 'Email', 'fieldtype' => 'Data', 'options' => 'Email'],
                ['fieldname' => 'phone', 'label' => 'Phone', 'fieldtype' => 'Data', 'in_list_view' => 1],
                ['fieldname' => 'whatsapp', 'label' => 'WhatsApp', 'fieldtype' => 'Data'],
                ['fieldname' => 'is_primary', 'label' => 'Primary', 'fieldtype' => 'Check', 'in_list_view' => 1],
                ['fieldname' => 'notes', 'label' => 'Notes', 'fieldtype' => 'Small Text'],
            ],
            'permissions' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function vesselField(): array
    {
        return [
            'doctype' => 'Custom Field',
            'dt' => 'Vessel',
            'fieldname' => 'principal',
            'label' => 'Principal',
            'fieldtype' => 'Link',
            'options' => self::DOCTYPE,
            'insert_after' => 'company',
            'in_list_view' => 1,
            'in_standard_filter' => 1,
            'module' => self::MODULE,
        ];
    }
}
