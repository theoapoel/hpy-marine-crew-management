<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ManagesErpDoctypes;
use App\Models\CrewCandidate;
use App\Services\Erpnext\ErpnextClient;
use App\Support\CocTypes;
use Illuminate\Console\Command;

/**
 * Mirrors the Candidate Pool as a custom Doctype in ERP HPY (module HR), so crewing
 * staff can also see and edit candidates inside ERP HPY itself. Additive and
 * idempotent — safe to run again after adding fields here.
 *
 * COC types are master data ("COC Type"), seeded from the list the app shipped with;
 * Crew Candidate.coc_type links to it, so new ones are added as records.
 */
class SyncCandidateDoctype extends Command
{
    use ManagesErpDoctypes;

    protected $signature = 'erp:sync-candidate-doctype';

    protected $description = 'Create the "Crew Candidate" custom Doctype (module HR) in ERP HPY';

    private const DOCTYPE = 'Crew Candidate';

    private const COP_DOCTYPE = 'Crew Candidate COP';

    public function handle(ErpnextClient $client): int
    {
        $this->ensureCocTypes($client);

        $this->ensure($client, self::COP_DOCTYPE, [
            'istable' => 1,
            'fields' => [
                ['fieldname' => 'certificate_name', 'label' => 'Certificate', 'fieldtype' => 'Data', 'reqd' => 1, 'in_list_view' => 1],
                ['fieldname' => 'certificate_number', 'label' => 'Number', 'fieldtype' => 'Data', 'in_list_view' => 1],
                ['fieldname' => 'expiry_date', 'label' => 'Expiry', 'fieldtype' => 'Date', 'in_list_view' => 1],
            ],
        ]);

        $this->ensure($client, self::DOCTYPE, [
            'autoname' => 'field:candidate_code',
            'title_field' => 'full_name',
            'fields' => $this->fields(),
            'permissions' => [
                ['role' => 'HR Manager', 'read' => 1, 'write' => 1, 'create' => 1, 'delete' => 1, 'report' => 1, 'export' => 1],
                ['role' => 'HR User', 'read' => 1, 'write' => 1, 'create' => 1, 'report' => 1],
            ],
        ]);

        // An instance set up before the master existed still has a Select here.
        $this->repoint($client, self::DOCTYPE, 'coc_type', CocTypes::DOCTYPE);

        return self::SUCCESS;
    }

    /** The master behind the COC dropdown, seeded with the old fixed list. */
    private function ensureCocTypes(ErpnextClient $client): void
    {
        $this->ensureDoctype($client, CocTypes::DOCTYPE, 'coc_type_name', [
            ['fieldname' => 'coc_type_name', 'label' => 'COC Type', 'fieldtype' => 'Data', 'reqd' => 1, 'unique' => 1, 'in_list_view' => 1],
            ['fieldname' => 'department', 'label' => 'Department', 'fieldtype' => 'Select', 'in_list_view' => 1,
                'options' => "\nDeck\nEngine\nRating\nOther"],
            ['fieldname' => 'is_active', 'label' => 'Active', 'fieldtype' => 'Check', 'default' => '1'],
            ['fieldname' => 'description', 'label' => 'Description', 'fieldtype' => 'Small Text'],
        ], usersMayAdd: true);

        $rows = [];
        foreach (CrewCandidate::COC_TYPES as $type) {
            $rows[$type] = ['coc_type_name' => $type, 'is_active' => 1, 'department' => match (true) {
                str_starts_with($type, 'ANT') => 'Deck',
                str_starts_with($type, 'ATT') => 'Engine',
                $type === 'Rating' => 'Rating',
                default => 'Other',
            }];
        }

        $this->seed($client, CocTypes::DOCTYPE, $rows);
    }

    /** @param array<string, mixed> $definition */
    private function ensure(ErpnextClient $client, string $doctype, array $definition): void
    {
        if ($client->exists('DocType', $doctype)) {
            $this->line("= {$doctype} (already there)");

            return;
        }

        $client->create('DocType', array_merge([
            'name' => $doctype,
            'module' => 'HR',
            'custom' => 1,
            'editable_grid' => 1,
        ], $definition));

        $this->info("+ {$doctype}");
    }

    /**
     * The same shape as the crew_candidates table, expressed as ERP HPY fields.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fields(): array
    {
        $select = fn (array $values) => implode("\n", $values);

        return [
            ['fieldname' => 'sec_personal', 'label' => 'Personal', 'fieldtype' => 'Section Break'],
            ['fieldname' => 'candidate_code', 'label' => 'Candidate Code', 'fieldtype' => 'Data', 'reqd' => 1, 'unique' => 1, 'in_list_view' => 1],
            ['fieldname' => 'full_name', 'label' => 'Full Name', 'fieldtype' => 'Data', 'reqd' => 1, 'in_list_view' => 1],
            ['fieldname' => 'first_name', 'label' => 'First Name', 'fieldtype' => 'Data'],
            ['fieldname' => 'last_name', 'label' => 'Last Name', 'fieldtype' => 'Data'],
            ['fieldname' => 'cb_personal', 'fieldtype' => 'Column Break'],
            ['fieldname' => 'gender', 'label' => 'Gender', 'fieldtype' => 'Select', 'options' => $select(['', 'Male', 'Female'])],
            ['fieldname' => 'date_of_birth', 'label' => 'Date of Birth', 'fieldtype' => 'Date'],
            ['fieldname' => 'place_of_birth', 'label' => 'Place of Birth', 'fieldtype' => 'Data'],
            ['fieldname' => 'nationality', 'label' => 'Nationality', 'fieldtype' => 'Data', 'default' => 'Indonesia'],
            ['fieldname' => 'marital_status', 'label' => 'Marital Status', 'fieldtype' => 'Select', 'options' => $select(['', 'Single', 'Married', 'Divorced', 'Widowed'])],
            ['fieldname' => 'religion', 'label' => 'Religion', 'fieldtype' => 'Data'],
            ['fieldname' => 'blood_type', 'label' => 'Blood Type', 'fieldtype' => 'Data'],

            ['fieldname' => 'sec_identity', 'label' => 'Identity', 'fieldtype' => 'Section Break'],
            ['fieldname' => 'nik', 'label' => 'NIK (KTP)', 'fieldtype' => 'Data'],
            ['fieldname' => 'passport_no', 'label' => 'Passport No', 'fieldtype' => 'Data'],
            ['fieldname' => 'passport_expiry', 'label' => 'Passport Expiry', 'fieldtype' => 'Date'],
            ['fieldname' => 'passport_issue_place', 'label' => 'Passport Issue Place', 'fieldtype' => 'Data'],
            ['fieldname' => 'cb_identity', 'fieldtype' => 'Column Break'],
            ['fieldname' => 'seaman_book_no', 'label' => 'Seaman Book No', 'fieldtype' => 'Data', 'in_list_view' => 1],
            ['fieldname' => 'seaman_book_expiry', 'label' => 'Seaman Book Expiry', 'fieldtype' => 'Date'],
            ['fieldname' => 'seaman_book_issue_place', 'label' => 'Seaman Book Issue Place', 'fieldtype' => 'Data'],

            ['fieldname' => 'sec_contact', 'label' => 'Contact', 'fieldtype' => 'Section Break'],
            ['fieldname' => 'phone', 'label' => 'Phone', 'fieldtype' => 'Data', 'reqd' => 1],
            ['fieldname' => 'whatsapp', 'label' => 'WhatsApp', 'fieldtype' => 'Data'],
            ['fieldname' => 'email', 'label' => 'Email', 'fieldtype' => 'Data', 'options' => 'Email'],
            ['fieldname' => 'cb_contact', 'fieldtype' => 'Column Break'],
            ['fieldname' => 'address', 'label' => 'Address', 'fieldtype' => 'Small Text'],
            ['fieldname' => 'city', 'label' => 'City', 'fieldtype' => 'Data'],
            ['fieldname' => 'province', 'label' => 'Province', 'fieldtype' => 'Data'],
            ['fieldname' => 'postal_code', 'label' => 'Postal Code', 'fieldtype' => 'Data'],
            ['fieldname' => 'sec_emergency', 'label' => 'Emergency Contact', 'fieldtype' => 'Section Break'],
            ['fieldname' => 'emergency_contact_name', 'label' => 'Name', 'fieldtype' => 'Data'],
            ['fieldname' => 'emergency_contact_relation', 'label' => 'Relation', 'fieldtype' => 'Data'],
            ['fieldname' => 'emergency_contact_phone', 'label' => 'Phone', 'fieldtype' => 'Data'],

            ['fieldname' => 'sec_professional', 'label' => 'Professional', 'fieldtype' => 'Section Break'],
            ['fieldname' => 'applied_rank', 'label' => 'Applied Rank', 'fieldtype' => 'Link', 'options' => 'Rank', 'in_list_view' => 1],
            ['fieldname' => 'preferred_vessel_type', 'label' => 'Preferred Vessel Type', 'fieldtype' => 'Data'],
            ['fieldname' => 'years_of_experience', 'label' => 'Years of Experience', 'fieldtype' => 'Int'],
            ['fieldname' => 'cb_professional', 'fieldtype' => 'Column Break'],
            ['fieldname' => 'last_vessel_name', 'label' => 'Last Vessel', 'fieldtype' => 'Data'],
            ['fieldname' => 'last_rank', 'label' => 'Last Rank', 'fieldtype' => 'Data'],
            ['fieldname' => 'last_sign_off_date', 'label' => 'Last Sign Off', 'fieldtype' => 'Date'],

            ['fieldname' => 'sec_certification', 'label' => 'Certification', 'fieldtype' => 'Section Break'],
            ['fieldname' => 'coc_type', 'label' => 'COC Type', 'fieldtype' => 'Link', 'options' => CocTypes::DOCTYPE, 'in_list_view' => 1],
            ['fieldname' => 'coc_number', 'label' => 'COC Number', 'fieldtype' => 'Data'],
            ['fieldname' => 'coc_expiry', 'label' => 'COC Expiry', 'fieldtype' => 'Date'],
            ['fieldname' => 'cb_certification', 'fieldtype' => 'Column Break'],
            ['fieldname' => 'mcu_expiry', 'label' => 'MCU Expiry', 'fieldtype' => 'Date'],
            ['fieldname' => 'endc_expiry', 'label' => 'ENDC Expiry', 'fieldtype' => 'Date'],
            ['fieldname' => 'sec_cop', 'label' => 'COP Certificates', 'fieldtype' => 'Section Break'],
            ['fieldname' => 'cop_certificates', 'label' => 'COP Certificates', 'fieldtype' => 'Table', 'options' => self::COP_DOCTYPE],

            ['fieldname' => 'sec_source', 'label' => 'Source', 'fieldtype' => 'Section Break'],
            ['fieldname' => 'source', 'label' => 'Source', 'fieldtype' => 'Select', 'reqd' => 1, 'options' => $select(array_map(
                fn ($s) => ucwords(str_replace('_', ' ', $s)),
                CrewCandidate::SOURCES,
            ))],
            ['fieldname' => 'source_detail', 'label' => 'Source Detail', 'fieldtype' => 'Data'],
            ['fieldname' => 'referred_by_employee', 'label' => 'Referred By', 'fieldtype' => 'Link', 'options' => 'Employee', 'depends_on' => 'eval:doc.source=="Referral"'],
            ['fieldname' => 'cb_source', 'fieldtype' => 'Column Break'],
            ['fieldname' => 'source_agency', 'label' => 'Agency', 'fieldtype' => 'Link', 'options' => 'Supplier', 'depends_on' => 'eval:doc.source=="Agency"'],
            ['fieldname' => 'source_school', 'label' => 'School', 'fieldtype' => 'Data', 'depends_on' => 'eval:doc.source=="School"'],
            ['fieldname' => 'source_cost', 'label' => 'Recruitment Cost', 'fieldtype' => 'Currency', 'depends_on' => 'eval:["Agency","Head Hunter","Job Board"].includes(doc.source)'],

            ['fieldname' => 'sec_status', 'label' => 'Status', 'fieldtype' => 'Section Break'],
            ['fieldname' => 'status', 'label' => 'Status', 'fieldtype' => 'Select', 'reqd' => 1, 'in_list_view' => 1, 'options' => $select(array_map(
                fn ($s) => ucwords(str_replace('_', ' ', $s)),
                CrewCandidate::STATUSES,
            ))],
            ['fieldname' => 'availability_date', 'label' => 'Availability Date', 'fieldtype' => 'Date'],
            ['fieldname' => 'cb_status', 'fieldtype' => 'Column Break'],
            ['fieldname' => 'expected_salary', 'label' => 'Expected Salary', 'fieldtype' => 'Currency'],
            ['fieldname' => 'linked_employee', 'label' => 'Linked Employee', 'fieldtype' => 'Link', 'options' => 'Employee', 'read_only' => 1],

            ['fieldname' => 'sec_attachments', 'label' => 'Attachments', 'fieldtype' => 'Section Break'],
            ['fieldname' => 'photo', 'label' => 'Photo', 'fieldtype' => 'Attach Image'],
            ['fieldname' => 'cv', 'label' => 'CV', 'fieldtype' => 'Attach'],
            ['fieldname' => 'id_scan', 'label' => 'ID Scan', 'fieldtype' => 'Attach'],
            ['fieldname' => 'cb_attachments', 'fieldtype' => 'Column Break'],
            ['fieldname' => 'seaman_book_scan', 'label' => 'Seaman Book Scan', 'fieldtype' => 'Attach'],
            ['fieldname' => 'coc_scan', 'label' => 'COC Scan', 'fieldtype' => 'Attach'],

            ['fieldname' => 'sec_notes', 'label' => 'Notes', 'fieldtype' => 'Section Break'],
            ['fieldname' => 'notes', 'label' => 'Notes', 'fieldtype' => 'Text'],
        ];
    }
}
