<?php

namespace PDPhilip\OmniCron\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Scaffold a task class - the one unified way a job is created, run and
 * managed. Lands wherever config('omnicron.task_namespace') points
 * (App\CronJobs by default); register it in config/omnicron.php and it is
 * scheduled, locked, logged and health-checked like everything else.
 */
class MakeTaskCommand extends Command
{
    protected $signature = 'omnicron:task {name : The task class name, e.g. PurgeSessions}';

    protected $description = 'Create a new OmniCron task class';

    public function handle(): int
    {
        $class = Str::studly($this->argument('name'));
        $namespace = trim(config('omnicron.task_namespace') ?: 'App\\CronJobs', '\\');
        $directory = $this->directoryFor($namespace);
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

    /**
     * Where the class file belongs, read off the app's psr-4 map rather than
     * assumed. An app that keeps App\ in src/App gets src/App/CronJobs; assuming
     * app_path() would drop the class in a directory the autoloader never
     * looks at, and the failure would only show up as a missing class later.
     */
    private function directoryFor(string $namespace): string
    {
        foreach ($this->psr4Roots() as $prefix => $root) {
            if (! str_starts_with($namespace.'\\', $prefix)) {
                continue;
            }

            $tail = str_replace('\\', '/', substr($namespace, strlen($prefix)));

            return base_path(trim($root, '/').($tail ? '/'.$tail : ''));
        }

        return app_path(str_replace('\\', '/', Str::after($namespace, 'App\\')));
    }

    /**
     * psr-4 prefixes longest first, so App\CronJobs\ wins over a broader App\.
     *
     * @return array<string, string>
     */
    private function psr4Roots(): array
    {
        $composer = base_path('composer.json');

        if (! is_file($composer)) {
            return [];
        }

        $roots = json_decode(file_get_contents($composer), true)['autoload']['psr-4'] ?? [];
        $roots = array_map(fn ($root) => is_array($root) ? reset($root) : $root, $roots);
        uksort($roots, fn ($a, $b) => strlen($b) <=> strlen($a));

        return $roots;
    }
}
