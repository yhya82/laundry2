# Production Setup Baseline

Concrete, ready-to-apply configuration for a production deploy. Written
against what's actually in this repo right now (verified below), not a
generic checklist — where something is already correctly configured in code,
that's noted as done rather than repeated as a to-do.

## TLS enforcement (app ↔ database)

**Already wired in code, not yet turned on** — `config/database.php` already
supports a TLS connection to MySQL out of the box:

```php
'options' => extension_loaded('pdo_mysql') ? array_filter([
    PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
]) : [],
```

Nothing here needs a code change. To actually enforce TLS in production:

1. Get the CA certificate for your MySQL provider (RDS: `rds-ca-2019-root.pem`
   from AWS; a self-managed server: your own CA or the server's cert if
   self-signed and you've pinned it deliberately).
2. Set in the production `.env`:
   ```
   MYSQL_ATTR_SSL_CA=/etc/ssl/certs/mysql-ca.pem
   ```
3. On the MySQL server side, require TLS for the application's account
   specifically (least-privilege, matching the same philosophy as
   `ops_grants.sql` — don't loosen this app-wide if other accounts don't
   need it):
   ```sql
   ALTER USER 'laundry_app'@'%' REQUIRE SSL;
   ```
4. Verify: `SHOW STATUS LIKE 'Ssl_cipher';` from a session opened by the app
   should show a non-empty cipher, not blank.

Not done in this pass because there's no real production MySQL target in
this environment to point a real CA cert at — this is the concrete procedure
for whoever stands one up.

## Pinned UTC

**Already correct, verified, no action needed.**

- `config/app.php`: `'timezone' => 'UTC'` — this is what Carbon/`now()`
  and all timestamp columns use application-side.
- Confirm the database server itself agrees: `SELECT @@global.time_zone,
  @@session.time_zone;` should show `+00:00` or `UTC`, not `SYSTEM`. If a
  production MySQL server's `SYSTEM` timezone isn't already UTC, either set
  `default-time-zone='+00:00'` in `my.cnf` (see below) or run `mysql_tzinfo_to_sql`
  to load the named zone tables and set `default-time-zone='UTC'`.
- Confirm the OS/container the app and MySQL run on is itself set to UTC
  (`timedatectl` on Linux) — belt-and-suspenders; the app doesn't depend on
  it, but a mismatched host clock makes log correlation across services
  painful during an incident.

## my.cnf baseline

A starting point, not exhaustive — tuned for a single-instance MySQL 8 /
MariaDB deploy at the scale this app currently targets (Phase 01: no
multi-branch, no partitioning yet). Revisit the buffer pool size and
connection limits once real traffic/data volume is known; the values below
are conservative defaults, not a capacity-planned number.

```ini
[mysqld]
# --- Timezone (see above) ---
default-time-zone = '+00:00'

# --- Monitoring baseline (what MonitorHealth's checks read) ---
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 1
log_queries_not_using_indexes = 0   # noisy without much signal at this scale; revisit if it becomes useful

# --- Character set (matches this app's utf8mb4 migrations) ---
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# --- Connection/resource limits (conservative starting point) ---
max_connections = 150
wait_timeout = 600
interactive_timeout = 600

# --- InnoDB ---
innodb_buffer_pool_size = 1G        # size to ~60-70% of available RAM on a dedicated DB host; this is a floor, not a target
innodb_flush_log_at_trx_commit = 1  # full durability -- do not weaken this for a system that handles payments
innodb_file_per_table = 1

# --- Binary logging (needed if/when the RPO gap in the DR runbook gets closed via binlog PITR) ---
log_bin = /var/log/mysql/mysql-bin.log
binlog_expire_logs_seconds = 604800  # 7 days
server-id = 1
```

**Do not weaken `innodb_flush_log_at_trx_commit`** for performance — this
app's payment/credit triggers (`trg_payments_cap_guard`,
`trg_credit_transactions_overdraft_guard`, etc.) assume committed data is
actually durable. Trading that away for write throughput would be a real
correctness risk for a system tracking money, not just a performance
tradeoff.

## Alerting

Backup failures and health-check problems (`health:monitor`, scheduled
every 5 minutes — see `bootstrap/app.php`) email whoever's configured in
Settings → Backup → Alert email. No additional infrastructure needed for
this — it reuses the app's existing mail configuration (`MAIL_MAILER` etc.
in `.env`). Set `MAIL_MAILER` to a real transport (not `log`) in production,
or alerts will only ever reach the log file, not an inbox.

## What's still open after this document

- The RPO gap noted in `ops/DISASTER_RECOVERY_RUNBOOK.md` (daily backups
  vs. a 15-minute target) — `log_bin` above is the prerequisite for closing
  it via binlog point-in-time recovery, not the fix itself.
- A real TLS cert and a real production MySQL target to point
  `MYSQL_ATTR_SSL_CA` at.
- Re-running the DR drill at production data volume, not just the
  near-empty rehearsal already logged.
