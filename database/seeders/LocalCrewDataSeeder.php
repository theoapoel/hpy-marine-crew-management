<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Reads back what `php artisan crew:export-local` wrote.
 *
 * Rows are matched on uuid, so a re-run updates instead of duplicating and anything
 * entered directly on this install is left alone. The tables are walked parents-first:
 * each one's uuid map is built as it is imported, so applications and assignments can
 * point at the candidates that just landed.
 */
class LocalCrewDataSeeder extends Seeder
{
    /** Import order, and the uuid-carried links to translate back into local ids. */
    private const TABLES = [
        'crew_candidates' => [],
        'crew_applications' => ['crew_candidate_id' => 'crew_candidates'],
        'crew_assignments' => [
            'crew_candidate_id' => 'crew_candidates',
            'crew_application_id' => 'crew_applications',
        ],
    ];

    public function run(): void
    {
        $path = database_path('exports/local-crew-data.json');

        if (! File::exists($path)) {
            $this->command?->warn("No export found at {$path} — nothing to import.");

            return;
        }

        $export = json_decode(File::get($path), true) ?: [];

        /** @var array<string, array<string, int>> $ids  table => uuid => local id */
        $ids = [];

        foreach (self::TABLES as $table => $links) {
            $ids[$table] = DB::table($table)->pluck('id', 'uuid')->all();
            $created = 0;
            $updated = 0;

            foreach ($export[$table] ?? [] as $row) {
                foreach ($links as $column => $parent) {
                    $uuid = $row[preg_replace('/_id$/', '_uuid', $column)] ?? null;
                    $row[$column] = $uuid ? $ids[$parent][$uuid] ?? null : null;
                    unset($row[preg_replace('/_id$/', '_uuid', $column)]);
                }

                $uuid = $row['uuid'];
                unset($row['uuid']);

                if (isset($ids[$table][$uuid])) {
                    DB::table($table)->where('uuid', $uuid)->update($row);
                    $updated++;
                } else {
                    $ids[$table][$uuid] = DB::table($table)->insertGetId($row + ['uuid' => $uuid]);
                    $created++;
                }
            }

            $this->command?->info(sprintf('%-18s %d new, %d updated', $table, $created, $updated));
        }
    }
}
