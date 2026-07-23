<?php

namespace App\Console\Commands;

use App\Models\Principal;
use Illuminate\Console\Command;

/**
 * Pushes local records into ERP HPY. Records are normally synced as they are saved;
 * this is the catch-up for anything that failed or was created while ERP HPY was
 * unreachable.
 */
class ErpnextSync extends Command
{
    protected $signature = 'erpnext:sync {model : Principal} {--pending : only records not yet synced or failed} {--code=* : specific principal codes}';

    protected $description = 'Sync local records to ERP HPY';

    /** Models this command knows how to push. */
    private const MODELS = [
        'principal' => Principal::class,
    ];

    public function handle(): int
    {
        $key = mb_strtolower((string) $this->argument('model'));

        if (! isset(self::MODELS[$key])) {
            $this->error('Unknown model. Available: ' . implode(', ', array_keys(self::MODELS)));

            return self::FAILURE;
        }

        /** @var class-string<Principal> $class */
        $class = self::MODELS[$key];

        $records = $class::query()
            ->when($this->option('pending'), fn ($q) => $q->whereIn('erpnext_sync_status', ['pending', 'failed']))
            ->when($this->option('code'), fn ($q, $codes) => $q->whereIn('principal_code', $codes))
            ->get();

        if ($records->isEmpty()) {
            $this->info('Nothing to sync.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($records as $record) {
            if ($record->syncToErp()) {
                $this->info("+ {$record->principal_code} -> {$record->erpnext_name}");

                continue;
            }

            $failed++;
            $this->error("! {$record->principal_code}: {$record->erpnext_sync_error}");
        }

        $this->line(($records->count() - $failed) . ' synced, ' . $failed . ' failed.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
