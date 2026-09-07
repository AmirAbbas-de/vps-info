-- Z2U Telegram monitor: initial schema.
-- All timestamps are ISO-8601 UTC strings unless noted.

CREATE TABLE IF NOT EXISTS app_meta (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL,
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

CREATE TABLE IF NOT EXISTS monitors (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  original_url TEXT NOT NULL,
  normalized_url TEXT NOT NULL UNIQUE,
  search_keyword TEXT,
  search_definition TEXT NOT NULL,
  search_fingerprint TEXT NOT NULL UNIQUE,
  enabled INTEGER NOT NULL DEFAULT 1,
  initialized INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
  last_checked_at TEXT,
  last_success_at TEXT,
  last_error TEXT,
  last_error_at TEXT,
  error_notified_at TEXT,
  check_lock_until TEXT
);

CREATE INDEX IF NOT EXISTS idx_monitors_enabled ON monitors(enabled, initialized);
CREATE INDEX IF NOT EXISTS idx_monitors_lock ON monitors(check_lock_until);

CREATE TABLE IF NOT EXISTS listings (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  monitor_id INTEGER NOT NULL REFERENCES monitors(id) ON DELETE CASCADE,
  external_id TEXT NOT NULL,
  title TEXT NOT NULL,
  seller TEXT,
  price_cents INTEGER NOT NULL,
  currency TEXT NOT NULL DEFAULT 'USD',
  url TEXT NOT NULL,
  availability TEXT NOT NULL DEFAULT 'available',
  first_seen_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
  last_seen_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
  UNIQUE(monitor_id, external_id)
);

CREATE INDEX IF NOT EXISTS idx_listings_monitor ON listings(monitor_id);
CREATE INDEX IF NOT EXISTS idx_listings_updated ON listings(updated_at);

CREATE TABLE IF NOT EXISTS price_history (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  listing_id INTEGER NOT NULL REFERENCES listings(id) ON DELETE CASCADE,
  price_cents INTEGER NOT NULL,
  currency TEXT NOT NULL DEFAULT 'USD',
  checked_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

CREATE INDEX IF NOT EXISTS idx_price_history_listing ON price_history(listing_id, checked_at DESC);

CREATE TABLE IF NOT EXISTS notification_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  monitor_id INTEGER NOT NULL REFERENCES monitors(id) ON DELETE CASCADE,
  listing_id INTEGER,
  event_type TEXT NOT NULL,
  event_key TEXT NOT NULL UNIQUE,
  event_value TEXT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

CREATE INDEX IF NOT EXISTS idx_notification_events_monitor ON notification_events(monitor_id, event_type, created_at DESC);

CREATE TABLE IF NOT EXISTS telegram_state (
  user_id INTEGER PRIMARY KEY,
  state TEXT NOT NULL,
  data TEXT,
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);
