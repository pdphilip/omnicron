<?php

namespace PDPhilip\OmniCron\Schedule;

/**
 * Best-effort English for a cron expression - covers every shape the fluent
 * builder produces, and hands back the raw expression for anything it cannot
 * say honestly. "Every 15 minutes" beats making an operator parse asterisks.
 */
class CronWords
{
    public static function toWords(string $expression, string $timezone = 'UTC'): string
    {
        $parts = preg_split('/\s+/', trim($expression));
        if (count($parts) !== 5) {
            return $expression;
        }

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;
        $suffix = $timezone === 'UTC' ? '' : ' ('.$timezone.')';
        $fixed = fn (string $field) => preg_match('/^\d+$/', $field) === 1;
        $clock = fn () => sprintf('%02d:%02d', (int) $hour, (int) $minute);

        // Every minute / every N minutes, all day every day
        if (($minute === '*' || str_starts_with($minute, '*/'))
            && $hour === '*' && $dayOfMonth === '*' && $month === '*' && $dayOfWeek === '*') {
            $step = $minute === '*' ? 1 : (int) substr($minute, 2);

            return ($step === 1 ? 'Every minute' : "Every {$step} minutes").$suffix;
        }

        if ($fixed($minute) && $dayOfMonth === '*' && $month === '*' && $dayOfWeek === '*') {
            if ($hour === '*') {
                return ((int) $minute === 0 ? 'Every hour' : 'Hourly at :'.sprintf('%02d', (int) $minute)).$suffix;
            }
            if (str_starts_with($hour, '*/')) {
                $step = (int) substr($hour, 2);

                return "Every {$step} hours at :".sprintf('%02d', (int) $minute).$suffix;
            }
            if ($fixed($hour)) {
                return 'Daily at '.$clock().$suffix;
            }
        }

        // Named weekdays at a clock time
        if ($fixed($minute) && $fixed($hour) && $dayOfMonth === '*' && $month === '*' && $dayOfWeek !== '*') {
            $days = self::dayNames($dayOfWeek);
            if ($days !== null) {
                return $days.' at '.$clock().$suffix;
            }
        }

        // Day-of-month at a clock time, monthly or every N months
        if ($fixed($minute) && $fixed($hour) && $fixed($dayOfMonth) && $dayOfWeek === '*') {
            $day = self::ordinal((int) $dayOfMonth);
            if ($month === '*') {
                return "Monthly on the {$day} at ".$clock().$suffix;
            }
            if (str_starts_with($month, '*/')) {
                return 'Every '.(int) substr($month, 2)." months on the {$day} at ".$clock().$suffix;
            }
        }

        return $expression;
    }

    private static function dayNames(string $dayOfWeek): ?string
    {
        if ($dayOfWeek === '1,2,3,4,5') {
            return 'Weekdays';
        }
        if ($dayOfWeek === '0,6' || $dayOfWeek === '6,0') {
            return 'Weekends';
        }

        $names = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
        $days = [];
        foreach (explode(',', $dayOfWeek) as $day) {
            if (preg_match('/^\d+$/', $day) !== 1 || ! isset($names[(int) $day])) {
                return null;
            }
            $days[] = $names[(int) $day].'s';
        }

        return implode(', ', $days);
    }

    private static function ordinal(int $day): string
    {
        $suffix = match (true) {
            $day % 100 >= 11 && $day % 100 <= 13 => 'th',
            $day % 10 === 1 => 'st',
            $day % 10 === 2 => 'nd',
            $day % 10 === 3 => 'rd',
            default => 'th',
        };

        return $day.$suffix;
    }
}
