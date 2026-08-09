<?php

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
    | finished runs are kept. Schedule `omnicron:prune` - or just register
    | the bundled PruneRuns task - to enforce the window.
    */
    'table' => 'omnicron_runs',
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
