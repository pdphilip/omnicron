<?php

namespace PDPhilip\OmniCron\Run;

use Illuminate\Database\Eloquent\Model;

/**
 * One run of one task - the durable log row, SQL flavour.
 *
 * The row is opened in RUNNING before the work begins and closed by
 * succeed()/fail() afterwards. A run that never closes is the crash
 * evidence the try/catch cannot produce.
 *
 * Non-SQL apps swap the whole model via config('omnicron.model') - see
 * RunsLifecycle for the five-line MongoDB version.
 *
 * @property mixed $id
 * @property string $task
 * @property RunState $state
 * @property int $started_at
 * @property int|null $finished_at
 * @property int|null $duration_ms
 * @property array|null $output
 * @property string|null $error
 * @property string|null $host
 * @property bool $manual
 */
class Run extends Model implements RunRow
{
    use RunsLifecycle;

    public function getConnectionName(): ?string
    {
        return config('omnicron.connection') ?? parent::getConnectionName();
    }
}
