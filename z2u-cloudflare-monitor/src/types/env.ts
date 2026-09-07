import type { D1Database } from "@cloudflare/workers-types";

/**
 * Worker bindings and configuration.
 *
 * TELEGRAM_BOT_TOKEN, TELEGRAM_WEBHOOK_SECRET and TELEGRAM_ALLOWED_USER_IDS
 * should be stored as Cloudflare secrets, never in wrangler.toml.
 */
export interface Env {
  DB: D1Database;

  TELEGRAM_BOT_TOKEN?: string;
  TELEGRAM_WEBHOOK_SECRET?: string;
  TELEGRAM_ALLOWED_USER_IDS?: string;
  TELEGRAM_CHAT_ID?: string;

  CHECK_INTERVAL_MINUTES?: string;
  Z2U_REQUEST_TIMEOUT_MS?: string;
  Z2U_RETRIES?: string;
  MAX_MONITORS_PER_CRON_RUN?: string;
  MAX_Z2U_PAGES?: string;
  LOG_LEVEL?: string;

  Z2U_REFRESH_EXPIRY?: string;
  Z2U_EXPIRY_WINDOW_MINUTES?: string;
}
