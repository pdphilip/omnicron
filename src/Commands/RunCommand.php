<?php

namespace PDPhilip\OmniCron\Commands;

use Illuminate\Console\Command;
use PDPhilip\OmniCron\OmniCron;
use PDPhilip\OmniCron\Run\Trigger;

/**
 * Fire one task by hand. Manual runs bypass the environment gate - a human
 * at a terminal is explicit intent, which is exactly what the gate exists
 * to require.
 */
class RunCommand extends Command
{
    protected $signature = 'omnicron:run {task : The task key, e.g. purge-sessions}';

    protected $description = 'Run one task now, regardless of its schedule';

    public function handle(OmniCron $omnicron): int
    {
        $task = $omnicron->find($this->argument('task'));

        if (! $task) {
            $this->error('Unknown task: '.$this->argument('task'));
            $this->line('Registered: '.implode(', ', array_map(fn ($t) => $t->key(), $omnicron->tasks())));

            return self::FAILURE;
        }

        $result = $omnicron->run($task, Trigger::COMMAND);

        if (! $result['ran']) {
            $this->warn($task->key().' is already running elsewhere (locked).');

            return self::FAILURE;
        }

        if ($result['state'] === 'failed') {
            $this->error($task->key().' failed after '.$result['duration_ms'].'ms: '.$result['error']);

            return self::FAILURE;
        }

        $this->info($task->key().' ran in '.$result['duration_ms'].'ms');
        $this->line(json_encode($result['output'], JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
