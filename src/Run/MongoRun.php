<?php

namespace PDPhilip\OmniCron\Run;

use MongoDB\Laravel\Eloquent\Model;

/**
 * The run log on MongoDB. Requires mongodb/laravel-mongodb.
 *
 *   // config/omnicron.php
 *   'model' => PDPhilip\OmniCron\Run\MongoRun::class,
 *
 * Collections are schemaless, so skip the migration. Declare indexes on
 * (task, started_at) and (task, state, started_at) however your app manages
 * Mongo indexes - the store sorts and filters on exactly those.
 */
class MongoRun extends Model implements RunRow
{
    use RunsLifecycle;
}
