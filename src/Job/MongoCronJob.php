<?php

namespace PDPhilip\OmniCron\Job;

use MongoDB\Laravel\Eloquent\Model;

/**
 * The registry on MongoDB. Requires mongodb/laravel-mongodb.
 *
 *   // config/omnicron.php
 *   'job_model' => PDPhilip\OmniCron\Job\MongoCronJob::class,
 *
 * Pair it with MongoRun as the log model and the runs() relationship stays
 * same-connection. Schemaless - no migration; a unique index on `key` is
 * worth declaring.
 */
class MongoCronJob extends Model implements JobRow
{
    use JobLifecycle;
}
