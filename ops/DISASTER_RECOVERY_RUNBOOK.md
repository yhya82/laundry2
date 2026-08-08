# Disaster Recovery Runbook

## Scope

What to do if the primary MySQL database is lost, corrupted, or needs to be
rolled back to a known-good point. Does not cover application-server
recovery (stateless — redeploy from git) or Reverb/queue recovery (both
recoverable independently; no durable state lives there that isn't already
in MySQL).

## Confirmed targets (Phase 01, 2026-08-02)

- **RPO: 15 minutes.** ⚠️ **Not currently met by this runbook** — see
  "Known gap" below. The engineering default was confirmed at the planning
  stage, but the backup mechanism actually built runs once daily
  (`backup:run` at 02:00, see `bootstrap/app.php`), which gives an RPO of up
  to ~24 hours, not 15 minutes. Flagging this explicitly rather than quietly
  meeting a weaker bar than what was signed off on.
- **RTO: 4 hours.**
- **Replica/standby:** none — single-instance MySQL + backups is the
  accepted gap for a single-location launch (Phase 01 decision).

## Backup mechanism

- `php artisan backup:run` (scheduled daily at 02:00): `mysqldump
  --single-transaction --quick`, gzip-compressed, uploaded to the `s3`
  filesystem disk (any S3-compatible target — AWS S3, DigitalOcean Spaces,
  Backblaze B2, Cloudflare R2 — configured via `AWS_*` env vars, see
  `config/filesystems.php`). A `backups` row is written **only after the
  upload has actually succeeded**; a failed dump or failed upload instead
  writes a `status='failed'` row and emails whoever's set in Settings →
  Backup → Alert email.
- `php artisan backup:cleanup` (scheduled daily at 03:00): deletes backups
  (both the S3 object and the DB row) past `expires_at`, which was computed
  from `backup.retention_days` at the time each backup was created.

## Restore procedure

1. SSH into a host with access to both the target database and the backup
   bucket. **Never restore directly against production without deliberately
   deciding to** — `backup:restore` overwrites the target database
   wholesale, no partial/merge mode exists.
2. Run `ops/scripts/restore.sh` (or `php artisan backup:restore` directly).
   Omit the filename argument to pick from the 20 most recent backups
   interactively.
3. The command shows the target database name, host, and the backup's
   timestamp, then requires you to **type the exact database name** to
   proceed — a plain y/n prompt is deliberately not used here (see the
   comment on `RestoreBackup` for why: an unscoped `--env` flag on a
   different command already wiped this project's real dev database once,
   from a command with no confirmation guard at all).
4. On confirmation: downloads the backup, decompresses it, and pipes it into
   `mysql` against the current DB connection.
5. Verify application functionality against the restored data before
   declaring the incident resolved (login, dashboard, spot-check a few
   records) — the restore command's own success just means `mysql` didn't
   error, not that the data is what you expected.

## Known gap: RPO

Daily backups cannot meet a 15-minute RPO. Closing this gap for real would
need one of:
- MySQL binary log (binlog) archiving between full backups, replayed during
  restore up to the point of failure — the standard way to get point-in-time
  recovery without hourly+ full dumps.
- Or, simpler at this scale: increase `backup:run`'s schedule frequency and
  accept a coarser-but-still-much-better RPO (e.g. hourly gets to ~1hr, not
  15min, without binlog replay).

Not built in this pass — flagged as a real gap rather than silently building
against a weaker target than what Phase 01 actually signed off on.

## Drill log

### 2026-08-07 — mechanism rehearsal (dev environment)

Ran against a live MariaDB 10.4.32 instance, `laundry2_testing` database (a
dedicated, disposable database — not production, not the shared dev
database), with the `s3` disk faked to a local temp directory (no real S3
bucket exists in this environment — same "no live credentials, full pipeline
still proven" situation as this project's Twilio integration).

**What was actually exercised, end to end, with real binaries (no mocking of
mysqldump/gzip/mysql themselves):**

1. `backup:run` executed a real `mysqldump --single-transaction --quick`
   against `laundry2_testing`, gzip-compressed the output (8,661 bytes
   compressed, from 92,598 bytes of raw SQL), uploaded it, and only then
   wrote the `completed` backup row (size_bytes correctly recorded).
2. Downloaded and decompressed that exact file back to raw SQL — byte
   count and MariaDB dump header both confirmed intact.
3. Ran the real `mysql` CLI restore (via `RestoreBackup::restore()`,
   invoked directly, not the interactive wrapper) against the same
   database. **Restore succeeded with no errors.**
4. Post-restore sanity check: 41 tables present (matches pre-restore count),
   `migrations` table still has all 35 rows. No data loss, no corruption.
5. Full backup → download → decompress → restore cycle: **4.58 seconds**
   wall-clock, at this database's current (near-empty, dev-scale) size.
6. Separately verified (automated test, `tests/Feature/BackupCommandsTest.php`):
   the typed-confirmation guard correctly aborts on a mismatched database
   name, and `backup:run` correctly fails closed (no `completed` row, alert
   attempted) when no bucket is configured.
7. Confirmed the real dev database (`laundry2_db`) was never touched by any
   of the above — verified user/table counts before and after.

**What this drill does NOT prove:** restore time at production data volume.
4.58 seconds is a near-empty test database; a real production dataset will
take meaningfully longer to dump and restore. Re-run this drill against a
realistic data volume (or a copy of production) before treating the 4-hour
RTO as validated at scale, not just validated as a working mechanism.

**Outcome:** mechanism is sound and the 4-hour RTO is trivially met at
current scale. RPO gap (above) is real and unresolved. Recommend re-running
this drill at production data volume, and deciding on the RPO gap, before
this runbook is treated as launch-ready.
