import type { D1Database } from "@cloudflare/workers-types";
import type { MonitorRow } from "../types/db";
import type { Env } from "../types/env";
import type { Z2uParseResult, Z2uSearchDefinition } from "../types/z2u";
import { fetchZ2uSearchPage } from "../z2u/client";
import { parseSearchResults } from "../z2u/parser";
import { buildZ2uSearchUrl, createSearchDefinition } from "../z2u/search";
import {
  acquireMonitorLock,
  addMillisToIso,
  getEnabledMonitors,
  getMonitorById,
  getMonitorListings,
  insertNotificationEventIfAbsent,
  insertPriceHistory,
  markCheckStarted,
  markCheckSuccess,
  markErrorNotified,
  nowIso,
  releaseMonitorLock,
  setMonitorError,
  upsertListing,
} from "../db/queries";
import { authorizedChatIds, type TelegramAuthConfig } from "../telegram/auth";
import { diffListings, rowToStoredSnapshot } from "./listing-service";
import { notifyNewListing, notifyPriceDrop, sendTelegramMessage } from "./notification-service";
import { structuredLog, type LogLevel } from "../utils/logger";

export interface CheckResult {
  monitorId: number;
  status: "success" | "error" | "locked" | "skipped";
  listings: number;
  new: number;
  priceDrops: number;
  durationMs: number;
  error?: string;
}

export interface BatchCheckResult {
  results: CheckResult[];
  errorCount: number;
}

const LOCK_DURATION_MS = 10 * 60 * 1000;
const ERROR_NOTIFY_THROTTLE_MS = 60 * 60 * 1000;

export interface MonitorRuntimeConfig {
  maxMonitorsPerRun: number;
  maxZ2uPages: number;
  logLevel: LogLevel;
  lockDurationMs: number;
}

export function monitorRuntimeConfig(env: Env): MonitorRuntimeConfig {
  return {
    maxMonitorsPerRun: Math.max(1, Number(env.MAX_MONITORS_PER_CRON_RUN ?? "3") || 3),
    maxZ2uPages: Math.max(1, Number(env.MAX_Z2U_PAGES ?? "1") || 1),
    logLevel: (env.LOG_LEVEL as LogLevel) ?? "info",
    lockDurationMs: LOCK_DURATION_MS,
  };
}

export function parseStoredSearchDefinition(value: string): Z2uSearchDefinition {
  try {
    const parsed = JSON.parse(value) as {
      keyword?: string;
      basePath?: string;
      extraParams?: Record<string, string>;
      originalExpiryMs?: number | null;
    };
    return createSearchDefinition({
      keyword: parsed.keyword ?? "",
      basePath: parsed.basePath ?? "/ItemStore.html",
      extraParams: parsed.extraParams ?? {},
      originalExpiryMs: parsed.originalExpiryMs ?? undefined,
    });
  } catch {
    return createSearchDefinition({
      keyword: value,
      basePath: "/ItemStore.html",
      extraParams: {},
    });
  }
}

function configFor(env: Env): TelegramAuthConfig {
  return {
    allowedUserIds: env.TELEGRAM_ALLOWED_USER_IDS ?? "",
    chatId: env.TELEGRAM_CHAT_ID,
  };
}

async function shouldNotifyError(db: D1Database, monitorId: number): Promise<boolean> {
  const monitor = await getMonitorById(db, monitorId);
  if (!monitor) {
    return false;
  }
  const last = monitor.error_notified_at ? new Date(monitor.error_notified_at).getTime() : null;
  if (last === null || Number.isNaN(last)) {
    return true;
  }
  return nowIso() > addMillisToIso(new Date(last).toISOString(), ERROR_NOTIFY_THROTTLE_MS);
}

async function notifyErrorIfNeeded(env: Env, db: D1Database, monitor: MonitorRow, message: string): Promise<void> {
  if (!(await shouldNotifyError(db, monitor.id))) {
    return;
  }
  const chatIds = authorizedChatIds(configFor(env));
  if (chatIds.length === 0 || !env.TELEGRAM_BOT_TOKEN) {
    return;
  }
  const text =
    `⚠️ <b>Z2U Monitor Error</b>\n\n` +
    `Monitor: ${monitor.name.replace(/</g, "&lt;").replace(/>/g, "&gt;")}\n` +
    `Error: ${message.replace(/</g, "&lt;").replace(/>/g, "&gt;")}`;
  for (const chatId of chatIds) {
    await sendTelegramMessage(env.TELEGRAM_BOT_TOKEN, chatId, text, { parseMode: "HTML" });
  }
  await markErrorNotified(db, monitor.id);
}

/**
 * Run one monitor check. Uses a D1-based periodic lock so overlapping cron
 * invocations cannot corrupt the same monitor.
 */
export async function checkMonitor(env: Env, monitorId: number): Promise<CheckResult> {
  const db = env.DB;
  const started = Date.now();
  const lockUntil = addMillisToIso(nowIso(), monitorRuntimeConfig(env).lockDurationMs);
  const lockAcquired = await acquireMonitorLock(db, monitorId, lockUntil);
  if (!lockAcquired) {
    return {
      monitorId,
      status: "locked",
      listings: 0,
      new: 0,
      priceDrops: 0,
      durationMs: Date.now() - started,
    };
  }

  let monitor: MonitorRow | null = null;
  try {
    monitor = await getMonitorById(db, monitorId);
    if (!monitor) {
      await releaseMonitorLock(db, monitorId);
      return {
        monitorId,
        status: "skipped",
        listings: 0,
        new: 0,
        priceDrops: 0,
        durationMs: Date.now() - started,
      };
    }

    await markCheckStarted(db, monitorId);
    structuredLog("info", "monitor_check_started", { monitor_id: monitorId }, env.LOG_LEVEL);

    const definition = parseStoredSearchDefinition(monitor.search_definition);
    const fetchUrl = buildZ2uSearchUrl(definition, env, Date.now());
    const fetchResult = await fetchZ2uSearchPage(fetchUrl, env, env.LOG_LEVEL);
    const parseResult: Z2uParseResult = parseSearchResults(fetchResult.html, fetchUrl);
    const scanCompletenessChecked = monitorInitialized(monitor)
      ? parseResult.listings.length > 0 || parseResult.hasZeroResults
      : true;

    const storedRows = await getMonitorListings(db, monitorId);
    const stored = rowToStoredSnapshot(storedRows);
    const firstSnapshot = !monitorInitialized(monitor);
    const diff = diffListings(stored, parseResult.listings, firstSnapshot);
    const chatIds = authorizedChatIds(configFor(env));

    // Persist every scanned listing and record price history for new or
    // changed prices. Unchanged prices do not write redundant history rows.
    for (const listing of parseResult.listings) {
      const row = await upsertListing(db, monitorId, listing);
      const previous = stored.find((s) => s.externalId === listing.externalId);
      if (!previous || previous.priceCents !== listing.priceCents) {
        await insertPriceHistory(db, row.id, listing.priceCents, listing.currency);
      }
    }

    // New listings.
    let newCount = 0;
    for (const listing of diff.newNotificationCandidates) {
      const row = await upsertListing(db, monitorId, listing);
      const eventKey = `new:${monitorId}:${listing.externalId}`;
      const inserted = await insertNotificationEventIfAbsent(
        db,
        monitorId,
        row.id,
        "new_listing",
        eventKey,
        listing.title
      );
      if (inserted && env.TELEGRAM_BOT_TOKEN && chatIds.length > 0 && scanCompletenessChecked) {
        await notifyNewListing(env.TELEGRAM_BOT_TOKEN, chatIds, monitor, listing);
      }
      if (inserted) {
        newCount += 1;
      }
    }

    // Price drops.
    let dropCount = 0;
    for (const drop of diff.priceDropCandidates) {
      const row = await upsertListing(db, monitorId, drop.listing);
      const eventKey = `price_drop:${monitorId}:${drop.listing.externalId}:${drop.listing.priceCents}`;
      const inserted = await insertNotificationEventIfAbsent(
        db,
        monitorId,
        row.id,
        "price_drop",
        eventKey,
        String(drop.listing.priceCents)
      );
      if (inserted && env.TELEGRAM_BOT_TOKEN && chatIds.length > 0 && scanCompletenessChecked) {
        await notifyPriceDrop(env.TELEGRAM_BOT_TOKEN, chatIds, monitor, drop);
      }
      if (inserted) {
        dropCount += 1;
      }
    }

    await setMonitorInitialized(db, monitorId, true);
    await markCheckSuccess(db, monitorId);
    await releaseMonitorLock(db, monitorId);

    structuredLog(
      "info",
      "monitor_check_completed",
      {
        monitor_id: monitorId,
        listings: parseResult.listings.length,
        new: newCount,
        price_drops: dropCount,
        duration_ms: Date.now() - started,
      },
      env.LOG_LEVEL
    );

    return {
      monitorId,
      status: "success",
      listings: parseResult.listings.length,
      new: newCount,
      priceDrops: dropCount,
      durationMs: Date.now() - started,
    };
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    structuredLog("error", "monitor_check_failed", { monitor_id: monitorId, error: message }, env.LOG_LEVEL);
    await setMonitorError(db, monitorId, message);
    if (monitor) {
      await notifyErrorIfNeeded(env, db, monitor, message);
    }
    await releaseMonitorLock(db, monitorId);
    return {
      monitorId,
      status: "error",
      listings: 0,
      new: 0,
      priceDrops: 0,
      durationMs: Date.now() - started,
      error: message,
    };
  } finally {
    await releaseMonitorLock(db, monitorId).catch(() => undefined);
  }
}

export async function setMonitorInitialized(db: D1Database, monitorId: number, initialized: boolean): Promise<void> {
  await db
    .prepare("UPDATE monitors SET initialized = ?, updated_at = ? WHERE id = ?")
    .bind(initialized ? 1 : 0, nowIso(), monitorId)
    .run();
}

function monitorInitialized(monitor: MonitorRow): boolean {
  return monitor.initialized === 1;
}

export async function runChecks(
  env: Env,
  monitorIds?: number[]
): Promise<BatchCheckResult> {
  const config = monitorRuntimeConfig(env);
  let ids = monitorIds;
  if (!ids || ids.length === 0) {
    const monitors = await getEnabledMonitors(env.DB);
    ids = monitors.slice(0, config.maxMonitorsPerRun).map((m) => m.id);
  } else {
    ids = ids.slice(0, config.maxMonitorsPerRun);
  }

  const results: CheckResult[] = [];
  let errorCount = 0;
  for (const id of ids) {
    const result = await checkMonitor(env, id);
    results.push(result);
    if (result.status === "error") {
      errorCount += 1;
    }
  }
  return { results, errorCount };
}
