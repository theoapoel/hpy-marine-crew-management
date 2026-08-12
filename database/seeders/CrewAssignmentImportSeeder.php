<?php

namespace Database\Seeders;

use App\Models\CrewApplication;
use App\Models\CrewCandidate;
use App\Models\CrewAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Reads back what `php artisan crew:export-assignments` wrote.
 *
 * Matching is by uuid, so running this twice updates rather than duplicates, and rows
 * already entered on this install are left alone. Candidate and application links are
 * resolved by uuid too and simply dropped when that record does not exist here — the
 * assignment carries its own crew_name snapshot, so it stays readable without them.
 */
class CrewAssignmentImportSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('exports/crew-assignments.json');

        if (! File::exists($path)) {
            $this->command?->warn("No export found at {$path} — nothing to import.");

            return;
        }

        $rows = json_decode(File::get($path), true)['assignments'] ?? [];

        $candidates = CrewCandidate::pluck('id', 'uuid');
        $applications = CrewApplication::pluck('id', 'uuid');

        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $candidateUuid = $row['candidate_uuid'] ?? null;
            $applicationUuid = $row['application_uuid'] ?? null;
            unset($row['candidate_uuid'], $row['application_uuid']);

            $row['crew_candidate_id'] = $candidateUuid ? $candidates[$candidateUuid] ?? null : null;
            $row['crew_application_id'] = $applicationUuid ? $applications[$applicationUuid] ?? null : null;

            $uuid = $row['uuid'];
            unset($row['uuid']);

            $existing = CrewAssignment::withTrashed()->where('uuid', $uuid)->exists();

            CrewAssignment::withTrashed()->updateOrCreate(['uuid' => $uuid], $row);

            $existing ? $updated++ : $created++;
        }

        $this->command?->info("Crew assignments imported: {$created} new, {$updated} updated.");
    }
}
