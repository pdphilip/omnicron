<?php

namespace PDPhilip\OmniCron\Run;

/**
 * The life of one run. RUNNING is written before the work starts, which is
 * the package's whole trick: a task that dies in a way it cannot report
 * (fatal, OOM, killed container) leaves a RUNNING row instead of no row -
 * silence becomes evidence.
 */
enum RunState: string
{
    case RUNNING = 'running';
    case OK = 'ok';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::RUNNING => 'Running',
            self::OK => 'OK',
            self::FAILED => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RUNNING => 'info',
            self::OK => 'success',
            self::FAILED => 'danger',
        };
    }

    public function isFinished(): bool
    {
        return $this !== self::RUNNING;
    }
}
