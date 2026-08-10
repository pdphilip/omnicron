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

The secret is **opt-in**: leave `OMNICRON_SECRET` unset and the urls are public — fine for a zero-config heartbeat, since due-ness and locks decide what actually runs. Set it and every request must present it (services that only take a plain url can pass `?token=<secret>` instead of the header). **Set it** when any task is production-gated or should not be a stranger's button to press — `/omnicron/run/{task}` bypasses both the schedule and the environment gate.

**Or from the Laravel scheduler**, if your app already runs `schedule:run`:

```php
Schedule::command('omnicron:tick')->everyMinute();
```

All three triggers are interchangeable — and safe to combine. The tick decides due-ness itself, and a doubled tick — even simultaneous ticks from a fleet of parallel machines — can never double-run a task: each task takes an atomic lock, and the lock winner's rivals recheck due-ness before running.

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
| `omnicron:task {Name}` | Scaffold a new task class (into `task_namespace`, default `App\OmniCron`) |
| `omnicron:tick` | Run everything due. This is your one crontab entry. |
| `omnicron:run {task}` | Run one task now, ignoring schedule and environment |
| `omnicron:list` | Every registered task with its schedule and health |
| `omnicron:prune` | Trim finished run history past the retention window |

Every run records **what asked for it** - `schedule` (the tick), `dashboard`, `endpoint`, `command`, or `app` (your own code) - so "who ran this at 3am" is a stored fact, not a guess.

Tasks lock per key, so the same task never runs twice at once — including across multiple servers. Two requirements for a fleet: the cache store must support atomic locks (Redis, Memcached, DynamoDB, database), and every machine must point at the **same** one — locks in a per-machine cache (`file`, `array`) coordinate nothing. After winning a lock, a tick-driven run rechecks due-ness against the store, so simultaneous ticks cannot re-fire a fast task the winner already finished.

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

## The Run Log Lives Anywhere

Two stores, one interface:

```php
// config/omnicron.php  (or OMNICRON_STORE=redis)
'store' => 'database',   // durable, queryable - the default
'store' => 'redis',      // the Horizon way: zero migrations, history capped per task
```

Redis is the zero-friction start - nothing to migrate, nothing to install. Know the trade: shared Redis under memory pressure evicts a run log first, and the cap (`omnicron.redis.max_runs`) IS the retention policy. When "what did it return three weeks ago" starts mattering, graduate to the database store - which is just a model, and the model is swappable:

```php
'model' => PDPhilip\OmniCron\Run\Run::class,        // SQL - the default, migration included
'model' => PDPhilip\OmniCron\Run\MongoRun::class,   // MongoDB (mongodb/laravel-mongodb)
'model' => PDPhilip\OmniCron\Run\EsRun::class,      // Elasticsearch (pdphilip/elasticsearch)
```

Mongo needs no migration - collections are schemaless; declare indexes on `(task, started_at)`. Elasticsearch needs its index **mapped before the first run lands** (ES types fields on first write): `Schema::create('omnicron_runs', [EsRun::class, 'mappingDefinition'])`.

Or bring your own - any Eloquent flavour becomes the run log by wearing one trait:

```php
class OmniCronRun extends WhateverEloquentModel
{
    use \PDPhilip\OmniCron\Run\RunsLifecycle;
}
```

There is also `'connection' => 'mysql'` for the simpler case of pointing the bundled SQL model at a secondary connection.

---

## Two Models: the Registry and the Log

Your code defines the tasks; two models mirror them:

- **`CronJob`** — one row per registered task, created lazily. This is the operator's handle: it holds `paused` and `schedule_override`, the two things an operator may change without a deploy. Everything else stays in code.
- **The run model** (`Run` / `MongoRun` / `EsRun` / yours) — the log. Reached from the registry as a plain relationship.

```php
use PDPhilip\OmniCron\Job\CronJob;

CronJob::with('latestRun')->get();     // every cron and how it's doing
CronJob::query()->sole()->runs;        // a job's full history, newest first

OmniCron::pause($task);                     // skipped by the tick; manual runs still fire
OmniCron::resume($task);
OmniCron::overrideSchedule($task, '*/5 * * * *');  // wins over the code until cleared
OmniCron::overrideSchedule($task, null);           // back to what the code says
```

Pausing gates the **tick only** — a manual run is explicit intent, the same rule as `environments()`. An override must be a valid cron expression (invalid input throws; an invalid stored value is ignored rather than obeyed). `MongoCronJob` is bundled for Mongo apps — set `'job_model' => MongoCronJob::class` and pair it with `MongoRun` so the relationship stays same-connection. On the Redis store, operator state lives in a Redis hash — no models, same controls.

---

## Dashboard

`/omnicron/dashboard` — task health cards with the schedule in words, per-task uptime and a last-runs strip, the full run log with each run's returned JSON and a response-time chart, a queue of upcoming executions, manual triggers, pause/resume, and click-to-edit schedule overrides (the cron pill turns amber when overridden; clear it to restore the code's schedule). Every relative time ticks live. Served entirely by the package: no build step, no Inertia/Livewire coupling, works identically whatever your app's frontend is.

### Authorization — the Horizon convention

Open in `local`. Everywhere else the `viewOmniCron` gate must exist **and** pass — no gate means nobody gets in (fails closed).

`omnicron:install` drops `App\Providers\OmniCronServiceProvider` into your app and registers it — list the specific users who may enter:

```php
Gate::define('viewOmniCron', function ($user) {
    return in_array($user->email, [
        'you@example.com',
    ]);
});
```

Or swap the check for any rule your app already has:

```php
Gate::define('viewOmniCron', fn ($user) => $user->isAdmin());
```

Path and middleware live in `config('omnicron.dashboard')`.

---

## Roadmap

- **Queued tasks** — `queued()` exists on the base class and is reserved; v0 runs every task inline.
- **Notifications** — `notify()` from inside a task, plus automatic alerts after repeated failures.
- **Remote store** — POST run results to a collector service; the `RunStore` interface is ready for it.

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
