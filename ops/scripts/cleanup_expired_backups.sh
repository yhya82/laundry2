#!/usr/bin/env bash
#
# Thin wrapper around `php artisan backup:cleanup`. Retention comes from the
# backup.retention_days setting (Settings -> Backup tab), baked into each
# backup row's expires_at at creation time -- not read fresh from here.
#
# Usage: ops/scripts/cleanup_expired_backups.sh

set -euo pipefail

cd "$(dirname "$0")/../.."

php artisan backup:cleanup
