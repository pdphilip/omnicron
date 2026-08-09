<?php

namespace PDPhilip\OmniCron\Commands;

use Illuminate\Console\Command;
use PDPhilip\OmniCron\OmniCron;

/** Health per task: what runs when, and whether it is due, stale or stuck. */
class ListCommand extends Command
{
    protected $signature = 'omnicron:list';

    protected $description = 'List every registered task with its schedule and health';

    public function handle(OmniCron $omnicron): int
    {
        $status = $omnicron->status();

        if (! $status['tasks']) {
            $this->warn('No tasks registered. Create one with omnicron:task and add it to config/omnicron.php.');

            return self::SUCCESS;
        }

        $this->table(
            ['Task', 'Schedule', 'TZ', 'Last state', 'Last success', 'Health'],
            array_map(fn ($row) => [
                $row['task'],
                $row['schedule'],
                $row['timezone'],
                $row['last_state'] ?? 'never run',
                $row['last_success_at'] ? date('Y-m-d H:i', $row['last_success_at']).' UTC' : '-',
                $this->health($row),
            ], $status['tasks']),
        );

        if ($status['stuck'] > 0) {
            $this->error($status['stuck'].' task(s) stuck mid-run.');
        }
        if ($status['stale'] > 0) {
            $this->warn($status['stale'].' task(s) stale.');
        }

        return self::SUCCESS;
    }

    private function health(array $row): string
    {
        return match (true) {
            $row['is_stuck'] => '<fg=red>stuck</>',
            $row['last_state'] === 'failed' => '<fg=red>failed</>',
            $row['is_stale'] => '<fg=yellow>stale</>',
            $row['is_due'] => 'due',
            default => '<fg=green>ok</>',
        };
    }
}
