<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ManagesErpDoctypes;
use App\Services\Erpnext\ErpnextClient;
use Illuminate\Console\Command;

/**
 * Vessel types and vessel certificate types were hardcoded Select options, so adding
 * one meant editing a doctype. This gives each its own doctype, seeds the list from
 * what was there, and re-points the fields at them — after which the app and ERP HPY
 * read the same master data. Additive and idempotent.
 */
class SyncVesselTypes extends Command
{
    use ManagesErpDoctypes;

    protected $signature = 'erp:sync-vessel-types';

    protected $description = 'Create the "Vessel Type" and "Vessel Certificate Type" doctypes in ERP HPY, seed them, and link the fields';

    private const TYPE_DOCTYPE = 'Vessel Type';

    private const CERTIFICATE_DOCTYPE = 'Vessel Certificate Type';

    /** name => [code, category, description] */
    private const TYPES = [
        'Tanker' => ['TNK', 'Liquid Cargo', 'Oil, chemical and product tankers'],
        'LNG Carrier' => ['LNG', 'Liquid Cargo', 'Liquefied natural gas carrier'],
        'Bulk Carrier' => ['BLK', 'Dry Cargo', 'Loose dry cargo in bulk'],
        'General Cargo' => ['GEN', 'Dry Cargo', 'Break bulk and general cargo'],
        'Container' => ['CNT', 'Dry Cargo', 'Container feeder and liner vessels'],
        'Barge' => ['BRG', 'Dry Cargo', 'Unpowered cargo barge, usually towed'],
        'RoRo' => ['RRO', 'Dry Cargo', 'Roll-on / roll-off vessel'],
        'Tugboat' => ['TUG', 'Support', 'Harbour, towing and escort tugs'],
        'AHTS' => ['AHT', 'Offshore', 'Anchor handling tug supply vessel'],
        'PSV' => ['PSV', 'Offshore', 'Platform supply vessel'],
        'Crew Boat' => ['CRB', 'Offshore', 'Fast crew transfer vessel'],
        'Passenger' => ['PAX', 'Passenger', 'Passenger and ferry vessels'],
        'Other' => ['OTH', 'Other', 'Anything not covered above'],
    ];

    /** name => [category, issued by, renewal months] */
    private const CERTIFICATE_TYPES = [
        'Safety Construction' => ['Statutory', 'Flag State / RO', 60],
        'Safety Equipment' => ['Statutory', 'Flag State / RO', 60],
        'Safety Radio' => ['Statutory', 'Flag State / RO', 60],
        'Load Line' => ['Statutory', 'Flag State / RO', 60],
        'Tonnage' => ['Statutory', 'Flag State', 0],
        'Minimum Safe Manning' => ['Statutory', 'Flag State', 60],
        'Radio License' => ['Statutory', 'Telecom Authority', 12],
        'IOPP (MARPOL Annex I)' => ['MARPOL', 'Flag State / RO', 60],
        'ISPP (MARPOL Annex IV)' => ['MARPOL', 'Flag State / RO', 60],
        'IAPP (MARPOL Annex VI)' => ['MARPOL', 'Flag State / RO', 60],
        'Garbage (MARPOL Annex V)' => ['MARPOL', 'Flag State / RO', 60],
        'Anti-Fouling' => ['MARPOL', 'Flag State / RO', 60],
        'Ballast Water Management' => ['MARPOL', 'Flag State / RO', 60],
        'ISM - DOC' => ['Management', 'Flag State / RO', 60],
        'ISM - SMC' => ['Management', 'Flag State / RO', 60],
        'ISPS - ISSC' => ['Management', 'Flag State / RO', 60],
        'MLC 2006' => ['Management', 'Flag State / RO', 60],
        'Class Certificate' => ['Class', 'Classification Society', 60],
        'P&I Insurance' => ['Insurance', 'P&I Club', 12],
        'H&M Insurance' => ['Insurance', 'Insurer', 12],
        'Other' => ['Other', '', 0],
    ];

    public function handle(ErpnextClient $client): int
    {
        $this->line('<info>' . self::TYPE_DOCTYPE . '</info>');
        $this->ensureTypeDoctype($client);
        $this->seed($client, self::TYPE_DOCTYPE, $this->typeRows());
        $this->repoint($client, 'Vessel', 'vessel_type', self::TYPE_DOCTYPE);

        $this->line('<info>' . self::CERTIFICATE_DOCTYPE . '</info>');
        $this->ensureCertificateDoctype($client);
        $this->seed($client, self::CERTIFICATE_DOCTYPE, $this->certificateRows());
        $this->repoint($client, 'Vessel Certificate', 'certificate_type', self::CERTIFICATE_DOCTYPE);

        return self::SUCCESS;
    }

    private function ensureTypeDoctype(ErpnextClient $client): void
    {
        $this->ensureDoctype($client, self::TYPE_DOCTYPE, 'type_name', [
            ['fieldname' => 'type_name', 'label' => 'Vessel Type', 'fieldtype' => 'Data', 'reqd' => 1, 'unique' => 1, 'in_list_view' => 1],
            ['fieldname' => 'type_code', 'label' => 'Code', 'fieldtype' => 'Data', 'in_list_view' => 1],
            ['fieldname' => 'category', 'label' => 'Category', 'fieldtype' => 'Select', 'in_list_view' => 1,
                'options' => "\nLiquid Cargo\nDry Cargo\nOffshore\nSupport\nPassenger\nOther"],
            ['fieldname' => 'is_active', 'label' => 'Active', 'fieldtype' => 'Check', 'default' => '1'],
            ['fieldname' => 'description', 'label' => 'Description', 'fieldtype' => 'Small Text'],
        ], usersMayAdd: true);
    }

    private function ensureCertificateDoctype(ErpnextClient $client): void
    {
        $this->ensureDoctype($client, self::CERTIFICATE_DOCTYPE, 'certificate_name', [
            ['fieldname' => 'certificate_name', 'label' => 'Certificate Type', 'fieldtype' => 'Data', 'reqd' => 1, 'unique' => 1, 'in_list_view' => 1],
            ['fieldname' => 'category', 'label' => 'Category', 'fieldtype' => 'Select', 'in_list_view' => 1,
                'options' => "\nStatutory\nMARPOL\nManagement\nClass\nInsurance\nOther"],
            ['fieldname' => 'issuing_authority', 'label' => 'Usually Issued By', 'fieldtype' => 'Data'],
            ['fieldname' => 'validity_months', 'label' => 'Validity (months)', 'fieldtype' => 'Int', 'in_list_view' => 1],
            ['fieldname' => 'is_active', 'label' => 'Active', 'fieldtype' => 'Check', 'default' => '1'],
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function typeRows(): array
    {
        $rows = [];

        foreach (self::TYPES as $type => [$code, $category, $description]) {
            $rows[$type] = [
                'type_name' => $type,
                'type_code' => $code,
                'category' => $category,
                'description' => $description,
                'is_active' => 1,
            ];
        }

        return $rows;
    }

    /** @return array<string, array<string, mixed>> */
    private function certificateRows(): array
    {
        $rows = [];

        foreach (self::CERTIFICATE_TYPES as $type => [$category, $authority, $months]) {
            $rows[$type] = [
                'certificate_name' => $type,
                'category' => $category,
                'issuing_authority' => $authority,
                'validity_months' => $months,
                'is_active' => 1,
            ];
        }

        return $rows;
    }
}
