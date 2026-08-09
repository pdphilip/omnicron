<?php

namespace PDPhilip\OmniCron\Tests\Fixtures;

use PDPhilip\OmniCron\OmniTask;
use PDPhilip\OmniCron\Schedule\Schedule;
use RuntimeException;

class FailingTask extends OmniTask
{
    public function schedule(Schedule $schedule): void
    {
        $schedule->everyMinute();
    }

    public function execute(): array
    {
        throw new RuntimeException('the disk is on fire');
    }
}
