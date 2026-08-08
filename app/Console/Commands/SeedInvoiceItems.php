<?php

namespace App\Console\Commands;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Console\Command;

/**
 * The Item master behind the manning invoices KBM issues to vessel owners.
 *
 * Every line the invoices bill for is one of these: a recurring manning fee per
 * crew onboard, a fixed on-signer cost, or a pass-through the agency pays first
 * and rebills. Codifying them means an invoice line picks a rate instead of
 * retyping it, and revenue lands in a consistent Item Group for the per-vessel
 * profitability reporting.
 *
 * Rates are USD, which the IDR company price lists cannot hold, so they go into
 * a dedicated USD selling price list rather than Item.standard_rate. Items whose
 * price is per-transaction (tickets, FDA, excess baggage) get no price at all.
 *
 * Idempotent: existing groups, items and prices are left as they are.
 */
class SeedInvoiceItems extends Command
{
    protected $signature = 'erp:seed-invoice-items {--dry-run : show what would be written, write nothing}';

    protected $description = 'Seed the manning-invoice Item master (groups, items, USD prices) in ERP HPY';

    /** Selling price list the USD rates live in; created if missing. */
    private const PRICE_LIST = 'Standard Selling USD';

    /**
     * Most of these are charged per seafarer, and the invoices say so: "(3 Crew)",
     * not "(3 Nos)". ERP HPY has no such UOM out of the box.
     */
    private const CREW_UOM = 'Crew';

    /** Item groups these items sort into, beyond the "Manning Fee" group already there. */
    private const GROUPS = [
        'Crew Mobilization',
        'Crew Travel & Documents',
        'Port Disbursement',
        'Marine Spare Parts',
    ];

    /**
     * code => [name, group, uom, USD rate (null = priced per transaction), purchased?, description]
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: float|null, 4: bool, 5: string}>
     */
    private function items(): array
    {
        return [
            // Recurring, billed per crew onboard per month.
            'MF-OFFICER' => ['Manning Fee - Officer', 'Manning Fee', self::CREW_UOM, 75.0, false,
                'Monthly manning fee per officer onboard (Master, CO, 2O, 3O, 2E, 3E, 4E, ETO).'],
            'MF-RATING' => ['Manning Fee - Rating', 'Manning Fee', self::CREW_UOM, 50.0, false,
                'Monthly manning fee per rating onboard (Bosun, Pumpman, AB, OS, Fitter, Oiler, Cook, Wiper, Cadet).'],
            'MLC-RETRIBUTION' => ['Retribution for MLC 2006', 'Manning Fee', self::CREW_UOM, 5.0, false,
                'MLC 2006 retribution, charged per crew onboard.'],

            // Fixed on-signer costs, billed per crew joining.
            'MED-FITNESS' => ['Medical Fitness', 'Crew Mobilization', self::CREW_UOM, 60.0, true,
                'Pre-employment medical examination per joining crew.'],
            'MED-FITNESS-LIB' => ['Medical Fitness - Liberia Online', 'Crew Mobilization', self::CREW_UOM, 95.0, true,
                'Medical examination with Liberia flag online endorsement, per joining crew.'],
            'WORKING-GEARS' => ['Working Gears', 'Crew Mobilization', self::CREW_UOM, 60.0, true,
                'Personal protective equipment issued per joining crew.'],
            'HOTEL-ACCOM' => ['Hotel Accommodation', 'Crew Mobilization', 'Day', 50.0, true,
                'Hotel for crew awaiting joining. Quantity is crew x nights.'],
            'TRAVEL-ALLOW' => ['Travel Allowance', 'Crew Mobilization', self::CREW_UOM, 38.0, true,
                'Travel allowance per joining crew.'],
            'VAC-CHOLERA' => ['Cholera Vaccination', 'Crew Mobilization', self::CREW_UOM, 30.0, true,
                'Cholera vaccination, per crew required to hold one.'],
            'MEALS-ALLOW' => ['Meals Allowance', 'Crew Mobilization', self::CREW_UOM, 60.0, true,
                'Meals allowance for crew joining abroad.'],

            // Travel and documents: actual cost, rebilled.
            'AIR-TICKET' => ['Airlines Ticket', 'Crew Travel & Documents', 'Nos', null, true,
                'Air ticket at actual fare. State crew name and route on the invoice line.'],
            'VISA-CHINA' => ['Chinese Visa', 'Crew Travel & Documents', self::CREW_UOM, 120.0, true,
                'Chinese visa application per crew.'],
            'EXCESS-BAGGAGE' => ['Excess Baggage Fee', 'Crew Travel & Documents', 'Nos', null, true,
                'Excess baggage charged at actual, for on or off signers.'],

            // Port and supply pass-throughs.
            'FDA' => ['Final Disbursement Account (FDA)', 'Port Disbursement', 'Nos', null, true,
                'Port disbursement at actual. State vessel and port on the invoice line.'],
            'SP-BATTERY-HT-GRC' => ['Spare Part - Battery HT GRC', 'Marine Spare Parts', 'Nos', 310.0, true,
                'Handheld transceiver battery, GRC type. Supplied and rebilled.'],
            'SP-RADAR-REFLECTOR' => ['Spare Part - Radar Reflector', 'Marine Spare Parts', 'Nos', 61.0, true,
                'Radar reflector. Supplied and rebilled.'],
        ];
    }

    public function handle(ErpnextClient $client): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->uom($client, $dry);
        $this->groups($client, $dry);
        $this->priceList($client, $dry);

        foreach ($this->items() as $code => [$name, $group, $uom, $rate, $purchased, $description]) {
            $this->item($client, $dry, $code, $name, $group, $uom, $purchased, $description);
            $this->price($client, $dry, $code, $rate);
        }

        if ($dry) {
            $this->newLine();
            $this->comment('Dry run: nothing was written to ERP HPY.');
        }

        return self::SUCCESS;
    }

    private function uom(ErpnextClient $client, bool $dry): void
    {
        if ($client->exists('UOM', self::CREW_UOM)) {
            $this->line('= UOM ' . self::CREW_UOM);

            return;
        }

        if (! $dry) {
            $client->create('UOM', ['uom_name' => self::CREW_UOM, 'must_be_whole_number' => 1]);
        }

        $this->info('+ UOM ' . self::CREW_UOM);
    }

    private function groups(ErpnextClient $client, bool $dry): void
    {
        foreach (self::GROUPS as $group) {
            if ($client->exists('Item Group', $group)) {
                $this->line("= Item Group {$group}");

                continue;
            }

            if (! $dry) {
                $client->create('Item Group', [
                    'item_group_name' => $group,
                    'parent_item_group' => 'All Item Groups',
                    'is_group' => 0,
                ]);
            }

            $this->info("+ Item Group {$group}");
        }
    }

    private function priceList(ErpnextClient $client, bool $dry): void
    {
        if ($client->exists('Price List', self::PRICE_LIST)) {
            $this->line('= Price List ' . self::PRICE_LIST);

            return;
        }

        if (! $dry) {
            $client->create('Price List', [
                'price_list_name' => self::PRICE_LIST,
                'currency' => 'USD',
                'selling' => 1,
                'buying' => 0,
                'enabled' => 1,
            ]);
        }

        $this->info('+ Price List ' . self::PRICE_LIST . ' (USD, selling)');
    }

    private function item(ErpnextClient $client, bool $dry, string $code, string $name, string $group, string $uom, bool $purchased, string $description): void
    {
        $current = $client->list('Item', ['name', 'stock_uom'], [['name', '=', $code]], 1);

        if ($current !== []) {
            if ($current[0]['stock_uom'] !== $uom) {
                if (! $dry) {
                    $client->update('Item', $code, ['stock_uom' => $uom]);
                }

                $this->info("~ {$code} UOM {$current[0]['stock_uom']} -> {$uom}");

                return;
            }

            $this->line("= {$code}");

            return;
        }

        if (! $dry) {
            $client->create('Item', [
                'item_code' => $code,
                'item_name' => $name,
                'item_group' => $group,
                'stock_uom' => $uom,
                'description' => $description,
                // Services and pass-throughs: nothing to hold in a warehouse.
                'is_stock_item' => 0,
                'is_sales_item' => 1,
                'is_purchase_item' => $purchased ? 1 : 0,
            ]);
        }

        $this->info("+ {$code} — {$name} [{$group}]");
    }

    private function price(ErpnextClient $client, bool $dry, string $code, ?float $rate): void
    {
        if ($rate === null) {
            $this->line("    no standard rate (billed at actual)");

            return;
        }

        $existing = $client->list('Item Price', ['name', 'price_list_rate'], [
            ['item_code', '=', $code],
            ['price_list', '=', self::PRICE_LIST],
        ], 1);

        if ($existing !== []) {
            $this->line("    price USD {$existing[0]['price_list_rate']} already set");

            return;
        }

        if (! $dry) {
            $client->create('Item Price', [
                'item_code' => $code,
                'price_list' => self::PRICE_LIST,
                'currency' => 'USD',
                'selling' => 1,
                'price_list_rate' => $rate,
            ]);
        }

        $this->info("    price USD {$rate}");
    }
}
