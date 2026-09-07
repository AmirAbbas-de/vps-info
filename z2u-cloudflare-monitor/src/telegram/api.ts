import type { TelegramApiResponse } from "../types/telegram";
import { structuredLog } from "../utils/logger";

export async function telegramRequest<Result = unknown>(
  token: string,
  method: string,
  body: Record<string, unknown> = {}
): Promise<Result | null> {
  if (!token) {
    return null;
  }
  const url = `https://api.telegram.org/bot${token}/${method}`;
  const response = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  const payload = (await response.json().catch(() => null)) as TelegramApiResponse<Result> | null;
  if (response.ok && payload?.ok) {
    return payload.result ?? null;
  }
  structuredLog("warn", "telegram_api_failed", {
    method,
    status: response.status,
    error: payload?.description ?? "unknown",
  });
  return null;
}

export async function getWebhookInfo(token: string): Promise<Record<string, unknown> | null> {
  return telegramRequest<Record<string, unknown>>(token, "getWebhookInfo");
}

export async function setWebhook(
  token: string,
  webhookUrl: string,
  secretToken: string,
  allowedUpdates: string[] = [
    "message",
    "callback_query",
  ]
): Promise<boolean> {
  const result = await telegramRequest<boolean>(token, "setWebhook", {
    url: webhookUrl,
    secret_token: secretToken,
    allowed_updates: allowedUpdates,
    drop_pending_updates: false,
    max_connections: 40,
  });
  return result === true;
}

export async function deleteWebhook(token: string): Promise<void> {
  await telegramRequest(token, "deleteWebhook");
}
