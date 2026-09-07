import type { D1Database } from "@cloudflare/workers-types";
import type { MonitorRow } from "../types/db";
import type { Env } from "../types/env";
import type {
  TelegramCallbackQuery,
  TelegramMessage,
  TelegramUpdate,
} from "../types/telegram";
import {
  clearTelegramState,
  countMonitors,
  deleteMonitor,
  findMonitorByFingerprint,
  findMonitorByNormalizedUrl,
  getAllMonitors,
  getEnabledMonitors,
  getMonitorById,
  getSeedDone,
  getTelegramState,
  insertMonitor,
  setMonitorEnabled,
  setTelegramState,
} from "../db/queries";
import { getWebhookInfo } from "./api";
import { authConfigFor, isAuthorized } from "./auth";
import { addCancelKeyboard, monitorListKeyboard, removeConfirmKeyboard } from "./keyboards";
import { answerTelegramCallback, escapeHtml, sendTelegramMessage } from "../services/notification-service";
import { checkMonitor, runChecks } from "../services/monitor-service";
import { ensureSeeded } from "../services/seed-service";
import { normalizeKeyword, normalizeZ2uMonitorUrl } from "../z2u/search";
import { structuredLog } from "../utils/logger";

function replyText(
  env: Env,
  chatId: number,
  text: string,
  replyMarkup?: Record<string, unknown>
): Promise<boolean> {
  return sendTelegramMessage(env.TELEGRAM_BOT_TOKEN ?? "", chatId, text, {
    parseMode: "HTML",
    ...(replyMarkup ? { replyMarkup } : {}),
  });
}

function commandToken(text: string): string | null {
  const trimmed = text.trim();
  if (!trimmed.startsWith("/")) {
    return null;
  }
  const space = trimmed.search(/\s/);
  return (space === -1 ? trimmed : trimmed.slice(0, space)).toLowerCase();
}

function commandArgument(text: string): string {
  const trim = text.trim();
  const space = trim.search(/\s/);
  return space === -1 ? "" : trim.slice(space + 1).trim();
}

function extractUrl(text: string): string | null {
  const match = /https?:\/\/[^\s]+/i.exec(text);
  return match ? match[0] : null;
}

function titleCaseKeyword(keyword: string): string {
  const words = normalizeKeyword(keyword).split(" ");
  return words
    .map((word) => {
      if (["plus", "gpt"].includes(word)) {
        return word.toUpperCase();
      }
      return word.charAt(0).toUpperCase() + word.slice(1);
    })
    .join(" ");
}

function statusLabel(monitor: MonitorRow): string {
  if (monitor.enabled !== 1) return "Disabled";
  return "Active";
}

async function sendStart(env: Env, chatId: number): Promise<void> {
  await replyText(
    env,
    chatId,
    "👋 <b>Z2U Telegram Monitor</b>\n\n" +
      "I monitor Z2U ItemStore searches for new listings and price drops.\n\n" +
      "Commands:\n" +
      "/start — show this help\n" +
      "/help — show help\n" +
      "/add — add a Z2U search URL\n" +
      "/list — list monitors\n" +
      "/remove &lt;id&gt; — remove a monitor\n" +
      "/enable &lt;id&gt; — enable a monitor\n" +
      "/disable &lt;id&gt; — disable a monitor\n" +
      "/check [id] — check selected or all active monitors\n" +
      "/status — show operational status"
  );
}

async function sendHelp(env: Env, chatId: number): Promise<void> {
  await replyText(
    env,
    chatId,
    "<b>Z2U Telegram Monitor Help</b>\n\n" +
      "Private bot; only authorized users can use it.\n\n" +
      "/add\n" +
      "Paste a https://www.z2u.com/ItemStore.html?... URL. The bot decodes the search parameter, detects duplicates, stores a stable definition and immediately runs an initial snapshot.\n\n" +
      "/list\n" +
      "Shows all monitors with inline buttons to check, enable/disable, or delete.\n\n" +
      "/check\n" +
      "Checks all active monitors (limited by MAX_MONITORS_PER_CRON_RUN).\n\n" +
      "/status\n" +
      "Shows D1 health, monitor count, cron interval, and Telegram webhook state."
  );
}

async function sendList(env: Env, chatId: number): Promise<void> {
  const monitors = await getAllMonitors(env.DB);
  if (monitors.length === 0) {
    await replyText(env, chatId, "No monitors configured yet. Use /add.");
    return;
  }
  const lines = monitors
    .map((m, index) => {
      const last = m.last_success_at ? m.last_success_at.slice(0, 19).replace("T", " ") : "never";
      return `${index + 1}. ${escapeHtml(m.name)} — ${statusLabel(m)} (id ${m.id}, last ${last})`;
    })
    .join("\n");
  await replyText(env, chatId, `<b>Configured monitors</b>\n\n${lines}`, monitorListKeyboard(monitors));
}

async function setAddState(env: Env, userId: number, chatId: number): Promise<void> {
  await setTelegramState(env.DB, userId, "awaiting_z2u_url", null);
  await replyText(
    env,
    chatId,
    "Please send the Z2U <b>ItemStore search URL</b> you want to monitor.\n\n" +
      "Example:\n" +
      "https://www.z2u.com/ItemStore.html?search=...",
    addCancelKeyboard()
  );
}

async function addMonitorFromUrl(env: Env, chatId: number, rawUrl: string): Promise<void> {
  try {
    const normalized = normalizeZ2uMonitorUrl(rawUrl);
    const byUrl = await findMonitorByNormalizedUrl(env.DB, normalized.normalizedUrl);
    if (byUrl) {
      await replyText(
        env,
        chatId,
        `⚠️ Duplicate URL. Monitor id ${byUrl.id} ("${escapeHtml(byUrl.name)}") already uses this URL.`
      );
      return;
    }
    const byKeyword = await findMonitorByFingerprint(env.DB, normalized.searchDefinition.fingerprint);
    if (byKeyword) {
      await replyText(
        env,
        chatId,
        `⚠️ Equivalent search already exists as monitor id ${byKeyword.id} ("${escapeHtml(byKeyword.name)}"). ` +
          "No duplicate was created."
      );
      return;
    }

    const name =
      normalized.searchDefinition.keyword === "chatgpt plus" || normalized.searchDefinition.keyword === "chatgpt+plus"
        ? "ChatGPT Plus"
        : titleCaseKeyword(normalized.searchDefinition.keyword);

    const inserted = await insertMonitor(
      env.DB,
      {
        name,
        original_url: normalized.originalUrl,
        normalized_url: normalized.normalizedUrl,
        search_keyword: normalized.searchDefinition.keyword,
        search_definition: normalized.searchDefinition.stableDefinition,
        search_fingerprint: normalized.searchDefinition.fingerprint,
      },
      true,
      false
    );

    await replyText(
      env,
      chatId,
      `✅ Added monitor #${inserted.id} "${escapeHtml(inserted.name)}".\nRunning initial baseline check…`
    );

    const result = await checkMonitor(env, inserted.id);
    if (result.status === "success") {
      await replyText(
        env,
        chatId,
        `✅ Initial snapshot complete.\n\nMonitor: ${escapeHtml(inserted.name)}\nListings: ${result.listings}\nNew alerts during initial snapshot: none (baseline only)\nDuration: ${(result.durationMs / 1000).toFixed(1)}s`
      );
    } else {
      const err = result.error ?? "unknown error";
      await replyText(
        env,
        chatId,
        `⚠️ Monitor added, but the initial baseline check failed.\n\nError: ${escapeHtml(err)}`
      );
    }
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    await replyText(env, chatId, `❌ Could not add monitor.\n\n${escapeHtml(message)}`);
  }
}

async function handleRemove(env: Env, chatId: number, arg: string): Promise<void> {
  const id = Number(arg);
  if (!Number.isFinite(id) || !Number.isInteger(id) || id <= 0) {
    await replyText(env, chatId, "Usage: /remove &lt;monitor_id&gt;");
    return;
  }
  const monitor = await getMonitorById(env.DB, id);
  if (!monitor) {
    await replyText(env, chatId, "No monitor with that id.");
    return;
  }
  await deleteMonitor(env.DB, id);
  await replyText(env, chatId, `🗑 Removed monitor #${id} "${escapeHtml(monitor.name)}".`);
}

async function handleToggle(env: Env, chatId: number, arg: string, enable: boolean): Promise<void> {
  const id = Number(arg);
  if (!Number.isFinite(id) || !Number.isInteger(id) || id <= 0) {
    await replyText(env, chatId, `Usage: /${enable ? "enable" : "disable"} &lt;monitor_id&gt;`);
    return;
  }
  const monitor = await getMonitorById(env.DB, id);
  if (!monitor) {
    await replyText(env, chatId, "No monitor with that id.");
    return;
  }
  await setMonitorEnabled(env.DB, id, enable);
  await replyText(
    env,
    chatId,
    `✅ Monitor #${id} "${escapeHtml(monitor.name)}" ${enable ? "enabled" : "disabled"}.`
  );
}

async function handleCheckCommand(env: Env, chatId: number, arg: string): Promise<void> {
  await replyText(env, chatId, "Running checks…");
  const started = Date.now();
  let result;
  if (arg) {
    const id = Number(arg);
    if (!Number.isFinite(id)) {
      await replyText(env, chatId, "Usage: /check [monitor_id]");
      return;
    }
    result = { results: [await checkMonitor(env, id)], errorCount: 0 };
  } else {
    result = await runChecks(env);
  }
  const lines = result.results.map((r) => {
    if (r.status === "error") {
      return `Monitor #${r.monitorId}: ❌ ${escapeHtml(r.error ?? "error")}`;
    }
    if (r.status === "locked") {
      return `Monitor #${r.monitorId}: ⏳ skipped (already running)`;
    }
    return `Monitor #${r.monitorId}: ✅ ${r.listings} listings, ${r.new} new, ${r.priceDrops} drops (${(r.durationMs / 1000).toFixed(1)}s)`;
  });
  await replyText(
    env,
    chatId,
    `Check completed.\n\n${lines.join("\n")}\n\nTotal duration: ${((Date.now() - started) / 1000).toFixed(1)}s`
  );
}

async function handleStatus(env: Env, chatId: number): Promise<void> {
  let dbOk = true;
  try {
    await env.DB.prepare("SELECT 1 AS ok").first();
  } catch {
    dbOk = false;
  }
  const active = (await getEnabledMonitors(env.DB)).length;
  const total = await countMonitors(env.DB);
  const monitors = await getAllMonitors(env.DB);
  const lastSuccess = monitors
    .map((m) => m.last_success_at)
    .filter((t): t is string => Boolean(t))
    .sort()
    .pop() ?? "never";

  let webhook = "not configured";
  if (env.TELEGRAM_BOT_TOKEN) {
    const info = await getWebhookInfo(env.TELEGRAM_BOT_TOKEN);
    if (info) {
      webhook = info.url ? String(info.url) : info.pending_update_count ? `unset (${info.pending_update_count} pending)` : "unset";
    }
  }

  await replyText(
    env,
    chatId,
    `<b>Z2U Monitor Status</b>\n\n` +
      `Worker: ✅ operating\n` +
      `D1: ${dbOk ? "✅ operational" : "❌ error"}\n` +
      `Monitors: ${active} active / ${total} total\n` +
      `Cron interval: ${env.CHECK_INTERVAL_MINUTES ?? "5"} minutes\n` +
      `Last successful check: ${escapeHtml(lastSuccess)}\n` +
      `Telegram webhook: ${escapeHtml(webhook)}`
  );
}

async function handleMessage(env: Env, message: TelegramMessage): Promise<void> {
  const userId = message.from?.id;
  const chatId = message.chat.id;
  if (userId === undefined) {
    return;
  }

  if (!isAuthorized(authConfigFor(env), userId)) {
    structuredLog("warn", "telegram_unauthorized_message", { chat_id: chatId }, env.LOG_LEVEL);
    return;
  }

  await ensureSeeded(env.DB).catch((error) =>
    structuredLog("error", "seed_failed", { error: String(error) }, env.LOG_LEVEL)
  );

  const text = message.text ?? "";
  const cmd = commandToken(text);
  const arg = commandArgument(text);
  const state = await getTelegramState(env.DB, userId);

  if (state?.state === "awaiting_z2u_url" && !cmd) {
    await clearTelegramState(env.DB, userId);
    const url = extractUrl(text);
    if (!url) {
      await replyText(env, chatId, "Please send a full Z2U ItemStore URL containing ?search=...");
      return;
    }
    await addMonitorFromUrl(env, chatId, url);
    return;
  }

  switch (cmd) {
    case "/start":
      await sendStart(env, chatId);
      break;
    case "/help":
      await sendHelp(env, chatId);
      break;
    case "/add":
      if (arg) {
        const url = extractUrl(arg);
        if (url) {
          await addMonitorFromUrl(env, chatId, url);
        } else {
          await setAddState(env, userId, chatId);
        }
      } else {
        await setAddState(env, userId, chatId);
      }
      break;
    case "/list":
      await sendList(env, chatId);
      break;
    case "/cancel":
      await clearTelegramState(env.DB, userId);
      await replyText(env, chatId, "Cancelled.");
      break;
    case "/remove":
      await handleRemove(env, chatId, arg);
      break;
    case "/enable":
      await handleToggle(env, chatId, arg, true);
      break;
    case "/disable":
      await handleToggle(env, chatId, arg, false);
      break;
    case "/check":
      await handleCheckCommand(env, chatId, arg);
      break;
    case "/status":
      await handleStatus(env, chatId);
      break;
    default:
      await replyText(
        env,
        chatId,
        "Unknown command. Use /help to see available commands."
      );
      break;
  }
}

async function handleCallback(env: Env, callback: TelegramCallbackQuery): Promise<void> {
  const userId = callback.from.id;
  if (!isAuthorized(authConfigFor(env), userId)) {
    structuredLog("warn", "telegram_unauthorized_callback", { user_id: String(userId) }, env.LOG_LEVEL);
    return;
  }
  const chatId = callback.message?.chat.id;
  if (chatId === undefined) {
    return;
  }
  const data = callback.data ?? "";
  const [action, rawId] = data.split(":");
  const id = Number(rawId);

  if (action === "cancel_cb") {
    await answerTelegramCallback(env.TELEGRAM_BOT_TOKEN ?? "", callback.id);
    await clearTelegramState(env.DB, userId);
    return;
  }

  if (!Number.isFinite(id)) {
    await answerTelegramCallback(env.TELEGRAM_BOT_TOKEN ?? "", callback.id, "Invalid monitor id.");
    return;
  }
  const monitor = await getMonitorById(env.DB, id);
  if (!monitor) {
    await answerTelegramCallback(env.TELEGRAM_BOT_TOKEN ?? "", callback.id, "Monitor not found.");
    return;
  }

  switch (action) {
    case "check": {
      await answerTelegramCallback(env.TELEGRAM_BOT_TOKEN ?? "", callback.id, "Running check…");
      const result = await checkMonitor(env, id);
      if (result.status === "error") {
        await replyText(env, chatId, `❌ Check failed: ${escapeHtml(result.error ?? "error")}`);
      } else {
        await replyText(
          env,
          chatId,
          `✅ Check #${id} ${escapeHtml(monitor.name)}\nListings: ${result.listings}\nNew: ${result.new}\nPrice drops: ${result.priceDrops}\nDuration: ${(result.durationMs / 1000).toFixed(1)}s`
        );
      }
      break;
    }
    case "toggle":
      await answerTelegramCallback(env.TELEGRAM_BOT_TOKEN ?? "", callback.id);
      await setMonitorEnabled(env.DB, id, monitor.enabled !== 1);
      await replyText(
        env,
        chatId,
        `✅ Monitor #${id} "${escapeHtml(monitor.name)}" ${monitor.enabled !== 1 ? "enabled" : "disabled"}.`
      );
      break;
    case "remove":
      await answerTelegramCallback(env.TELEGRAM_BOT_TOKEN ?? "", callback.id);
      await replyText(
        env,
        chatId,
        `Delete monitor #${id} "${escapeHtml(monitor.name)}"?`,
        removeConfirmKeyboard(id)
      );
      break;
    case "confirm_remove":
      await answerTelegramCallback(env.TELEGRAM_BOT_TOKEN ?? "", callback.id);
      await deleteMonitor(env.DB, id);
      await replyText(env, chatId, `🗑 Removed monitor #${id} "${escapeHtml(monitor.name)}".`);
      break;
    default:
      await answerTelegramCallback(env.TELEGRAM_BOT_TOKEN ?? "", callback.id, "Unknown action.");
      break;
  }
}

export async function handleTelegramUpdate(env: Env, update: TelegramUpdate): Promise<void> {
  const userId = update.message?.from?.id ?? update.callback_query?.from?.id;
  if (!isAuthorized(authConfigFor(env), userId)) {
    structuredLog("warn", "telegram_unauthorized_update", { update_id: update.update_id }, env.LOG_LEVEL);
    return;
  }
  if (update.message) {
    await handleMessage(env, update.message);
  }
  if (update.callback_query) {
    await handleCallback(env, update.callback_query);
  }
}

export async function healthQuery(db: D1Database): Promise<{
  db: boolean;
  totalMonitors: number;
  seeded: boolean;
}> {
  let dbOk = false;
  try {
    await db.prepare("SELECT 1 AS ok").first();
    dbOk = true;
  } catch {
    dbOk = false;
  }
  const totalMonitors = await countMonitors(db).catch(() => 0);
  const seeded = await getSeedDone(db).catch(() => false);
  return { db: dbOk, totalMonitors, seeded };
}
