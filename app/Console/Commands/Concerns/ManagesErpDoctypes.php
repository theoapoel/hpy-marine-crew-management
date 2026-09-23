<?php

namespace App\Console\Commands\Concerns;

use App\Services\Erpnext\ErpnextClient;

/**
 * The steps every "give this hardcoded list its own doctype" command repeats: create
 * the master doctype, seed it with what the list held, and re-point the field that
 * used to be a Select at it. Each step is idempotent.
 */
trait ManagesErpDoctypes
{
    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  bool  $usersMayAdd  let HR User create records too, not only HR Manager
     */
    private function ensureDoctype(ErpnextClient $client, string $doctype, string $titleField, array $fields, bool $usersMayAdd = false): void
    {
        if ($client->exists('DocType', $doctype)) {
            $this->line("  = {$doctype} (already there)");

            return;
        }

        $client->create('DocType', [
            'name' => $doctype,
            'module' => 'HR',
            'custom' => 1,
            'autoname' => "field:{$titleField}",
            'title_field' => $titleField,
            'fields' => $fields,
            'permissions' => [
                ['role' => 'HR Manager', 'read' => 1, 'write' => 1, 'create' => 1, 'delete' => 1, 'report' => 1, 'export' => 1],
                ['role' => 'HR User', 'read' => 1, 'report' => 1] + ($usersMayAdd ? ['write' => 1, 'create' => 1] : []),
            ],
        ]);

        $this->info("  + {$doctype}");
    }

    /** @param array<string, array<string, mixed>> $rows */
    private function seed(ErpnextClient $client, string $doctype, array $rows): void
    {
        $existing = collect($client->list($doctype, ['name'], [], 500))->pluck('name')->all();

        foreach ($rows as $name => $row) {
            if (in_array($name, $existing, true)) {
                continue;
            }

            $client->create($doctype, $row);
            $this->info("  + {$name}");
        }

        $this->line('  = '.count(array_intersect(array_keys($rows), $existing)).' already there');
    }

    /**
     * Point a field at its new master doctype. ERP HPY refuses some fieldtype changes
     * outright; when it does, the Select stays but its options are refreshed from the
     * same records, so both sides still agree on the list.
     */
    private function repoint(ErpnextClient $client, string $parent, string $fieldname, string $target): void
    {
        $document = $client->get('DocType', $parent);
        $fields = $document['fields'] ?? [];

        $position = collect($fields)->search(fn ($f) => $f['fieldname'] === $fieldname);

        if ($position === false) {
            $this->warn("  ! {$parent}.{$fieldname} not found");

            return;
        }

        if (($fields[$position]['fieldtype'] ?? null) === 'Link' && ($fields[$position]['options'] ?? null) === $target) {
            $this->line("  = {$parent}.{$fieldname} (already a Link)");

            return;
        }

        $asLink = $fields;
        $asLink[$position]['fieldtype'] = 'Link';
        $asLink[$position]['options'] = $target;

        try {
            $client->update('DocType', $parent, ['fields' => $asLink]);
            $this->info("  ~ {$parent}.{$fieldname} -> Link ({$target})");
        } catch (\Throwable $e) {
            $fields[$position]['options'] = collect($client->list($target, ['name'], [], 500))->pluck('name')->implode("\n");
            $client->update('DocType', $parent, ['fields' => $fields]);

            $this->warn("  ! ERP HPY refused the change to Link; Select options of {$parent}.{$fieldname} synced with {$target}");
        }
    }
}
