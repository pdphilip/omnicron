<?php

use Illuminate\Support\Facades\Redis;
use PDPhilip\OmniCron\OmniCron;
use PDPhilip\OmniCron\Run\RunState;
use PDPhilip\OmniCron\Store\RedisStore;
use PDPhilip\OmniCron\Tests\Fixtures\FailingTask;
use PDPhilip\OmniCron\Tests\Fixtures\HourlyTask;

/**
 * Runs against a real Redis when one is reachable (database 15, flushed
 * per test); skips cleanly everywhere else so CI without Redis stays green.
 */
beforeEach(function () {
    config()->set('database.redis.client', 'predis');
    config()->set('database.redis.default', ['host' => '127.0.0.1', 'port' => 6379, 'database' => 15]);
    config()->set('omnicron.store', 'redis');
    config()->set('omnicron.tasks', [HourlyTask::class]);

    try {
        Redis::connection()->ping();
    } catch (Throwable) {
        $this->markTestSkipped('No Redis reachable at 127.0.0.1:6379');
    }

    // Database 15 is ours alone - flush beats fighting key prefixes.
    Redis::connection()->flushdb();
});

function redisEngine(): OmniCron
{
    return app(OmniCron::class);
}

it('resolves the redis store from config', function () {
    expect(redisEngine()->store())->toBeInstanceOf(RedisStore::class);
});

it('claims the run in redis BEFORE the work - same crash evidence as everywhere else', function () {
    $run = redisEngine()->store()->open(new HourlyTask);

    $latest = redisEngine()->store()->latestFor(new HourlyTask);
    expect($latest->state)->toBe(RunState::RUNNING)
        ->and($latest->getKey())->toBe($run->getKey());
});

it('runs a task end to end through redis - output, duration, dueness', function () {
    $result = redisEngine()->run(new HourlyTask);
    expect($result['state'])->toBe('ok');

    $latest = redisEngine()->store()->latestFor(new HourlyTask);
    expect($latest->state)->toBe(RunState::OK)
        ->and($latest->output)->toBe(['worked' => true])
        ->and($latest->duration_ms)->not->toBeNull();

    // Dueness reads the redis last-start: just ran, hourly - not due.
    expect(redisEngine()->isDue(new HourlyTask))->toBeFalse();
});

it('records failures with their error', function () {
    config()->set('omnicron.tasks', [FailingTask::class]);
    redisEngine()->run(new FailingTask);

    $latest = redisEngine()->store()->latestFor(new FailingTask);
    expect($latest->state)->toBe(RunState::FAILED)
        ->and($latest->error)->toBe('the disk is on fire');
});

it('caps history per task - the cap is the retention contract', function () {
    config()->set('omnicron.redis.max_runs', 3);

    for ($i = 0; $i < 5; $i++) {
        redisEngine()->store()->open(new HourlyTask);
    }

    expect(redisEngine()->store()->history(new HourlyTask, 50))->toHaveCount(3);
});

it('prunes finished runs but keeps RUNNING rows', function () {
    $store = redisEngine()->store();

    $old = $store->open(new HourlyTask);
    $old->started_at = time() - 200 * 86400;
    $store->rewrite($old);
    $old->succeed(['done' => 1], microtime(true));

    $abandoned = $store->open(new HourlyTask);
    $abandoned->started_at = time() - 200 * 86400;
    $store->rewrite($abandoned);

    expect($store->prune(time() - 90 * 86400))->toBe(1)
        ->and($store->history(new HourlyTask, 10))->toHaveCount(1)
        ->and($store->history(new HourlyTask, 10)->first()->state)->toBe(RunState::RUNNING);
});
