<?php

namespace PDPhilip\OmniCron\Tests\Fixtures;

use PDPhilip\OmniCron\OmniTask;
use PDPhilip\OmniCron\Schedule\Schedule;

class ProductionOnlyTask extends OmniTask
{
    public function schedule(Schedule $schedule): void
    {
        $schedule->everyMinute();
    }

    public function execute(): array
    {
        return ['emails_sent' => 3];
    }

    public function environments(): ?array
    {
        return ['production'];
    }
}
