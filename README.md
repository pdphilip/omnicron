<div align="center">

# OmniCron for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/pdphilip/omnicron.svg?style=flat-square)](https://packagist.org/packages/pdphilip/omnicron)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/pdphilip/omnicron/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/pdphilip/omnicron/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/pdphilip/omnicron/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/pdphilip/omnicron/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/pdphilip/omnicron.svg?style=flat-square)](https://packagist.org/packages/pdphilip/omnicron)

Unify your cron jobs into self-describing tasks with a run history you can actually query.

</div>

```php
class CloseBillingCycles extends OmniTask
{
    public function schedule(Schedule $schedule): void
    {
        $schedule->everyMonday()->everyHour(5)->everyMinute(10);
    }

    public function execute(): array
    {
        return ['closed' => BillingCycle::closeExpired()];
    }
}
```

That is the whole task. It knows when it runs, it does one thing, and **what it returns becomes the log**.

Run your crons in the Kernel by hand, or do it the OmniCron way. Both is also allowed, though at that point you are just making work for yourself.

---

## Why

A `schedule:run` entry in crontab tells you nothing. Something fired, or it didn't. When a customer asks why last Tuesday's invoices went out late, you have no answer, because there is nothing to look at.

OmniCron keeps the record:

| | |
|---|---|
| **What it returned** | `{"closed": 6}` stored as JSON per run, not scraped stdout |
| **How long it took** | so you notice the job that has quietly gone from 2s to 40s |
| **When it last succeeded** | separately from when it last *ran* |
| **What died mid-flight** | the run is recorded **before** the work starts, so a fatal leaves evidence instead of silence |

That last one is the point. A task that throws can report itself. A task that gets OOM-killed, times out, or has its container reaped cannot — and those are exactly the failures worth knowing about. OmniCron writes the row first, so an unfinished run is a visible fact rather than an absence.

---

## Requirements

| | Version |
|---|---|
| PHP | 8.2+ |
| Laravel | 10, 11, 12 |

---

## Installation

```bash
composer require pdphilip/omnicron
```

```bash
php artisan omnicron:install
```

---

## Quick Start

### 1. Create a task

```bash
php artisan omnicron:task PurgeSessions
```

```php
namespace App\OmniCron;

use PDPhilip\OmniCron\OmniTask;
use PDPhilip\OmniCron\Schedule\Schedule;

class PurgeSessions extends OmniTask
{
    public function schedule(Schedule $schedule): void
    {
        $schedule->everyHour();
    }

    public function execute(): array
    {
        $deleted = Session::expired()->delete();

        return ['deleted' => $deleted];
    }
}
```

### 2. Register it

```php
// config/omnicron.php
'tasks' => [
    App\OmniCron\PurgeSessions::class,
    App\OmniCron\CloseBillingCycles::class,
],
```

### 3. Give it a heartbeat

One heartbeat a minute, forever. New tasks are just new classes — nothing outside your codebase ever changes again.

**From crontab:**

```
* * * * * cd /path-to-your-project && php artisan omnicron:tick >> /dev/null 2>&1
```

**Or from any scheduling service** (FastCron, cron-job.org, UptimeRobot, a GitHub Action) — point it at the tick url once a minute:

```
GET https://your-app.com/omnicron/tick
X-OmniCron-Secret: <OMNICRON_SECRET>
```

Set `OMNICRON_SECRET` in your `.env`. The endpoint **fails closed** — no secret configured means every request is refused. Services that only take a plain url can pass `?token=<secret>` instead of the header.

The JSON the tick returns says exactly what ran and what each task returned — external services that log responses are now capturing your run results remotely, for free:

```json
{
  "checked": 5, "due": 2, "ran": 2, "failed": 0,
  "tasks": [
    { "task": "purge-sessions", "ran": true, "state": "ok", "duration_ms": 214, "output": { "deleted": 118 } },
    { "task": "close-billing-cycles", "ran": true, "state": "ok", "duration_ms": 891, "output": { "closed": 6 } }
  ]
}
```

Two more urls, same secret: `/omnicron/status` reports health per task (due, stale, stuck mid-run), and `/omnicron/run/{task}` fires one by hand.

---

## Scheduling

Chains read coarse to fine, the way you would say it out loud.

```php
$schedule->everyMonday()->everyHour(5)->everyMinute(10);
```

> Every Monday, every 5 hours, every 10 minutes within that hour.

Each chain compiles to a plain cron expression, so it can be shown on the dashboard, pasted into crontab.guru, and understood by someone who has never seen this package.

| Chain | Expression |
|---|---|
| `everyMinute()` | `* * * * *` |
| `everyMinute(10)` | every 10th minute |
| `everyHour()` | `0 * * * *` |
| `everyHour(6)` | every 6th hour, on the hour |
| `everyMonday()` | `0 0 * * 1` |
| `everyWeekday()->at('06:00')` | `0 6 * * 1,2,3,4,5` |
| `everyWeekend()->everyHour(2)` | every 2 hours, Saturday and Sunday |
| `everyMonth(3)->dayAt(1)->at('04:00')` | quarterly, 1st at 04:00 |

### Defaults are the quiet part

A bare schedule means **midnight daily**. So `everyMonday()` is Monday at 00:00, not 1,440 runs across Monday.

Calling a finer method widens the coarser ones only where you have not spoken:

```php
$schedule->everyMinute();                 // * * * * *      hour widened for you
$schedule->everyHour(6)->everyMinute(10); // every 10 min, every 6th hour
```

Your explicit `everyHour(6)` survives the later `everyMinute(10)`. Anything you set stays set.

### Timezones

UTC unless you say otherwise.

```php
$schedule->everyDay()->at('06:00')->timezone('Africa/Johannesburg');
```

### When the fluent form cannot say it

Some intervals have no cron form — "every 90 minutes" is the classic. Rather than quietly doing something else, OmniCron throws, and leaves you an escape hatch:

```php
$schedule->cron('0 0 1 */3 *');
```

---

## Task Options

Everything below is optional. Override only what you need.

```php
class SendInvoices extends OmniTask
{
    public function schedule(Schedule $schedule): void
    {
        $schedule->everyDay()->at('07:00');
    }

    public function execute(): array
    {
        return ['sent' => Invoice::sendDue()];
    }

    // Only ever run this from a tick in production. A scheduler that behaves
    // identically everywhere will eventually email your customers from a laptop.
    public function environments(): ?array
    {
        return ['production'];
    }

    // Long job - give it room before it is presumed dead
    public function lockSeconds(): int
    {
        return 900;
    }

}
```

| Method | Default | Purpose |
|---|---|---|
| `key()` | kebab of the class name | Stable id for locks, logs and urls |
| `label()` | headline of the class name | Human name on the dashboard |
| `description()` | `null` | Longer note |
| `lockSeconds()` | `300` | How long one run may hold its lock |
| `staleAfterSeconds()` | derived | When to flag it overdue |
| `environments()` | anywhere | Which environments a **tick** may run it in |

---

## Return Something Useful

The array from `execute()` is the log. Three weeks later it is the only thing standing between you and a shrug.

```php
return ['deleted' => 0];                        // a fact
return ['sent' => 12, 'skipped' => 3];          // better
return [];                                      // tells you nothing
```

Counts of zero are information. An empty return is not.

**Throw to fail.** OmniCron records the message and marks the run failed. Do not catch and return an error shape — a failure that reports itself as success is worse than a crash.

---

## Running

| Command | What it does |
|---|---|
| `omnicron:task {Name}` | Scaffold a new task class |
| `omnicron:tick` | Run everything due. This is your one crontab entry. |
| `omnicron:run {task}` | Run one task now, ignoring schedule and environment |
| `omnicron:list` | Every registered task with its schedule and health |
| `omnicron:prune` | Trim finished run history past the retention window |

Tasks lock per key, so the same task never runs twice at once — including across multiple servers, provided your cache driver supports atomic locks (Redis, Memcached, DynamoDB, database).

---

## Configuration

```php
return [
    // Every registered task class
    'tasks' => [],

    // The heartbeat urls: /{path}/tick, /{path}/status, /{path}/run/{task}
    'endpoint' => [
        'enabled' => true,
        'path' => 'omnicron',
        'secret' => env('OMNICRON_SECRET'),
        'middleware' => [],
    ],

    // Run history
    'table' => 'omnicron_runs',
    'history' => [
        'keep_days' => 90,
    ],

    // Locks are per task and need an atomic-lock cache store
    // (redis, memcached, database, dynamodb). Null = your default store.
    'cache_store' => null,
    'lock_prefix' => 'omnicron:task:',
];
```

---

## Roadmap

- **Queued tasks** — `queued()` exists on the base class and is reserved; v0 runs every task inline.
- **Notifications** — `notify()` from inside a task, plus automatic alerts after repeated failures.
- **Dashboard** — health per task and the run history with each task's returned JSON.
- **Alternate stores** — the run log sits behind a `RunStore` interface; Redis and remote drivers can slot in without touching the runner.

## Not a Scheduler Replacement

OmniCron deliberately does not reimplement `Illuminate\Console\Scheduling`. There is no `between()`, no `withoutOverlapping()` chain, no sub-minute frequency — the heartbeat is a hard floor of one minute.

It is a small, opinionated registry for the handful of recurring jobs that matter, with strong observability around them. If you need the full scheduler, use the full scheduler. They coexist fine.

---

## Changelog

See [CHANGELOG](CHANGELOG.md).

## Credits

- [David Philip](https://github.com/pdphilip)

## License

MIT. See [LICENSE](LICENSE).
