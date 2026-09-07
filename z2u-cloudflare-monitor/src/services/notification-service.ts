import type { MonitorRow } from "../types/db";
import type { TelegramApiResponse, TelegramMessage } from "../types/telegram";
import type { Z2uListing } from "../types/z2u";
import { formatCents } from "../utils/money";
import { structuredLog } from "../utils/logger";
import type { PriceDropChange } from "./listing-service";

export function escapeHtml(text: string): string {
  return text
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

interface SendMessageOptions {
  parseMode?: "HTML";
  replyMarkup?: Record<string, unknown>;
  disableWebPagePreview?: boolean;
}

export async function sendTelegramMessage(
  token: string,
  chatId: number,
  text: string,
  options: SendMessageOptions = {}
): Promise<boolean> {
  if (!token) {
    structuredLog("warn", "telegram_send_skipped", { reason: "missing_token" });
    return false;
  }
  const body: Record<string, unknown> = {
    chat_id: chatId,
    text,
    disable_web_page_preview: options.disableWebPagePreview ?? true,
  };
  if (options.parseMode) {
    body.parse_mode = options.parseMode;
  }
  if (options.replyMarkup) {
    body.reply_markup = options.replyMarkup;
  }

  const url = `https://api.telegram.org/bot${token}/sendMessage`;
  const response = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  const payload = (await response.json().catch(() => null)) as TelegramApiResponse<TelegramMessage> | null;
  if (!response.ok || !payload?.ok) {
    structuredLog("warn", "telegram_send_failed", {
      status: response.status,
      error: payload?.description ?? "unknown",
    });
    return false;
  }
  return true;
}

export async function answerTelegramCallback(
  token: string,
  callbackQueryId: string,
  text?: string,
  showAlert = false
): Promise<void> {
  if (!token || !callbackQueryId) {
    return;
  }
  const url = `https://api.telegram.org/bot${token}/answerCallbackQuery`;
  await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      callback_query_id: callbackQueryId,
      text,
      show_alert: showAlert,
    }),
  });
}

function openButton(url: string): Record<string, unknown> {
  return {
    text: "Open on Z2U",
    url,
  };
}

export function newListingMessage(monitor: MonitorRow, listing: Z2uListing): string {
  const price =
    listing.priceCents > 0 ? formatCents(listing.priceCents, listing.currency) : "Unknown";
  return [
    "🆕 <b>New Z2U Listing</b>",
    "",
    `Monitor: ${escapeHtml(monitor.name)}`,
    `Product: ${escapeHtml(listing.title)}`,
    `Price: ${price}`,
    listing.seller ? `Seller: ${escapeHtml(listing.seller)}` : undefined,
    "",
    `<a href="${escapeHtml(listing.url)}">Open on Z2U</a>`,
  ]
    .filter((line): line is string => line !== undefined)
    .join("\n");
}

export function priceDropMessage(monitor: MonitorRow, drop: PriceDropChange): string {
  const previous = formatCents(drop.previousPriceCents, drop.listing.currency);
  const current = formatCents(drop.currentPriceCents, drop.listing.currency);
  return [
    "🔥 <b>Z2U Price Drop</b>",
    "",
    `Monitor: ${escapeHtml(monitor.name)}`,
    `Product: ${escapeHtml(drop.listing.title)}`,
    "",
    `Previous: ${previous}`,
    `Current: ${current}`,
    `Change: -${Math.abs(drop.changePercent).toFixed(2)}%`,
    drop.listing.seller ? `Seller: ${escapeHtml(drop.listing.seller)}` : undefined,
    "",
    `<a href="${escapeHtml(drop.listing.url)}">Open on Z2U</a>`,
  ]
    .filter((line): line is string => line !== undefined)
    .join("\n");
}

export function newListingKeyboard(listing: Z2uListing): Record<string, unknown> {
  return {
    inline_keyboard: [[openButton(listing.url)]],
  };
}

export function priceDropKeyboard(drop: PriceDropChange): Record<string, unknown> {
  return {
    inline_keyboard: [[openButton(drop.listing.url)]],
  };
}

export async function notifyNewListing(
  token: string,
  chatIds: number[],
  monitor: MonitorRow,
  listing: Z2uListing
): Promise<void> {
  const text = newListingMessage(monitor, listing);
  const replyMarkup = newListingKeyboard(listing);
  for (const chatId of chatIds) {
    await sendTelegramMessage(token, chatId, text, { parseMode: "HTML", replyMarkup });
  }
}

export async function notifyPriceDrop(
  token: string,
  chatIds: number[],
  monitor: MonitorRow,
  drop: PriceDropChange
): Promise<void> {
  const text = priceDropMessage(monitor, drop);
  const replyMarkup = priceDropKeyboard(drop);
  for (const chatId of chatIds) {
    await sendTelegramMessage(token, chatId, text, { parseMode: "HTML", replyMarkup });
  }
}
