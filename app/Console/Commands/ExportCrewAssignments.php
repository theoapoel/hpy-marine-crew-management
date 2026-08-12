<?php

namespace App\Console\Commands;

use App\Models\CrewAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Carries crew assignments from one install to another.
 *
 * Assignments are the one part of the crew story that lives only in the local
 * database — ERP HPY has no sign-off field to hold them — and the SQLite file is
 * gitignored, so a fresh deploy starts with the table empty. This command writes the
 * rows to a JSON file that *is* committable; CrewAssignmentImportSeeder reads it back
 * on the other side.
 *
 * The row's own auto-increment id is left out on purpose: it means nothing on the
 * target install. The uuid identifies a row across installs, and the two foreign keys
 * travel as candidate/application uuids so the seeder can re-point them locally.
 */
class ExportCrewAssignments extends Command
{
    protected $signature = 'crew:export-assignments
                            {--path= : where to write the JSON (default database/exports/crew-assignments.json)}
                            {--company= : only assignments of this ERP HPY company}';

    protected $description = 'Export crew assignments to a JSON file for import on another install';

    /** Columns worth carrying: everything but the local id and the two foreign keys. */
    private const COLUMNS = [
        'uuid', 'assignment_code', 'employee_id', 'crew_name',
        'vessel', 'rank', 'company', 'status',
        'planned_sign_on_date', 'sign_on_date', 'sign_on_port', 'contract_months',
        'planned_sign_off_date', 'sign_off_date', 'sign_off_port', 'sign_off_reason',
        'wage', 'wage_currency',
        'notes', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at',
    ];

    public function handle(): int
    {
        $rows = CrewAssignment::withTrashed()
            ->with(['candidate:id,uuid', 'application:id,uuid'])
            ->when($this->option('company'), fn ($q, $company) => $q->where('company', $company))
            ->orderBy('id')
            ->get()
            ->map(fn (CrewAssignment $a) => $this->toArray($a))
            ->all();

        if ($rows === []) {
            $this->warn('No assignments matched — nothing written.');

            return self::SUCCESS;
        }

        $path = $this->option('path') ?: database_path('exports/crew-assignments.json');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'exported_at' => now()->toIso8601String(),
            'assignments' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        $this->info(count($rows) . ' assignment(s) -> ' . $path);
        $this->line('Commit the file, deploy, then run: php artisan db:seed --class=CrewAssignmentImportSeeder');

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(CrewAssignment $assignment): array
    {
        $row = [];

        foreach (self::COLUMNS as $column) {
            $row[$column] = $assignment->getRawOriginal($column);
        }

        // Ids differ per install; uuids do not.
        $row['candidate_uuid'] = $assignment->candidate?->uuid;
        $row['application_uuid'] = $assignment->application?->uuid;

        return $row;
    }
}
