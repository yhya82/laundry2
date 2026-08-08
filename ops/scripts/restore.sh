#!/usr/bin/env bash
#
# Thin wrapper around `php artisan backup:restore` -- the typed-confirmation
# guard and the actual restore logic live in
# app/Console/Commands/RestoreBackup.php, not here.
#
# DESTRUCTIVE: overwrites the current DB connection's database wholesale.
# Never run this against production without deliberately confirming that's
# what you mean to do -- see ops/DISASTER_RECOVERY_RUNBOOK.md.
#
# Usage:
#   ops/scripts/restore.sh                    # pick a backup interactively
#   ops/scripts/restore.sh <filename>          # restore a specific backup

set -euo pipefail

cd "$(dirname "$0")/../.."

php artisan backup:restore "$@"
