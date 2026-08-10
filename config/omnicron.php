<?php

use PDPhilip\OmniCron\Job\CronJob;
use PDPhilip\OmniCron\Run\Run;

return [

    /*
    |--------------------------------------------------------------------------
    | Tasks
    |--------------------------------------------------------------------------
    | Every registered task class. Create one with `php artisan omnicron:task`
    | and list it here - that is the whole registration. task_namespace is
    | where the scaffolder puts new classes (the directory derives from it:
    | App\Crons -> app/Crons).
    */
    'task_namespace' => 'App\\OmniCron',
    'tasks' => [
        // App\OmniCron\PurgeSessions::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Heartbeat endpoint
    |--------------------------------------------------------------------------
    | Point any scheduling service (or curl in a crontab) at GET
    | /{path}/tick once a minute with the X-OmniCron-Secret header (or
    | ?token=). No secret configured = every request refused - the endpoint
    | fails closed. /{path}/status reports health; /{path}/run/{task} fires
    | one task by hand.
    */
    'endpoint' => [
        'enabled' => true,
        'path' => 'omnicron',
        'secret' => env('OMNICRON_SECRET'),
        'middleware' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    | A self-contained dashboard at /{path} - task health, the run log with
    | each run's output, and manual triggers. Served entirely by the package
    | (no build step, no host-app coupling). Open in local; anywhere else a
    | `viewOmniCron` gate must exist and pass:
    |
    |   Gate::define('viewOmniCron', fn ($user) => $user->isAdmin());
    */
    'dashboard' => [
        'enabled' => true,
        'path' => 'omnicron/dashboard',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Run history
    |--------------------------------------------------------------------------
    | Where runs are logged. 'database' (default) is durable and queryable -
    | the point of keeping a log. 'redis' is the Horizon way: zero
    | migrations, history capped per task rather than kept - great for
    | trying the package; know that shared Redis under memory pressure
    | evicts a run log first.
    |
    | For 'database': 'connection' points the bundled Run model at a
    | non-default connection, and 'model' swaps the model entirely - Run
    | (SQL, default), MongoRun (requires mongodb/laravel-mongodb), EsRun
    | (requires pdphilip/elasticsearch - map the index first, see its
    | docblock), or any model of your own wearing RunsLifecycle.
    |
    | `omnicron:prune` enforces the retention window on durable stores.
    */
    'store' => env('OMNICRON_STORE', 'database'),
    'table' => 'omnicron_runs',
    'connection' => null,
    'model' => Run::class,

    /*
    |--------------------------------------------------------------------------
    | The job registry
    |--------------------------------------------------------------------------
    | One control row per registered task - where an operator pauses a job
    | or overrides its schedule without a deploy. CronJob::with('latestRun')
    | is the model view of "what crons exist and how are they doing";
    | $job->runs() is its log. MongoCronJob is bundled for Mongo apps
    | (pair it with MongoRun so the relationship stays same-connection).
    */
    'jobs_table' => 'omnicron_jobs',
    'job_model' => CronJob::class,
    'history' => [
        'keep_days' => 90,
    ],
    'redis' => [
        'connection' => null,
        'key_prefix' => 'omnicron:runs:',
        'max_runs' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Locking
    |--------------------------------------------------------------------------
    | Locks are per task and need an atomic-lock cache store (redis,
    | memcached, database, dynamodb). Null uses your default cache store.
    */
    'cache_store' => null,
    'lock_prefix' => 'omnicron:task:',

];
