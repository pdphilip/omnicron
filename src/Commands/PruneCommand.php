<?php

namespace PDPhilip\OmniCron\Commands;

use Illuminate\Console\Command;
use PDPhilip\OmniCron\OmniCron;

/**
 * Trim finished run history. RUNNING rows survive whatever their age - an
 * orphaned one is crash evidence, and pruning it erases the incident.
 */
class PruneCommand extends Command
{
    protected $signature = 'omnicron:prune {--days= : Override omnicron.history.keep_days}';

    protected $description = 'Delete finished runs older than the retention window';

    public function handle(OmniCron $omnicron): int
    {
        $days = (int) ($this->option('days') ?: config('omnicron.history.keep_days', 90));
        $removed = $omnicron->store()->prune(time() - ($days * 86400));

        $this->info('Pruned '.$removed.' run(s) older than '.$days.' days.');

        return self::SUCCESS;
    }
}
