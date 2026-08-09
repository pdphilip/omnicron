<?php

namespace PDPhilip\OmniCron\Tests\Fixtures;

use PDPhilip\OmniCron\OmniTask;
use PDPhilip\OmniCron\Schedule\Schedule;

class HourlyTask extends OmniTask
{
    public function schedule(Schedule $schedule): void
    {
        $schedule->everyHour();
    }

    public function execute(): array
    {
        return ['worked' => true];
    }
}
