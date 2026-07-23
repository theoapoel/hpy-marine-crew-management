<?php

namespace Database\Seeders;

use App\Models\CrewApplication;
use App\Models\CrewAssignment;
use App\Models\CrewCandidate;
use Illuminate\Database\Seeder;

/**
 * Sample recruitment data: applications spread across the pipeline, plus one crew
 * waiting to board and one already at sea, so the Sign On / Sign Off desks have
 * something to show.
 */
class RecruitmentSeeder extends Seeder
{
    public function run(): void
    {
        $candidates = CrewCandidate::orderBy('id')->get()->keyBy('candidate_code');

        if ($candidates->isEmpty()) {
            $this->command?->warn('Jalankan CrewCandidateSeeder dulu.');

            return;
        }

        $applications = [
            ['CND-2026-00001', 'Master', 'screening', 20],
            ['CND-2026-00002', 'Chief Engineer', 'interview', 15],
            ['CND-2026-00003', 'Third Officer', 'mcu', 9],
            ['CND-2026-00004', 'Deck Cadet', 'applied', 3],
        ];

        foreach ($applications as [$code, $rank, $stage, $daysAgo]) {
            if (! $candidate = $candidates->get($code)) {
                continue;
            }

            CrewApplication::updateOrCreate(
                ['crew_candidate_id' => $candidate->id, 'applied_rank' => $rank],
                [
                    'stage' => $stage,
                    'applied_date' => now()->subDays($daysAgo)->toDateString(),
                    'screening_date' => $stage !== 'applied' ? now()->subDays($daysAgo - 2)->toDateString() : null,
                    'interview_date' => in_array($stage, ['interview', 'mcu', 'offer'], true) ? now()->subDays($daysAgo - 5)->toDateString() : null,
                    'interview_score' => in_array($stage, ['interview', 'mcu', 'offer'], true) ? 8 : null,
                    'mcu_date' => $stage === 'mcu' ? now()->subDays(2)->toDateString() : null,
                    'mcu_result' => $stage === 'mcu' ? 'pending' : null,
                    'created_by' => 'seeder',
                    'updated_by' => 'seeder',
                ],
            );
        }

        $assignments = [
            [
                'crew_name' => 'Bagus Prasetyo',
                'rank' => 'Master',
                'vessel' => 'MT Bintang Samudera',
                'status' => 'planned',
                'planned_sign_on_date' => now()->addDays(9)->toDateString(),
                'contract_months' => 6,
            ],
            [
                'crew_name' => 'Yusuf Ramadhan',
                'rank' => 'Third Officer',
                'vessel' => 'MT Bintang Samudera',
                'status' => 'onboard',
                'sign_on_date' => now()->subMonths(5)->toDateString(),
                'sign_on_port' => 'Tanjung Priok',
                'contract_months' => 6,
                'planned_sign_off_date' => now()->addDays(21)->toDateString(),
            ],
        ];

        foreach ($assignments as $assignment) {
            CrewAssignment::updateOrCreate(
                ['crew_name' => $assignment['crew_name']],
                $assignment + ['created_by' => 'seeder', 'updated_by' => 'seeder'],
            );
        }
    }
}
