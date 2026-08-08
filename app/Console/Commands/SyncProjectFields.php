<?php

namespace App\Console\Commands;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Ties a Project to the ship it is run for.
 *
 * Vessel Profitability reads money through Projects — one per contract, charter or
 * voyage — so a project has to say which vessel it belongs to. That is the whole of
 * this command: a `vessel` Link field on the standard Project doctype, added as a
 * Custom Field so ERP HPY's own updates leave it alone.
 *
 * The definition is also written to storage/app/erpnext/… as importable JSON, the way
 * the Principal module does it. Additive and idempotent.
 */
class SyncProjectFields extends Command
{
    protected $signature = 'erp:sync-project-fields {--json-only : write the JSON file without touching ERP HPY}';

    protected $description = 'Add the "vessel" link field to the Project doctype in ERP HPY';

    /** Marine doctypes in this instance all live in HR; the link belongs with them. */
    private const MODULE = 'HR';

    public function handle(ErpnextClient $client): int
    {
        $this->writeJson('custom_fields/project_vessel_field.json', $this->definition());

        if ($this->option('json-only')) {
            return self::SUCCESS;
        }

        $existing = $client->list('Custom Field', ['name'], [
            ['dt', '=', 'Project'],
            ['fieldname', '=', 'vessel'],
        ], 1);

        if ($existing !== []) {
            $this->line('= Project.vessel (already there)');

            return self::SUCCESS;
        }

        $client->create('Custom Field', $this->definition());
        $this->info('+ Project.vessel (Link -> Vessel)');

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $definition */
    private function writeJson(string $path, array $definition): void
    {
        $full = storage_path('app/erpnext/' . $path);

        File::ensureDirectoryExists(dirname($full));
        File::put($full, json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        $this->line("  json: storage/app/erpnext/{$path}");
    }

    /** @return array<string, mixed> */
    private function definition(): array
    {
        return [
            'doctype' => 'Custom Field',
            'dt' => 'Project',
            'fieldname' => 'vessel',
            'label' => 'Vessel',
            'fieldtype' => 'Link',
            'options' => 'Vessel',
            // Right after the customer: whose ship, whose contract, side by side.
            'insert_after' => 'customer',
            'in_list_view' => 1,
            'in_standard_filter' => 1,
            'module' => self::MODULE,
            'description' => 'The ship this project earns and spends for. Drives Vessel Profitability.',
        ];
    }
}
