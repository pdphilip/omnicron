# Changelog

All notable changes to `pdphilip/omnicron` are documented here.

## v0.1.0-beta.8 - 2026-08-10

### Changed

- **The endpoint secret is now opt-in** - no `OMNICRON_SECRET` means the
  heartbeat urls are public (previously refused with 503). The tick is
  idempotent either way; set the secret when any task is production-gated
  or should not be manually triggerable by strangers, since
  `/omnicron/run/{task}` bypasses both the schedule and the environment
  gate.

## v0.1.0-beta.7 - 2026-08-10

### Added

- `task_namespace` config - where `omnicron:task` scaffolds new classes,
  with the directory derived from it (`App\Crons` -> `app/Crons`).
  Default `App\OmniCron`, unchanged.

## v0.1.0-beta.6 - 2026-08-10

### Fixed

- **Parallel-fleet double-fire** - a tick-driven run now rechecks due-ness
  after winning the task lock. Before: two machines ticking the same
  second both saw a task due; the lock loser, arriving after a fast task
  had already finished and released its lock, ran it a second time. Now
  the recheck reads the moved last-start from the shared store and the
  loser returns `{ran: false, state: 'already_ran'}`, writing no row.
  Manual runs are untouched - they ignore the schedule by design.
  (Reminder: fleet safety requires every machine to share one
  atomic-lock cache store - redis, memcached, database, dynamodb.)

## v0.1.0-beta.5 - 2026-08-09

Two models: the registry and the log.

### Added

- **`CronJob` registry model** - one control row per registered task,
  created lazily. Holds the two things an operator may change without a
  deploy: `paused` and `schedule_override`. `CronJob::with('latestRun')`
  is the model view of "what crons exist and how are they doing";
  `$job->runs` is its full log - a plain relationship to whichever run
  model is configured. `MongoCronJob` bundled for Mongo apps
  (`omnicron.job_model`); on the Redis store, operator state lives in a
  Redis hash - same controls, no models.
- `OmniCron::pause()` / `resume()` - a paused task is skipped by the
  tick but still runs manually (explicit intent, the same rule as
  `environments()`).
- `OmniCron::overrideSchedule()` - an operator cron expression that wins
  over the code until cleared. Invalid input throws; an invalid stored
  value is ignored rather than obeyed.
- Dashboard: pause/resume per card, click-to-edit schedule override on
  the cron pill (amber when overridden, clear to restore), paused cards
  dimmed with a Paused badge.
- `status()` rows now carry `paused`, `schedule_overridden`, and
  `schedule_in_code` alongside the ruling `schedule`.
- New migration `create_omnicron_jobs_table` (published by
  `omnicron:install`).

## v0.1.0-beta.4 - 2026-08-09

### Added

- **Redis store** - `'store' => 'redis'` (or `OMNICRON_STORE=redis`): the
  Horizon way. Zero migrations, one capped list per task, the RUNNING row
  still written before the work starts. The cap is the retention policy -
  the durable database store remains the default for history that must
  survive.
- `RunRow` contract - the surface the engine touches. Eloquent flavours
  satisfy it through `RunsLifecycle`; the Redis store's rows implement it
  directly.

## v0.1.0-beta.3 - 2026-08-09

### Added

- **Dashboard** at `/omnicron/dashboard` - task health cards, the run log
  with each run's output expandable inline, and manual triggers. Served
  entirely by the package (single page, vanilla JS polling the JSON API) -
  no build step, no Inertia/Livewire coupling, works in any Laravel app.
  Horizon-style authorization: open in local, `viewOmniCron` gate required
  everywhere else (fails closed).
- `OmniCron::nextRunAt()` - when a task's schedule next comes round.

## v0.1.0-beta.2 - 2026-08-09

The run log lives anywhere Eloquent does.

### Added

- `omnicron.model` - the run log is a swappable model. Bundled flavours:
  `Run` (SQL, default), `MongoRun` (mongodb/laravel-mongodb), `EsRun`
  (pdphilip/elasticsearch, with `mappingDefinition()` - map the index
  before the first run lands)
- `RunsLifecycle` trait - any Eloquent flavour becomes the run log by
  wearing it
- `omnicron.connection` - point the bundled SQL model at a secondary
  connection
- `EsRun` closes runs through a targeted update rather than a second
  save on the same instance, which Elasticsearch can silently drop

## v0.1.0-beta.1 - 2026-08-09

First public beta. The core loop is complete: one heartbeat a minute, tasks
declare their own schedules, every run is logged durably.

### Added

- `OmniTask` base class - a task declares its schedule, does its work, and
  what it returns becomes the log
- Fluent `Schedule` builder that compiles to plain cron expressions
  (timezone-aware, with a raw `cron()` escape hatch)
- The runner: due-ness measured from last START, per-task atomic locks
  (multi-VM safe), the run row written BEFORE the work starts so a crash
  leaves evidence, `environments()` gating for side-effecting tasks
- Durable run history (`omnicron_runs`) behind a `RunStore` interface,
  database driver included
- Heartbeat endpoints: `GET /omnicron/tick`, `/omnicron/status`,
  `/omnicron/run/{task}` behind a fails-closed shared secret
  (`X-OmniCron-Secret` header or `?token=`)
- Commands: `omnicron:task` (scaffold), `omnicron:tick`, `omnicron:run`,
  `omnicron:list`, `omnicron:prune`, `omnicron:install`
- Health semantics: due, stale (measured from last SUCCESS, threshold derived
  as 2x the scheduled interval when undeclared), stuck (RUNNING past its
  lock window)
