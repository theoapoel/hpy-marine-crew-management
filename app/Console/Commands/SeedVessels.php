<?php

namespace App\Console\Commands;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Console\Command;

/**
 * Sample fleet for the Vessel doctype in ERP HPY, so the Vessels module and the crew
 * assignment dropdowns have something real to work with. Idempotent.
 */
class SeedVessels extends Command
{
    protected $signature = 'erp:seed-vessels {--photos : replace the photo even when one is already set}';

    protected $description = 'Create three sample vessels in ERP HPY';

    /** @return array<int, array<string, mixed>> */
    private function vessels(): array
    {
        return [
            [
                'vessel_name' => 'MT Bintang Samudera',
                'company' => 'Keenindo Bintas Marine',
                'color' => [23, 51, 84],
                'vessel_type' => 'Tanker',
                'sub_type' => 'Oil Products Tanker',
                'imo_number' => '9456123',
                'mmsi_number' => '525019123',
                'call_sign' => 'YDAB2',
                'official_number' => '2019-PST-1123',
                'flag_state' => 'Indonesia',
                'port_of_registry' => 'Jakarta',
                'classification_society' => 'BKI',
                'class_number' => 'BKI-17245',
                'year_built' => 2014,
                'builder' => 'PT Dok & Perkapalan Kodja Bahari',
                'length_overall' => 109.5,
                'length_bp' => 103.0,
                'breadth' => 18.2,
                'depth_moulded' => 9.1,
                'draft_summer' => 6.8,
                'gross_tonnage' => 4998,
                'net_tonnage' => 2210,
                'deadweight' => 6500,
                'cargo_capacity' => 7200,
                'fuel_capacity' => 320,
                'fresh_water_capacity' => 150,
                'service_speed' => 11.5,
                'max_speed' => 13.0,
                'certificates_table' => [
                    ['certificate_type' => 'Class Certificate', 'certificate_number' => 'BKI-CL-17245', 'issued_by' => 'BKI', 'issue_date' => '2023-03-01', 'expiry_date' => '2028-02-28', 'status' => 'Valid'],
                    ['certificate_type' => 'IOPP (MARPOL Annex I)', 'certificate_number' => 'IOPP-9911', 'issued_by' => 'Ditjen Hubla', 'issue_date' => '2023-04-10', 'expiry_date' => '2026-09-30', 'status' => 'Expiring Soon'],
                    ['certificate_type' => 'Safety Equipment', 'certificate_number' => 'SEQ-3321', 'issued_by' => 'Ditjen Hubla', 'issue_date' => '2024-01-15', 'expiry_date' => '2027-01-14', 'status' => 'Valid'],
                ],
            ],
            [
                'vessel_name' => 'TB Keenindo 01',
                'company' => 'Keenindo Bintas Marine',
                'color' => [140, 42, 42],
                'vessel_type' => 'Tugboat',
                'sub_type' => 'Harbour Tug',
                'imo_number' => '9612345',
                'mmsi_number' => '525019456',
                'call_sign' => 'YDBK7',
                'flag_state' => 'Indonesia',
                'port_of_registry' => 'Samarinda',
                'classification_society' => 'BKI',
                'class_number' => 'BKI-20881',
                'year_built' => 2018,
                'builder' => 'PT Bahtera Bahari Shipyard',
                'length_overall' => 28.5,
                'breadth' => 8.4,
                'depth_moulded' => 4.0,
                'draft_summer' => 3.2,
                'gross_tonnage' => 210,
                'net_tonnage' => 63,
                'deadweight' => 180,
                'fuel_capacity' => 90,
                'fresh_water_capacity' => 30,
                'service_speed' => 10.0,
                'max_speed' => 12.0,
                'certificates_table' => [
                    ['certificate_type' => 'Class Certificate', 'certificate_number' => 'BKI-CL-20881', 'issued_by' => 'BKI', 'issue_date' => '2024-06-01', 'expiry_date' => '2029-05-31', 'status' => 'Valid'],
                    ['certificate_type' => 'Minimum Safe Manning', 'certificate_number' => 'MSM-4412', 'issued_by' => 'Ditjen Hubla', 'issue_date' => '2024-06-05', 'expiry_date' => '2026-08-15', 'status' => 'Expiring Soon'],
                ],
            ],
            [
                'vessel_name' => 'BG Donmar Jaya 12',
                'company' => 'Donmar Jaladri Utama',
                'color' => [64, 64, 64],
                'vessel_type' => 'Barge',
                'sub_type' => 'Coal Barge 300 ft',
                'official_number' => '2016-BRG-0912',
                'flag_state' => 'Indonesia',
                'port_of_registry' => 'Banjarmasin',
                'classification_society' => 'BKI',
                'class_number' => 'BKI-15530',
                'year_built' => 2016,
                'builder' => 'PT Karya Bahari Shipyard',
                'length_overall' => 91.4,
                'breadth' => 24.4,
                'depth_moulded' => 5.5,
                'draft_summer' => 4.2,
                'gross_tonnage' => 3200,
                'net_tonnage' => 960,
                'deadweight' => 8000,
                'cargo_capacity' => 9500,
                'certificates_table' => [
                    ['certificate_type' => 'Class Certificate', 'certificate_number' => 'BKI-CL-15530', 'issued_by' => 'BKI', 'issue_date' => '2022-08-01', 'expiry_date' => '2026-07-31', 'status' => 'Expiring Soon'],
                    ['certificate_type' => 'Load Line', 'certificate_number' => 'LL-7788', 'issued_by' => 'Ditjen Hubla', 'issue_date' => '2022-08-10', 'expiry_date' => '2025-12-31', 'status' => 'Expired'],
                ],
            ],
        ];
    }

    public function handle(ErpnextClient $client): int
    {
        $existing = collect($client->list('Vessel', ['name', 'vessel_name', 'vessel_photo'], [], 200));

        foreach ($this->vessels() as $vessel) {
            $match = $existing->firstWhere('vessel_name', $vessel['vessel_name']);

            if ($match) {
                $client->update('Vessel', $match['name'], ['company' => $vessel['company']]);
                $this->line("= {$vessel['vessel_name']} (already there, company disamakan)");
                $this->addPhoto($client, $match['name'], $vessel, ! empty($match['vessel_photo']));

                continue;
            }

            $created = $client->create('Vessel', collect($vessel)->except('color')->all());
            $this->info("+ {$vessel['vessel_name']} ({$created['name']})");
            $this->addPhoto($client, $created['name'], $vessel, false);
        }

        return self::SUCCESS;
    }

    /**
     * Sample vessels get a drawn placeholder photo, so the fleet list and detail page
     * show what a real photo will look like. An existing photo is left alone.
     *
     * @param  array<string, mixed>  $vessel
     */
    private function addPhoto(ErpnextClient $client, string $name, array $vessel, bool $hasPhoto): void
    {
        if ($hasPhoto && ! $this->option('photos')) {
            return;
        }

        $url = $client->upload(
            $this->drawPhoto($vessel['vessel_name'], $vessel['vessel_type'], $vessel['color']),
            \Illuminate\Support\Str::slug($vessel['vessel_name']) . '.jpg',
            'Vessel',
            $name,
            private: false,
        );

        $client->update('Vessel', $name, ['vessel_photo' => $url]);
        $this->info("  foto: {$url}");
    }

    /** A simple sea-and-sky placeholder with the ship's name, drawn not downloaded. */
    private function drawPhoto(string $name, string $type, array $hull): string
    {
        $width = 640;
        $height = 400;
        $image = imagecreatetruecolor($width, $height);

        $sky = imagecolorallocate($image, 176, 208, 232);
        $sea = imagecolorallocate($image, 26, 82, 118);
        $body = imagecolorallocate($image, ...$hull);
        $deck = imagecolorallocate($image, 236, 240, 241);
        $ink = imagecolorallocate($image, 255, 255, 255);

        imagefilledrectangle($image, 0, 0, $width, 250, $sky);
        imagefilledrectangle($image, 0, 250, $width, $height, $sea);

        // Hull: a blunt wedge sitting on the waterline.
        imagefilledpolygon($image, [120, 250, 520, 250, 470, 300, 170, 300], $body);
        // Superstructure.
        imagefilledrectangle($image, 250, 190, 360, 250, $deck);
        imagefilledrectangle($image, 290, 150, 320, 190, $deck);

        imagestring($image, 5, 130, 330, $name, $ink);
        imagestring($image, 3, 130, 355, $type, $ink);

        ob_start();
        imagejpeg($image, null, 85);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
