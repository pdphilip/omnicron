<?php

namespace PDPhilip\OmniCron\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array<int, \PDPhilip\OmniCron\OmniTask> tasks()
 * @method static \PDPhilip\OmniCron\OmniTask|null find(string $key)
 * @method static bool isDue(\PDPhilip\OmniCron\OmniTask $task, ?int $now = null)
 * @method static array<int, \PDPhilip\OmniCron\OmniTask> due(?int $now = null)
 * @method static array run(\PDPhilip\OmniCron\OmniTask $task, bool $manual = false)
 * @method static array tick()
 * @method static array status()
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
