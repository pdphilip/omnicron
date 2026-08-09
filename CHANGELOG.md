# Changelog

All notable changes to `pdphilip/omnicron` are documented here.

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
