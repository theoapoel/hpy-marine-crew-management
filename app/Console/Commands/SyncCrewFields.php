<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ManagesErpDoctypes;
use App\Services\Erpnext\ErpnextClient;
use Illuminate\Console\Command;

/**
 * Crew Master keeps marine-specific data on the Employee Doctype: crew status,
 * nationality, seaman book, rank, vessel, a certificate table (modelled on the
 * instance's own "Vessel Certificate" child table, attachment included), a bank
 * account table and the last salary.
 *
 * Certificate types are master data ("Crew Certificate Type"), not a hardcoded
 * Select: add one in ERP HPY or from the crew form and every dropdown offers it.
 *
 * ERP HPY silently drops unknown fields on write and rejects them in queries, so all
 * of this must exist before Crew Master can be used. Additive and idempotent.
 */
class SyncCrewFields extends Command
{
    use ManagesErpDoctypes;

    protected $signature = 'erp:sync-crew-fields';

    protected $description = 'Create the Employee custom fields and certificate table Crew Master relies on';

    private const CERT_DOCTYPE = 'Employee Certificate';

    /** Master list of certificate types; Employee Certificate.certificate_type links here. */
    public const CERT_TYPE_DOCTYPE = 'Crew Certificate Type';

    private const BANK_DOCTYPE = 'Employee Bank Account';

    /**
     * The certificate types a fresh instance starts with — the seed for the master,
     * no longer the list the app offers (that is read from ERP HPY). Passport and
     * Seaman Book must stay: the Documents menu has a page for each.
     */
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
        ['fieldname' => 'custom_bank_accounts', 'label' => 'Bank Accounts', 'fieldtype' => 'Table', 'options' => self::BANK_DOCTYPE],
        ['fieldname' => 'custom_last_salary_currency', 'label' => 'Last Salary Currency', 'fieldtype' => 'Link', 'options' => 'Currency'],
        ['fieldname' => 'custom_last_salary', 'label' => 'Last Salary', 'fieldtype' => 'Currency', 'options' => 'custom_last_salary_currency'],
    ];

    /** Category per seeded certificate type, for the master's list view. */
    private const CERTIFICATE_CATEGORIES = [
        'Passport' => 'Identity', 'Seaman Book' => 'Identity', 'Visa' => 'Identity',
        'Medical Certificate' => 'Medical', 'Yellow Fever' => 'Medical',
        'Certificate of Competency' => 'Competency', 'GMDSS / Radio' => 'Competency',
        'Certificate of Proficiency' => 'Proficiency', 'Basic Safety Training' => 'Proficiency',
        'Advanced Fire Fighting' => 'Proficiency', 'Survival Craft & Rescue Boat' => 'Proficiency',
        'Medical First Aid' => 'Proficiency', 'Training Certificate' => 'Training',
    ];

    public function handle(ErpnextClient $client): int
    {
        $doctype = (string) config('services.erpnext.crew_doctype', 'Employee');

        $this->ensureCertificateTypes($client);
        $this->ensureCertificateDoctype($client);
        $this->repoint($client, self::CERT_DOCTYPE, 'certificate_type', self::CERT_TYPE_DOCTYPE);
        $this->ensureBankDoctype($client);
        $this->ensureCustomFields($client, $doctype);

        return self::SUCCESS;
    }

    /** The master behind the certificate type dropdown, seeded with the old fixed list. */
    private function ensureCertificateTypes(ErpnextClient $client): void
    {
        $this->ensureDoctype($client, self::CERT_TYPE_DOCTYPE, 'certificate_name', [
            ['fieldname' => 'certificate_name', 'label' => 'Certificate Type', 'fieldtype' => 'Data', 'reqd' => 1, 'unique' => 1, 'in_list_view' => 1],
            ['fieldname' => 'category', 'label' => 'Category', 'fieldtype' => 'Select', 'in_list_view' => 1,
                'options' => "\nIdentity\nCompetency\nProficiency\nMedical\nTraining\nOther"],
            ['fieldname' => 'validity_months', 'label' => 'Validity (months)', 'fieldtype' => 'Int'],
            ['fieldname' => 'is_active', 'label' => 'Active', 'fieldtype' => 'Check', 'default' => '1'],
        ], usersMayAdd: true);

        $rows = [];
        foreach (self::CERTIFICATE_TYPES as $type) {
            $rows[$type] = ['certificate_name' => $type, 'category' => self::CERTIFICATE_CATEGORIES[$type] ?? 'Other', 'is_active' => 1];
        }

        $this->seed($client, self::CERT_TYPE_DOCTYPE, $rows);
    }

    /** One row per bank account; the first is mirrored to Employee.bank_name / bank_ac_no for payroll. */
    private function ensureBankDoctype(ErpnextClient $client): void
    {
        if ($client->exists('DocType', self::BANK_DOCTYPE)) {
            $this->line('= ' . self::BANK_DOCTYPE . ' (already there)');

            return;
        }

        $client->create('DocType', [
            'name' => self::BANK_DOCTYPE,
            'module' => 'HR',
            'custom' => 1,
            'istable' => 1,
            'editable_grid' => 1,
            'fields' => [
                ['fieldname' => 'bank_name', 'label' => 'Bank Name', 'fieldtype' => 'Data', 'reqd' => 1, 'in_list_view' => 1],
                ['fieldname' => 'account_holder', 'label' => 'Account Holder', 'fieldtype' => 'Data', 'in_list_view' => 1],
                ['fieldname' => 'account_number', 'label' => 'Account Number', 'fieldtype' => 'Data', 'in_list_view' => 1],
                ['fieldname' => 'bank_address', 'label' => 'Bank Address', 'fieldtype' => 'Small Text'],
            ],
            'permissions' => [],
        ]);

        $this->info('+ ' . self::BANK_DOCTYPE . ' (child table)');
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
                ['fieldname' => 'certificate_type', 'label' => 'Certificate Type', 'fieldtype' => 'Link', 'reqd' => 1, 'in_list_view' => 1, 'options' => self::CERT_TYPE_DOCTYPE],
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
