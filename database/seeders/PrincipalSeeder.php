<?php

namespace Database\Seeders;

use App\Models\Principal;
use Illuminate\Database\Seeder;

/** Three principals covering the shapes the module has to handle. */
class PrincipalSeeder extends Seeder
{
    public function run(): void
    {
        $principals = [
            [
                'principal_name' => 'MOL Chemical Tankers Indonesia',
                'legal_entity_name' => 'PT MOL Chemical Tankers Indonesia',
                'principal_type' => 'shipowner',
                'country' => 'Indonesia',
                'head_office_address' => 'Sampoerna Strategic Square, Jl. Jend. Sudirman Kav. 45',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12930',
                'phone' => '02157951234',
                'email' => 'crewing@molct.example.co.id',
                'website' => 'https://molct.example.co.id',
                'contract_start_date' => '2025-01-01',
                'contract_end_date' => '2027-12-31',
                'contract_type' => 'exclusive',
                'manning_fee_type' => 'per_crew',
                'manning_fee_amount' => 3500000,
                'payment_terms' => 'NET 30',
                'p_and_i_club' => 'Japan P&I Club',
                'wage_scale_reference' => 'ITF TCC',
                'tax_id' => '01.234.567.8-091.000',
                'status' => 'active',
                'contacts' => [
                    ['name' => 'Hiroshi Tanaka', 'position' => 'Crew Superintendent', 'email' => 'h.tanaka@molct.example.co.id', 'phone' => '081298761111', 'is_primary' => true],
                    ['name' => 'Dewi Kartika', 'position' => 'DPA', 'email' => 'd.kartika@molct.example.co.id', 'phone' => '081298762222', 'is_primary' => false],
                ],
            ],
            [
                'principal_name' => 'Pacific Ocean Ship Management',
                'legal_entity_name' => 'Pacific Ocean Ship Management Pte Ltd',
                'principal_type' => 'ship_manager',
                'country' => 'Singapore',
                'head_office_address' => '10 Anson Road, International Plaza',
                'city' => 'Singapore',
                'postal_code' => '079903',
                'phone' => '6562234567',
                'email' => 'manning@posm.example.sg',
                'contract_start_date' => '2026-03-01',
                'contract_type' => 'non_exclusive',
                'manning_fee_type' => 'percentage',
                'manning_fee_amount' => 8,
                'currency' => 'USD',
                'payment_terms' => 'NET 45',
                'p_and_i_club' => 'Gard',
                'wage_scale_reference' => 'ITF Uniform TCC',
                'status' => 'active',
                'contacts' => [
                    ['name' => 'Michael Lim', 'position' => 'Marine Superintendent', 'email' => 'm.lim@posm.example.sg', 'phone' => '6591234567', 'is_primary' => true],
                ],
            ],
            [
                'principal_name' => 'Bahari Nusantara Charter',
                'legal_entity_name' => 'PT Bahari Nusantara Charter',
                'principal_type' => 'charterer',
                'country' => 'Indonesia',
                'city' => 'Surabaya',
                'province' => 'Jawa Timur',
                'phone' => '0313456789',
                'email' => 'ops@bnc.example.co.id',
                'manning_fee_type' => 'flat_monthly',
                'manning_fee_amount' => 75000000,
                'payment_terms' => 'NET 14',
                'status' => 'prospect',
                'notes' => 'Masih tahap penjajakan; menunggu tender tug & barge 2026.',
                'contacts' => [
                    ['name' => 'Agung Wibowo', 'position' => 'Operation Manager', 'email' => 'a.wibowo@bnc.example.co.id', 'phone' => '081355550001', 'is_primary' => true],
                ],
            ],
        ];

        foreach ($principals as $data) {
            $contacts = $data['contacts'];
            unset($data['contacts']);

            $principal = Principal::updateOrCreate(
                ['principal_name' => $data['principal_name']],
                $data + ['created_by' => 'seeder', 'updated_by' => 'seeder'],
            );

            $principal->contactPersons()->delete();

            foreach ($contacts as $contact) {
                $principal->contactPersons()->create($contact);
            }
        }
    }
}
