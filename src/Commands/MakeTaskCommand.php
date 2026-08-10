<?php

namespace PDPhilip\OmniCron\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Scaffold a task class - the one unified way a job is created, run and
 * managed. Lands wherever config('omnicron.task_namespace') points
 * (app/OmniCron by default); register it in config/omnicron.php and it is
 * scheduled, locked, logged and health-checked like everything else.
 */
class MakeTaskCommand extends Command
{
    protected $signature = 'omnicron:task {name : The task class name, e.g. PurgeSessions}';

    protected $description = 'Create a new OmniCron task class';

    public function handle(): int
    {
        $class = Str::studly($this->argument('name'));
        $namespace = trim(config('omnicron.task_namespace', 'App\\OmniCron'), '\\');
        $directory = app_path(str_replace('\\', '/', Str::after($namespace, 'App\\')));
        $path = $directory.'/'.$class.'.php';

        if (file_exists($path)) {
            $this->error($path.' already exists.');

            return self::FAILURE;
        }

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $stub = file_get_contents(__DIR__.'/stubs/task.php.stub');
        file_put_contents($path, str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$namespace, $class],
            $stub,
        ));

        $this->info('Task created: '.$path);
        $this->line('');
        $this->line('Register it in <comment>config/omnicron.php</comment>:');
        $this->line('');
        $this->line("    'tasks' => [");
        $this->line("        {$namespace}\\{$class}::class,");
        $this->line('    ],');

        return self::SUCCESS;
    }
}
