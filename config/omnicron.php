<?php

use PDPhilip\OmniCron\Run\Run;

return [

    /*
    |--------------------------------------------------------------------------
    | Tasks
    |--------------------------------------------------------------------------
    | Every registered task class. Create one with `php artisan omnicron:task`
    | and list it here - that is the whole registration.
    */
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
    | Run history
    |--------------------------------------------------------------------------
    | Where runs are logged (the migration creates this table) and how long
    | finished runs are kept. Schedule `omnicron:prune` to enforce the window.
    |
    | 'connection' points the bundled Run model at a non-default database
    | connection. 'model' swaps the model entirely: Run (SQL, default),
    | MongoRun (requires mongodb/laravel-mongodb), EsRun (requires
    | pdphilip/elasticsearch - map the index first, see its docblock), or
    | any model of your own wearing RunsLifecycle.
    */
    'table' => 'omnicron_runs',
    'connection' => null,
    'model' => Run::class,
    'history' => [
        'keep_days' => 90,
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
