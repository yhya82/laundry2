#!/usr/bin/env bash
#
# Thin wrapper around `php artisan backup:run` -- the actual logic (dump,
# compress, upload, only-write-the-row-after-success) lives in
# app/Console/Commands/RunBackup.php, not here. This script exists so cron
# has a single, stable entry point independent of how the app is invoked.
#
# Usage: ops/scripts/backup.sh
# Exit code mirrors the artisan command: 0 on success, non-zero on failure.

set -euo pipefail

cd "$(dirname "$0")/../.."

php artisan backup:run
