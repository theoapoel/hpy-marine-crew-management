<?php

namespace Database\Seeders;

use App\Models\Crew;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@marine-erp.test',
        ]);

        $vessels = [
            ['name' => 'MV Pacific Star', 'type' => 'Container', 'status' => 'At Sea', 'principal' => 'Evergreen Shipping', 'crew_capacity' => 32, 'eta' => 'Busan +3d'],
            ['name' => 'MV Ocean Prime', 'type' => 'Bulk Carrier', 'status' => 'In Port', 'principal' => 'MSC Lines', 'crew_capacity' => 26, 'eta' => 'Manila'],
            ['name' => 'MV Atlantic Ray', 'type' => 'Container', 'status' => 'Dry Dock', 'principal' => 'Maersk', 'crew_capacity' => 30, 'eta' => 'Subic +14d'],
            ['name' => 'MV Eastern Wind', 'type' => 'Tanker', 'status' => 'At Sea', 'principal' => 'PIL', 'crew_capacity' => 34, 'eta' => 'Singapore +5d'],
            ['name' => 'MV Southern Cross', 'type' => 'Bulk Carrier', 'status' => 'At Sea', 'principal' => 'Evergreen Shipping', 'crew_capacity' => 28, 'eta' => 'Cebu +8d'],
        ];

        foreach ($vessels as $v) {
            Vessel::create($v);
        }

        $crews = [
            ['name' => 'Juan dela Cruz', 'rank' => 'Master', 'status' => 'Onboard', 'vessel_id' => 1],
            ['name' => 'Roberto Santos', 'rank' => 'Officers', 'status' => 'Onboard', 'vessel_id' => 1],
            ['name' => 'Antonio Bautista', 'rank' => 'Engineers', 'status' => 'Onboard', 'vessel_id' => 1],
            ['name' => 'Michael Reyes', 'rank' => 'Officers', 'status' => 'Sign Off', 'vessel_id' => 2],
            ['name' => 'Carlos Mendoza', 'rank' => 'Ratings', 'status' => 'Onboard', 'vessel_id' => 2],
            ['name' => 'Fernando Lopez', 'rank' => 'Engineers', 'status' => 'Onboard', 'vessel_id' => 4],
            ['name' => 'Ricardo Villanueva', 'rank' => 'Master', 'status' => 'Onboard', 'vessel_id' => 4],
            ['name' => 'Eduardo Ramos', 'rank' => 'Ratings', 'status' => 'Onboard', 'vessel_id' => 5],
            ['name' => 'Miguel Torres', 'rank' => 'Catering', 'status' => 'Standby', 'vessel_id' => null],
            ['name' => 'Jose Aquino', 'rank' => 'Officers', 'status' => 'Standby', 'vessel_id' => null],
        ];

        foreach ($crews as $c) {
            Crew::create(array_merge([
                'nationality' => 'Filipino',
                'seaman_book_no' => 'SB-' . fake()->numerify('######'),
                'sign_on_date' => now()->subMonths(rand(1, 6)),
                'contract_end_date' => now()->addMonths(rand(1, 8)),
            ], $c));
        }
    }
}
