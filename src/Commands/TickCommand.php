<?php

namespace PDPhilip\OmniCron\Commands;

use Illuminate\Console\Command;
use PDPhilip\OmniCron\OmniCron;

/**
 * The heartbeat as a command - for a crontab entry, the Laravel scheduler,
 * or a hand-run during development. Identical semantics to the url.
 */
class TickCommand extends Command
{
    protected $signature = 'omnicron:tick';

    protected $description = 'Check every task and run whatever is due';

    public function handle(OmniCron $omnicron): int
    {
        $result = $omnicron->tick();

        $this->line(sprintf(
            'Checked %d, due %d, ran %d, failed %d',
            $result['checked'], $result['due'], $result['ran'], $result['failed'],
        ));

        foreach ($result['tasks'] as $task) {
            $line = sprintf('  %s: %s', $task['task'], $task['state']);
            if (isset($task['duration_ms'])) {
                $line .= ' ('.$task['duration_ms'].'ms)';
            }
            if (isset($task['error'])) {
                $line .= ' - '.$task['error'];
            }
            $task['state'] === 'failed' ? $this->error($line) : $this->line($line);
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
