import { describe, expect, it } from "vitest";
import {
  decodeSearchParam,
  encodeSearchParam,
  isSearchExpired,
  normalizeKeyword,
  normalizeZ2uMonitorUrl,
} from "../src/z2u/search";

const CHATGPT = "a2V5d29yZCUzRGNoYXRncHQlMjUyMHBsdXMlMjZleHBpcnklM0QxNzg4ODUxNjQ1NzUy";
const SPOTIFY = "a2V5d29yZCUzRHNwb3RpZnklMjZleHBpcnklM0QxNzg4ODUxNjc2Mjgy";

describe("decodeSearchParam", () => {
  it("decodes the supplied ChatGPT Plus search", () => {
    const decoded = decodeSearchParam(CHATGPT);
    expect(decoded.keyword).toBe("chatgpt plus");
    expect(decoded.expiryMs).toBe(1788851645752);
  });

  it("decodes the supplied Spotify search", () => {
    const decoded = decodeSearchParam(SPOTIFY);
    expect(decoded.keyword).toBe("spotify");
    expect(decoded.expiryMs).toBe(1788851676282);
  });

  it("reproduces the supplied encoded search value", () => {
    expect(encodeSearchParam("chatgpt plus", 1788851645752)).toBe(CHATGPT);
    expect(encodeSearchParam("spotify", 1788851676282)).toBe(SPOTIFY);
  });

  it("returns nulls for malformed base64", () => {
    expect(decodeSearchParam("not-base64")).toEqual({ keyword: null, expiryMs: null });
    expect(decodeSearchParam("")).toEqual({ keyword: null, expiryMs: null });
  });
});

describe("normalizeZ2uMonitorUrl", () => {
  it("extracts the stable keyword and ignores the expiry in the fingerprint", () => {
    const normalized = normalizeZ2uMonitorUrl(`https://www.z2u.com/ItemStore.html?search=${CHATGPT}`);
    expect(normalized.searchDefinition.keyword).toBe("chatgpt plus");
    expect(normalized.searchDefinition.originalExpiryMs).toBe(1788851645752);
    expect(normalized.normalizedUrl).toContain(`search=${CHATGPT}`);
  });

  it("rejects non-ItemStore URLs", () => {
    expect(() => normalizeZ2uMonitorUrl("https://www.z2u.com/product-7942/x.html")).toThrow();
  });

  it("rejects non-Z2U hosts", () => {
    expect(() => normalizeZ2uMonitorUrl("https://example.com/ItemStore.html?search=x")).toThrow();
  });
});

describe("expiry handling", () => {
  it("considers past expiry expired", () => {
    const def = normalizeZ2uMonitorUrl(`https://www.z2u.com/ItemStore.html?search=${CHATGPT}`).searchDefinition;
    expect(isSearchExpired(def, def.originalExpiryMs! + 1)).toBe(true);
    expect(isSearchExpired(def, def.originalExpiryMs! - 1)).toBe(false);
  });

  it("normalizes keywords", () => {
    expect(normalizeKeyword("  ChatGPT   PLUS ")).toBe("chatgpt plus");
  });

  it("executes before migration of the intended expiry", () => {
    expect(new Date(1788851645752).toISOString()).toBe("2026-09-08T07:14:05.752Z");
  });
});
