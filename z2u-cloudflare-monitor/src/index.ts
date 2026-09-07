import { handleTelegramWebhook } from "./telegram/router";
import { healthQuery } from "./telegram/commands";
import { runCronHandler } from "./cron/handler";
import { ensureSeeded } from "./services/seed-service";
import type { Env } from "./types/env";

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const url = new URL(request.url);

    if (url.pathname === "/health") {
      if (request.method !== "GET") {
        return json({ ok: false, error: "method_not_allowed" }, 405);
      }
      await ensureSeeded(env.DB).catch(() => undefined);
      const health = await healthQuery(env.DB);
      return json({
        ok: true,
        service: "z2u-telegram-monitor",
        worker: "ok",
        db: health.db ? "ok" : "error",
        monitors: health.totalMonitors,
        seeded: health.seeded,
      });
    }

    if (url.pathname === "/telegram/webhook") {
      return handleTelegramWebhook(request, env);
    }

    return json({ ok: false, error: "not_found" }, 404);
  },

  async scheduled(_event: ScheduledEvent, env: Env): Promise<void> {
    await runCronHandler(env);
  },
};

function json(value: unknown, status = 200): Response {
  return new Response(JSON.stringify(value), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}
