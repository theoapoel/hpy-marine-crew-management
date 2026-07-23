<?php

namespace App\Console\Commands;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Console\Command;

/**
 * The instance carries a custom "Rank" Doctype for seafarer ranks, and Crew Master
 * links to it. This seeds the standard merchant-marine ranks. Idempotent.
 */
class SyncCrewRanks extends Command
{
    protected $signature = 'erp:sync-ranks';

    protected $description = 'Seed the seafarer ranks Crew Master offers into the Rank Doctype';

    /** name => [department, level (1 = highest), is officer, is management] */
    private const RANKS = [
        'Master' => ['Deck', 1, 1, 1],
        'Chief Officer' => ['Deck', 2, 1, 1],
        'Second Officer' => ['Deck', 3, 1, 0],
        'Third Officer' => ['Deck', 4, 1, 0],
        'Deck Cadet' => ['Deck', 8, 0, 0],
        'Bosun' => ['Deck', 6, 0, 0],
        'Able Seaman' => ['Deck', 7, 0, 0],
        'Ordinary Seaman' => ['Deck', 8, 0, 0],
        'Chief Engineer' => ['Engine', 1, 1, 1],
        'Second Engineer' => ['Engine', 2, 1, 1],
        'Third Engineer' => ['Engine', 3, 1, 0],
        'Fourth Engineer' => ['Engine', 4, 1, 0],
        'Electrician' => ['Engine', 5, 1, 0],
        'Oiler' => ['Engine', 7, 0, 0],
        'Wiper' => ['Engine', 8, 0, 0],
        'Engine Cadet' => ['Engine', 8, 0, 0],
        'Radio Officer' => ['Radio', 4, 1, 0],
        'Chief Cook' => ['Catering', 6, 0, 0],
        'Messman' => ['Catering', 8, 0, 0],
    ];

    public function handle(ErpnextClient $client): int
    {
        $existing = collect($client->list('Rank', ['name'], [], 500))->pluck('name')->all();

        foreach (self::RANKS as $rank => [$department, $level, $isOfficer, $isManagement]) {
            if (in_array($rank, $existing, true)) {
                $this->line("= {$rank} (already there)");

                continue;
            }

            $client->create('Rank', [
                'rank_name' => $rank,
                'department' => $department,
                'rank_level' => $level,
                'is_officer' => $isOfficer,
                'is_management' => $isManagement,
            ]);
            $this->info("+ {$rank} ({$department})");
        }

        return self::SUCCESS;
    }
}
