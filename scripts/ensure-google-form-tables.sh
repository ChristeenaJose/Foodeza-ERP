#!/usr/bin/env bash
# Create google form order tables on the desktop SQLite database.
# Works even when artisan commands are missing — falls back to raw SQL.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DATA_DIR="${FOODEZA_DATA_DIR:-$HOME/Library/Application Support/com.foodeza.bookkeeping}"
DB="$DATA_DIR/database.sqlite"

mkdir -p "$DATA_DIR"
touch "$DB"

echo "Using database: $DB"

sqlite3 "$DB" <<'SQL'
CREATE TABLE IF NOT EXISTS google_form_order_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  name varchar NOT NULL,
  location varchar NOT NULL,
  event_date date NOT NULL,
  start_time varchar NOT NULL,
  end_time varchar NOT NULL,
  confirmation_template TEXT NOT NULL,
  notes TEXT,
  created_at datetime,
  updated_at datetime
);
CREATE TABLE IF NOT EXISTS google_form_order_updates (
  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  event_id INTEGER NOT NULL,
  title varchar,
  message TEXT NOT NULL,
  last_sent_at datetime,
  created_at datetime,
  updated_at datetime
);
SQL

# Upgrade/create orders table with event_id when needed.
HAS_EVENT_ID="$(sqlite3 "$DB" "SELECT COUNT(*) FROM pragma_table_info('google_form_orders') WHERE name='event_id';" 2>/dev/null || echo 0)"
HAS_ORDERS="$(sqlite3 "$DB" "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='google_form_orders';" 2>/dev/null || echo 0)"

if [[ "$HAS_ORDERS" == "0" || "$HAS_EVENT_ID" == "0" ]]; then
  sqlite3 "$DB" <<'SQL'
DROP TABLE IF EXISTS google_form_orders;
CREATE TABLE google_form_orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  event_id INTEGER NOT NULL,
  reference varchar NOT NULL,
  customer_id INTEGER,
  submitted_on date NOT NULL,
  event_date date NOT NULL,
  service_type varchar,
  items TEXT NOT NULL,
  guest_count INTEGER,
  location varchar,
  status varchar NOT NULL DEFAULT 'new',
  form_response_id varchar NOT NULL,
  raw_payload TEXT,
  confirmation_sent_at datetime,
  notes TEXT,
  created_at datetime,
  updated_at datetime
);
CREATE UNIQUE INDEX IF NOT EXISTS google_form_orders_reference_unique ON google_form_orders (reference);
CREATE UNIQUE INDEX IF NOT EXISTS google_form_orders_event_id_form_response_id_unique ON google_form_orders (event_id, form_response_id);
SQL
fi

echo "Tables now:"
sqlite3 "$DB" ".tables" | tr ' ' '\n' | grep google || true

if [[ -f "$ROOT/api/artisan" ]]; then
  cd "$ROOT/api"
  FOODEZA_DATA_DIR="$DATA_DIR" php artisan migrate --force >/dev/null 2>&1 || true
fi

echo "Done. Restart the app (Ctrl+C, then npm run dev)."
