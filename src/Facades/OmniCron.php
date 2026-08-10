<?php

namespace PDPhilip\OmniCron\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array<int, \PDPhilip\OmniCron\OmniTask> tasks()
 * @method static \PDPhilip\OmniCron\OmniTask|null find(string $key)
 * @method static bool isDue(\PDPhilip\OmniCron\OmniTask $task, ?int $now = null)
 * @method static array<int, \PDPhilip\OmniCron\OmniTask> due(?int $now = null)
 * @method static array run(\PDPhilip\OmniCron\OmniTask $task, \PDPhilip\OmniCron\Run\Trigger $trigger = \PDPhilip\OmniCron\Run\Trigger::SCHEDULE)
 * @method static array tick()
 * @method static array status()
 * @method static string expressionFor(\PDPhilip\OmniCron\OmniTask $task)
 * @method static void pause(\PDPhilip\OmniCron\OmniTask $task)
 * @method static void resume(\PDPhilip\OmniCron\OmniTask $task)
 * @method static void overrideSchedule(\PDPhilip\OmniCron\OmniTask $task, ?string $expression)
 * @method static \PDPhilip\OmniCron\Store\RunStore store()
 *
 * @see \PDPhilip\OmniCron\OmniCron
 */
class OmniCron extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \PDPhilip\OmniCron\OmniCron::class;
    }
}
