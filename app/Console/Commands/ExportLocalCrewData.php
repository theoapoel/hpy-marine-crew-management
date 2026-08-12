<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Carries the locally-owned crew data from one install to another.
 *
 * Candidate Pool, Recruitment Pipeline and Crew Assignment are the three modules that
 * live only in the local database — ERP HPY has no doctype holding them, and the
 * SQLite file is gitignored — so a fresh deploy starts with all three empty. This
 * command writes them to a committable JSON file; LocalCrewDataSeeder reads it back on
 * the other side.
 *
 * Auto-increment ids are left out: they mean nothing on the target install. Rows are
 * identified by uuid, and the links between the three tables travel as uuids too so
 * the seeder can re-point them locally.
 */
class ExportLocalCrewData extends Command
{
    protected $signature = 'crew:export-local
                            {--path= : where to write the JSON (default database/exports/local-crew-data.json)}
                            {--company= : only rows of this ERP HPY company}';

    protected $description = 'Export candidates, applications and assignments to JSON for import on another install';

    /**
     * Table => the foreign keys to translate into uuids, as column => owning table.
     * Order matters: parents first, so the seeder can resolve links as it goes.
     */
    private const TABLES = [
        'crew_candidates' => [],
        'crew_applications' => ['crew_candidate_id' => 'crew_candidates'],
        'crew_assignments' => [
            'crew_candidate_id' => 'crew_candidates',
            'crew_application_id' => 'crew_applications',
        ],
    ];

    public function handle(): int
    {
        $export = [];

        foreach (self::TABLES as $table => $links) {
            $export[$table] = $this->rows($table, $links);
            $this->line(sprintf('  %-18s %d row(s)', $table, count($export[$table])));
        }

        if (array_sum(array_map('count', $export)) === 0) {
            $this->warn('Nothing matched — no file written.');

            return self::SUCCESS;
        }

        $path = $this->option('path') ?: database_path('exports/local-crew-data.json');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode(
            ['exported_at' => now()->toIso8601String()] + $export,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . "\n");

        $this->info('-> ' . $path);
        $this->line('Commit the file, deploy, then run: php artisan db:seed --class=LocalCrewDataSeeder --force');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string> $links
     * @return list<array<string, mixed>>
     */
    private function rows(string $table, array $links): array
    {
        $rows = DB::table($table)
            ->when($this->option('company'), fn ($q, $company) => $q->where('company', $company))
            ->orderBy('id')
            ->get();

        // uuid lookups for the parents this table points at.
        $parents = [];
        foreach (array_unique($links) as $parent) {
            $parents[$parent] = DB::table($parent)->pluck('uuid', 'id');
        }

        return $rows->map(function ($row) use ($links, $parents) {
            $row = (array) $row;

            foreach ($links as $column => $parent) {
                $id = $row[$column] ?? null;
                $row[$this->uuidKey($column)] = $id ? $parents[$parent][$id] ?? null : null;
                unset($row[$column]);
            }

            unset($row['id']);

            return $row;
        })->all();
    }

    /** crew_candidate_id -> crew_candidate_uuid */
    private function uuidKey(string $column): string
    {
        return preg_replace('/_id$/', '_uuid', $column);
    }
}
