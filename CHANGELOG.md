# Changelog

All notable changes to `pdphilip/omnicron` are documented here.

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
