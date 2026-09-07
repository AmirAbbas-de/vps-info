#!/usr/bin/env node
/**
 * Register the deployed Worker URL as the Telegram webhook.
 *
 * Required environment variables:
 *   TELEGRAM_BOT_TOKEN        BotFather token
 *   WORKER_URL                e.g. https://z2u-monitor.<subdomain>.workers.dev
 *   TELEGRAM_WEBHOOK_SECRET   must match the Worker secret
 */
const token = process.env.TELEGRAM_BOT_TOKEN;
const webhookUrl = process.env.WORKER_URL;
const secret = process.env.TELEGRAM_WEBHOOK_SECRET;

if (!token || !webhookUrl || !secret) {
  console.error("Missing required env. Run:");
  console.error("  TELEGRAM_BOT_TOKEN=... WORKER_URL=... TELEGRAM_WEBHOOK_SECRET=... node scripts/setup-telegram-webhook.mjs");
  process.exit(1);
}

const fullWebhook = new URL("/telegram/webhook", webhookUrl.includes("://") ? webhookUrl : `https://${webhookUrl}`).toString();

async function main() {
  const setBody = {
    url: fullWebhook,
    secret_token: secret,
    allowed_updates: ["message", "callback_query"],
    drop_pending_updates: false,
    max_connections: 40,
  };
  const setRes = await fetch(`https://api.telegram.org/bot${token}/setWebhook`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(setBody),
  });
  const setJson = await setRes.json();
  console.log("setWebhook ok:", setJson.ok, setJson.description || "");

  const infoRes = await fetch(`https://api.telegram.org/bot${token}/getWebhookInfo`);
  const infoJson = await infoRes.json();
  console.log("getWebhookInfo:", JSON.stringify(infoJson.result, null, 2));
}

main().catch((error) => {
  console.error("Webhook setup failed:", error instanceof Error ? error.message : String(error));
  process.exit(1);
});
