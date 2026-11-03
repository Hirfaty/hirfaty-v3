<?php

namespace App\Console\Commands;

use App\Services\Cathedis\CathedisCitySyncService;
use App\Services\Cathedis\CathedisException;
use Illuminate\Console\Command;

/**
 * Refresh Cathedis's city list and link our Cathedis localities to it — see
 * CathedisCitySyncService. Safe to re-run: existing links are kept.
 */
class SyncCathedisCities extends Command
{
    protected $signature = 'cathedis:sync-cities';

    protected $description = "Fetch Cathedis's cities and sectors and link our localities to them";

    public function handle(CathedisCitySyncService $sync): int
    {
        try {
            $result = $sync->sync();
        } catch (CathedisException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$result['cities']} Cathedis cities and {$result['sectors']} sectors stored; {$result['linked']} locality(ies) newly linked.");

        if ($result['unserved'] !== []) {
            $this->warn(count($result['unserved']).' locality(ies) linked to a city Cathedis does not deliver to right now:');
            foreach ($result['unserved'] as $name) {
                $this->line("  - {$name}");
            }
        }

        if ($result['unmatched'] !== []) {
            $this->warn(count($result['unmatched']).' locality(ies) not found on Cathedis\'s list:');
            foreach ($result['unmatched'] as $name) {
                $this->line("  - {$name}");
            }
        }

        return self::SUCCESS;
    }
}
