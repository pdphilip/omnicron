<?php

namespace PDPhilip\OmniCron\Run;

/**
 * Who asked for this run.
 *
 * SCHEDULE is the tick finding the task due. Everything else is explicit
 * intent - which is why only SCHEDULE respects pause, environments() and
 * the post-lock due-ness recheck. RETRY is reserved for the auto-retry
 * roadmap; nothing emits it yet.
 */
enum Trigger: string
{
    case SCHEDULE = 'schedule';   // a tick found it due
    case DASHBOARD = 'dashboard'; // the dashboard's Run now button
    case ENDPOINT = 'endpoint';   // GET /omnicron/run/{task}
    case COMMAND = 'command';     // artisan omnicron:run
    case APP = 'app';             // your own code calling OmniCron::run()
    case RETRY = 'retry';         // reserved: automatic retry after failure

    public function isManual(): bool
    {
        return $this !== self::SCHEDULE;
    }
}
