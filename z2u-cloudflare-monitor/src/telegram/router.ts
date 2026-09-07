import type { Env } from "../types/env";
import type { TelegramUpdate } from "../types/telegram";
import { handleTelegramUpdate } from "./commands";
import { structuredLog } from "../utils/logger";

function validateWebhookSecret(env: Env, request: Request): boolean {
  const expected = env.TELEGRAM_WEBHOOK_SECRET;
  if (!expected) {
    // When the secret is not configured we cannot trust Telegram webhook
    // requests. This is intentional: cloud setup requires TELEGRAM_WEBHOOK_SECRET.
    return false;
  }
  const actual = request.headers.get("X-Telegram-Bot-Api-Secret-Token");
  if (!actual) {
    return false;
  }
  return timingSafeEqual(actual, expected);
}

function timingSafeEqual(a: string, b: string): boolean {
  if (a.length !== b.length) {
    return false;
  }
  // Constant-time-ish comparison for short secrets. This is not a substitute
  // for TLS, but prevents trivial timing leaks.
  let result = 0;
  for (let i = 0; i < a.length; i += 1) {
    result |= a.charCodeAt(i) ^ b.charCodeAt(i);
  }
  return result === 0;
}

export async function handleTelegramWebhook(request: Request, env: Env): Promise<Response> {
  if (request.method !== "POST") {
    return new Response(JSON.stringify({ ok: false, error: "method_not_allowed" }), {
      status: 405,
      headers: { "Content-Type": "application/json" },
    });
  }

  if (!validateWebhookSecret(env, request)) {
    structuredLog("warn", "telegram_webhook_secret_invalid", {
      path: new URL(request.url).pathname,
    }, env.LOG_LEVEL);
    return new Response(JSON.stringify({ ok: false, error: "forbidden" }), {
      status: 403,
      headers: { "Content-Type": "application/json" },
    });
  }

  let update: TelegramUpdate;
  try {
    update = (await request.json()) as TelegramUpdate;
  } catch {
    return new Response(JSON.stringify({ ok: false, error: "bad_json" }), {
      status: 400,
      headers: { "Content-Type": "application/json" },
    });
  }

  if (!update || typeof update.update_id !== "number") {
    return new Response(JSON.stringify({ ok: false, error: "invalid_update" }), {
      status: 400,
      headers: { "Content-Type": "application/json" },
    });
  }

  try {
    await handleTelegramUpdate(env, update);
  } catch (error) {
    structuredLog("error", "telegram_update_processing_failed", {
      update_id: update.update_id,
      error: error instanceof Error ? error.message : String(error),
    }, env.LOG_LEVEL);
  }

  return new Response(JSON.stringify({ ok: true }), {
    status: 200,
    headers: { "Content-Type": "application/json" },
  });
}
