#!/usr/bin/env bash
# Create google form order tables on the desktop SQLite database.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DATA_DIR="${FOODEZA_DATA_DIR:-$HOME/Library/Application Support/com.foodeza.bookkeeping}"
DB="$DATA_DIR/database.sqlite"

mkdir -p "$DATA_DIR"
touch "$DB"

echo "Using database: $DB"

cd "$ROOT/api"
FOODEZA_DATA_DIR="$DATA_DIR" php artisan foodeza:ensure-google-form-tables
FOODEZA_DATA_DIR="$DATA_DIR" php artisan migrate --force

echo "Done. Restart the app (stop with Ctrl+C, then npm run dev)."
