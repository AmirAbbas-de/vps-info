import type { Env } from "../types/env";
import { ensureSeeded } from "../services/seed-service";
import { runChecks } from "../services/monitor-service";
import { structuredLog } from "../utils/logger";

export async function runCronHandler(env: Env): Promise<void> {
  const started = Date.now();
  structuredLog("info", "cron_started", {}, env.LOG_LEVEL);

  try {
    await ensureSeeded(env.DB);
  } catch (error) {
    structuredLog("error", "cron_seed_failed", {
      error: error instanceof Error ? error.message : String(error),
    }, env.LOG_LEVEL);
  }

  try {
    const batch = await runChecks(env);
    structuredLog("info", "cron_finished", {
      batch_size: batch.results.length,
      error_count: batch.errorCount,
      duration_ms: Date.now() - started,
    }, env.LOG_LEVEL);
  } catch (error) {
    structuredLog("error", "cron_failed", {
      error: error instanceof Error ? error.message : String(error),
      duration_ms: Date.now() - started,
    }, env.LOG_LEVEL);
  }
}
