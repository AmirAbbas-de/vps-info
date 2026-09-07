import type { D1Database } from "@cloudflare/workers-types";
import type {
  ListingRow,
  MonitorRow,
  NotificationEventRow,
  TelegramStateRow,
} from "../types/db";
import type { Z2uListing } from "../types/z2u";

export function nowIso(): string {
  return new Date().toISOString();
}

export function addMillisToIso(iso: string, ms: number): string {
  return new Date(new Date(iso).getTime() + ms).toISOString();
}

export interface NewMonitor {
  name: string;
  original_url: string;
  normalized_url: string;
  search_keyword: string | null;
  search_definition: string;
  search_fingerprint: string;
}

export async function getSeedDone(db: D1Database): Promise<boolean> {
  const row = await db
    .prepare("SELECT value FROM app_meta WHERE key = ?")
    .bind("initial_seed_done")
    .first<{ value: string } | null>();
  return row?.value === "1";
}

export async function setSeedDone(db: D1Database): Promise<void> {
  await db
    .prepare(
      "INSERT INTO app_meta (key, value, updated_at) VALUES (?, ?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at"
    )
    .bind("initial_seed_done", "1", nowIso())
    .run();
}

export async function countMonitors(db: D1Database): Promise<number> {
  const row = await db.prepare("SELECT COUNT(*) AS count FROM monitors").first<{ count: number }>();
  return Number(row?.count ?? 0);
}

export async function getAllMonitors(db: D1Database): Promise<MonitorRow[]> {
  const result = await db.prepare("SELECT * FROM monitors ORDER BY id ASC").all<MonitorRow>();
  return result.results ?? [];
}

export async function getMonitorById(db: D1Database, id: number): Promise<MonitorRow | null> {
  return db.prepare("SELECT * FROM monitors WHERE id = ?").bind(id).first<MonitorRow | null>();
}

export async function findMonitorByNormalizedUrl(
  db: D1Database,
  normalizedUrl: string
): Promise<MonitorRow | null> {
  return db
    .prepare("SELECT * FROM monitors WHERE normalized_url = ?")
    .bind(normalizedUrl)
    .first<MonitorRow | null>();
}

export async function findMonitorByFingerprint(
  db: D1Database,
  fingerprint: string
): Promise<MonitorRow | null> {
  return db
    .prepare("SELECT * FROM monitors WHERE search_fingerprint = ?")
    .bind(fingerprint)
    .first<MonitorRow | null>();
}

export async function insertMonitor(
  db: D1Database,
  monitor: NewMonitor,
  enabled = true,
  initialized = false
): Promise<MonitorRow> {
  const now = nowIso();
  await db
    .prepare(
      `INSERT INTO monitors
         (name, original_url, normalized_url, search_keyword, search_definition,
          search_fingerprint, enabled, initialized, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`
    )
    .bind(
      monitor.name,
      monitor.original_url,
      monitor.normalized_url,
      monitor.search_keyword,
      monitor.search_definition,
      monitor.search_fingerprint,
      enabled ? 1 : 0,
      initialized ? 1 : 0,
      now,
      now
    )
    .run();
  const row = await db
    .prepare("SELECT * FROM monitors WHERE normalized_url = ?")
    .bind(monitor.normalized_url)
    .first<MonitorRow | null>();
  if (!row) {
    throw new Error("Monitor insert succeeded but row could not be reloaded.");
  }
  return row;
}

export async function setMonitorEnabled(db: D1Database, id: number, enabled: boolean): Promise<void> {
  await db
    .prepare("UPDATE monitors SET enabled = ?, updated_at = ? WHERE id = ?")
    .bind(enabled ? 1 : 0, nowIso(), id)
    .run();
}

export async function deleteMonitor(db: D1Database, id: number): Promise<void> {
  await db.prepare("DELETE FROM monitors WHERE id = ?").bind(id).run();
}

export async function getEnabledMonitors(db: D1Database): Promise<MonitorRow[]> {
  const result = await db
    .prepare("SELECT * FROM monitors WHERE enabled = 1 ORDER BY id ASC")
    .all<MonitorRow>();
  return result.results ?? [];
}

export async function acquireMonitorLock(db: D1Database, id: number, lockUntilIso: string): Promise<boolean> {
  const now = nowIso();
  const result = await db
    .prepare(
      `UPDATE monitors
       SET check_lock_until = ?, updated_at = ?
       WHERE id = ? AND (check_lock_until IS NULL OR check_lock_until < ?)`
    )
    .bind(lockUntilIso, now, id, now)
    .run();
  return Number(result.meta?.changes ?? 0) > 0;
}

export async function releaseMonitorLock(db: D1Database, id: number): Promise<void> {
  await db
    .prepare("UPDATE monitors SET check_lock_until = NULL, updated_at = ? WHERE id = ?")
    .bind(nowIso(), id)
    .run();
}

export async function markCheckStarted(db: D1Database, id: number): Promise<void> {
  await db
    .prepare("UPDATE monitors SET last_checked_at = ?, updated_at = ? WHERE id = ?")
    .bind(nowIso(), nowIso(), id)
    .run();
}

export async function markCheckSuccess(db: D1Database, id: number): Promise<void> {
  const now = nowIso();
  await db
    .prepare(
      "UPDATE monitors SET last_success_at = ?, last_error = NULL, last_error_at = NULL, updated_at = ? WHERE id = ?"
    )
    .bind(now, now, id)
    .run();
}

export async function setMonitorError(
  db: D1Database,
  id: number,
  errorMessage: string,
  errorNotified = false
): Promise<void> {
  const now = nowIso();
  await db
    .prepare(
      `UPDATE monitors
       SET last_error = ?, last_error_at = ?,
           error_notified_at = CASE WHEN ? = 1 THEN ? ELSE error_notified_at END,
           updated_at = ?
       WHERE id = ?`
    )
    .bind(errorMessage, now, errorNotified ? 1 : 0, now, now, id)
    .run();
}

export async function markErrorNotified(db: D1Database, id: number): Promise<void> {
  await db
    .prepare("UPDATE monitors SET error_notified_at = ?, updated_at = ? WHERE id = ?")
    .bind(nowIso(), nowIso(), id)
    .run();
}

export async function getMonitorListings(db: D1Database, monitorId: number): Promise<ListingRow[]> {
  const result = await db
    .prepare("SELECT * FROM listings WHERE monitor_id = ? ORDER BY id ASC")
    .bind(monitorId)
    .all<ListingRow>();
  return result.results ?? [];
}

export async function upsertListing(
  db: D1Database,
  monitorId: number,
  listing: Z2uListing
): Promise<ListingRow> {
  const now = nowIso();
  await db
    .prepare(
      `INSERT INTO listings
         (monitor_id, external_id, title, seller, price_cents, currency, url,
          availability, first_seen_at, last_seen_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
       ON CONFLICT(monitor_id, external_id) DO UPDATE SET
         title = excluded.title,
         seller = excluded.seller,
         price_cents = excluded.price_cents,
         currency = excluded.currency,
         url = excluded.url,
         availability = excluded.availability,
         last_seen_at = excluded.last_seen_at,
         updated_at = excluded.updated_at`
    )
    .bind(
      monitorId,
      listing.externalId,
      listing.title,
      listing.seller,
      listing.priceCents,
      listing.currency,
      listing.url,
      listing.availability,
      now,
      now,
      now
    )
    .run();
  const row = await db
    .prepare(
      "SELECT * FROM listings WHERE monitor_id = ? AND external_id = ? LIMIT 1"
    )
    .bind(monitorId, listing.externalId)
    .first<ListingRow | null>();
  if (!row) {
    throw new Error("Listing upsert succeeded but row could not be reloaded.");
  }
  return row;
}

export async function insertPriceHistory(
  db: D1Database,
  listingId: number,
  priceCents: number,
  currency: string
): Promise<void> {
  await db
    .prepare(
      `INSERT INTO price_history (listing_id, price_cents, currency, checked_at)
       VALUES (?, ?, ?, ?)`
    )
    .bind(listingId, priceCents, currency, nowIso())
    .run();
}

export async function insertNotificationEventIfAbsent(
  db: D1Database,
  monitorId: number,
  listingId: number | null,
  eventType: string,
  eventKey: string,
  eventValue: string | null
): Promise<boolean> {
  const existing = await db
    .prepare("SELECT id FROM notification_events WHERE event_key = ? LIMIT 1")
    .bind(eventKey)
    .first<{ id: number } | null>();
  if (existing) {
    return false;
  }
  const created = await db
    .prepare(
      `INSERT OR IGNORE INTO notification_events
         (monitor_id, listing_id, event_type, event_key, event_value, created_at)
       VALUES (?, ?, ?, ?, ?, ?)`
    )
    .bind(monitorId, listingId, eventType, eventKey, eventValue, nowIso())
    .run();
  return Number(created.meta?.changes ?? 0) > 0;
}

export async function getNotificationEventCount(db: D1Database): Promise<number> {
  const row = await db.prepare("SELECT COUNT(*) AS count FROM notification_events").first<{ count: number }>();
  return Number(row?.count ?? 0);
}

export async function getTelegramState(db: D1Database, userId: number): Promise<TelegramStateRow | null> {
  return db
    .prepare("SELECT * FROM telegram_state WHERE user_id = ?")
    .bind(userId)
    .first<TelegramStateRow | null>();
}

export async function setTelegramState(
  db: D1Database,
  userId: number,
  state: string,
  data: string | null
): Promise<void> {
  await db
    .prepare(
      `INSERT INTO telegram_state (user_id, state, data, updated_at)
       VALUES (?, ?, ?, ?)
       ON CONFLICT(user_id) DO UPDATE SET state = excluded.state, data = excluded.data, updated_at = excluded.updated_at`
    )
    .bind(userId, state, data, nowIso())
    .run();
}

export async function clearTelegramState(db: D1Database, userId: number): Promise<void> {
  await db.prepare("DELETE FROM telegram_state WHERE user_id = ?").bind(userId).run();
}

export async function getLatestNotificationEvent(
  db: D1Database
): Promise<NotificationEventRow | null> {
  return db
    .prepare("SELECT * FROM notification_events ORDER BY id DESC LIMIT 1")
    .first<NotificationEventRow | null>();
}
