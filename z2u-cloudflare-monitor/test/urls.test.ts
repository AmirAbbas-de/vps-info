import { describe, expect, it } from "vitest";
import { encodeSearchParam, normalizeZ2uMonitorUrl } from "../src/z2u/search";
import { normalizeQuery } from "../src/utils/url";

describe("URL normalization and duplicate detection", () => {
  it("does not create duplicate monitors for duplicate URLs", () => {
    const normalized = normalizeZ2uMonitorUrl(
      "https://www.z2u.com/ItemStore.html?search=a2V5d29yZCUzRGNoYXRncHQlMjUyMHBsdXMlMjZleHBpcnklM0QxNzg4ODUxNjQ1NzUy"
    );
    const normalizedReproduced = normalizeZ2uMonitorUrl(
      `https://z2u.com/ItemStore.html?search=${encodeSearchParam("chatgpt plus", 1788851645752)}`
    );
    expect(normalizedReproduced.normalizedUrl).toBe(normalized.normalizedUrl);
    expect(normalizedReproduced.searchDefinition.fingerprint).toBe(normalized.searchDefinition.fingerprint);
  });

  it("treats equivalent keyword under a different expiring URL as duplicate", () => {
    const a = normalizeZ2uMonitorUrl(`https://www.z2u.com/ItemStore.html?search=${encodeSearchParam("chatgpt plus", 1000)}`);
    const b = normalizeZ2uMonitorUrl(`https://www.z2u.com/ItemStore.html?search=${encodeSearchParam("ChatGPT   Plus", 2000)}`);
    expect(a.searchDefinition.keyword).toBe("chatgpt plus");
    expect(b.searchDefinition.keyword).toBe("chatgpt plus");
    expect(a.searchDefinition.fingerprint).toBe(b.searchDefinition.fingerprint);
    expect(a.searchDefinition.stableDefinition).not.toBe(b.searchDefinition.stableDefinition);
  });

  it("canonicalizes query parameter ordering and removes tracking params", () => {
    const url = new URL("https://example.com/?utm_source=x&b=2&a=1");
    expect(normalizeQuery(url).toString()).toBe("https://example.com/?a=1&b=2");
  });
});
