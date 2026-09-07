import { describe, expect, it } from "vitest";
import {
  monitorRuntimeConfig,
  parseStoredSearchDefinition,
} from "../src/services/monitor-service";
import { buildZ2uSearchUrl, createSearchDefinition, decodeSearchParam } from "../src/z2u/search";
import type { Env } from "../src/types/env";

describe("monitor configuration", () => {
  it("parses runtime config with safe defaults", () => {
    const env = {} as Env;
    expect(monitorRuntimeConfig(env).maxMonitorsPerRun).toBe(3);
    expect(monitorRuntimeConfig(env).maxZ2uPages).toBe(1);
  });

  it("serializes and restores the stable search definition", () => {
    const def = createSearchDefinition({
      keyword: "spotify",
      originalExpiryMs: 1788851676282,
      extraParams: { sort: "price" },
    });
    const restored = parseStoredSearchDefinition(def.stableDefinition);
    expect(restored.keyword).toBe("spotify");
    expect(restored.extraParams).toEqual({ sort: "price" });
    expect(restored.originalExpiryMs).toBe(1788851676282);
  });

  it("rebuilds a fresh Z2U URL when the original expires", () => {
    const def = createSearchDefinition({ keyword: "chatgpt plus", originalExpiryMs: 1000 });
    const nowMs = 2000;
    const url = buildZ2uSearchUrl(def, { Z2U_REFRESH_EXPIRY: "true", Z2U_EXPIRY_WINDOW_MINUTES: "1440" }, nowMs);
    const search = new URL(url).searchParams.get("search") ?? "";
    const decoded = decodeSearchParam(search);
    expect(decoded.keyword).toBe("chatgpt plus");
    expect(decoded.expiryMs).toBeGreaterThan(nowMs);
  });
});
