<?php

namespace PDPhilip\OmniCron\Job;

use Illuminate\Database\Eloquent\Model;

/**
 * The registry of registered crons, SQL flavour - one row per task, synced
 * by the store on first touch. This is the model an app queries for "what
 * crons exist and how are they doing":
 *
 *   CronJob::with('latestRun')->get();
 *   $job->runs()->where('state', 'failed')->get();
 *
 * The row carries operator state (paused, schedule override); the code
 * keeps everything else. Non-SQL apps swap the flavour via
 * config('omnicron.job_model') - MongoCronJob is bundled.
 *
 * @property string $key
 * @property string $class
 * @property bool $paused
 * @property string|null $schedule_override
 */
class CronJob extends Model implements JobRow
{
    use JobLifecycle;

    public function getConnectionName(): ?string
    {
        return config('omnicron.connection') ?? parent::getConnectionName();
    }
}
