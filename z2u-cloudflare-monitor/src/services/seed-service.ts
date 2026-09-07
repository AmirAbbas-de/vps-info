import type { D1Database } from "@cloudflare/workers-types";
import {
  countMonitors,
  findMonitorByNormalizedUrl,
  getSeedDone,
  insertMonitor,
  setSeedDone,
} from "../db/queries";
import { normalizeZ2uMonitorUrl } from "../z2u/search";

export const DEFAULT_SEEDS = [
  {
    name: "ChatGPT Plus",
    url: "https://www.z2u.com/ItemStore.html?search=a2V5d29yZCUzRGNoYXRncHQlMjUyMHBsdXMlMjZleHBpcnklM0QxNzg4ODUxNjQ1NzUy",
  },
  {
    name: "Spotify",
    url: "https://www.z2u.com/ItemStore.html?search=a2V5d29yZCUzRHNwb3RpZnklMjZleHBpcnklM0QxNzg4ODUxNjc2Mjgy",
  },
];

/**
 * Seed the two default monitors only for a genuinely new installation.
 *
 * The app_meta "initial_seed_done" flag means manually removed monitors are
 * never recreated just because the Worker starts again.
 */
export async function ensureSeeded(db: D1Database): Promise<{ seeded: boolean; monitors: number }> {
  if (await getSeedDone(db)) {
    return { seeded: false, monitors: await countMonitors(db) };
  }

  const existing = await countMonitors(db);
  if (existing > 0) {
    await setSeedDone(db);
    return { seeded: false, monitors: existing };
  }

  for (const seed of DEFAULT_SEEDS) {
    const normalized = normalizeZ2uMonitorUrl(seed.url);
    const duplicate = await findMonitorByNormalizedUrl(db, normalized.normalizedUrl);
    if (!duplicate) {
      await insertMonitor(
        db,
        {
          name: seed.name,
          original_url: normalized.originalUrl,
          normalized_url: normalized.normalizedUrl,
          search_keyword: normalized.searchDefinition.keyword,
          search_definition: normalized.searchDefinition.stableDefinition,
          search_fingerprint: normalized.searchDefinition.fingerprint,
        },
        true,
        false
      );
    }
  }
  await setSeedDone(db);
  return { seeded: true, monitors: await countMonitors(db) };
}
