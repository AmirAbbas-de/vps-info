import type { Env } from "../types/env";
import type { Z2uNormalizedMonitorUrl, Z2uSearchDefinition } from "../types/z2u";
import { canonicalPathname, ensureHttps, normalizeQuery } from "../utils/url";

const BASE_PATH = "/ItemStore.html";
const SUPPORTED_HOST = "www.z2u.com";

/**
 * Decode the opaque `search` query value used by Z2U ItemStore links.
 *
 * The supplied value is base64(encodeURIComponent(payload)) where payload
 * looks like `keyword=chatgpt%20plus&expiry=1788851645752`. We therefore
 * base64-decode, URL-decode once, then let URLSearchParams decode the
 * remaining percent-escapes.
 */
export function decodeSearchParam(searchParam: string): {
  keyword: string | null;
  expiryMs: number | null;
} {
  if (!searchParam) {
    return { keyword: null, expiryMs: null };
  }

  const normalizedB64 = searchParam.replace(/-/g, "+").replace(/_/g, "/");
  let outer: string;
  try {
    outer = atob(normalizedB64);
  } catch {
    return { keyword: null, expiryMs: null };
  }

  let decoded: string;
  try {
    decoded = decodeURIComponent(outer);
  } catch {
    return { keyword: null, expiryMs: null };
  }

  let params: URLSearchParams;
  try {
    params = new URLSearchParams(decoded);
  } catch {
    return { keyword: null, expiryMs: null };
  }

  const rawKeyword = params.get("keyword");
  const keyword = rawKeyword ? rawKeyword.trim() : null;
  const rawExpiry = params.get("expiry");
  const expiryMs = rawExpiry && /^[0-9]+$/.test(rawExpiry) ? Number(rawExpiry) : null;
  return {
    keyword: keyword && keyword.length > 0 ? keyword : null,
    expiryMs,
  };
}

/**
 * Encode a keyword and expiry into the same opaque format Z2U uses.
 */
export function encodeSearchParam(keyword: string, expiryMs: number): string {
  const payload = `keyword=${encodeURIComponent(keyword)}&expiry=${Math.trunc(expiryMs)}`;
  return btoa(encodeURIComponent(payload));
}

export function normalizeKeyword(keyword: string): string {
  return keyword
    .trim()
    .replace(/\s+/g, " ")
    .toLowerCase();
}

export function isSearchExpired(definition: Z2uSearchDefinition, nowMs: number): boolean {
  return definition.originalExpiryMs !== undefined && definition.originalExpiryMs < nowMs;
}

function extractExtraParams(url: URL): Record<string, string> {
  const out: Record<string, string> = {};
  const excluded = new Set(["search"]);
  for (const [key] of Array.from(url.searchParams.entries())) {
    if (excluded.has(key) || key.startsWith("utm_")) {
      continue;
    }
    out[key] = url.searchParams.get(key) ?? "";
  }
  return out;
}

function stableDefinition(definition: Omit<Z2uSearchDefinition, "stableDefinition" | "fingerprint">): string {
  return JSON.stringify({
    keyword: definition.keyword,
    basePath: definition.basePath,
    extraParams: definition.extraParams,
    originalExpiryMs: definition.originalExpiryMs ?? null,
  });
}

function fingerprintFor(definition: Z2uSearchDefinition): string {
  const params = Object.keys(definition.extraParams)
    .sort()
    .map((key) => `${key}=${definition.extraParams[key]}`)
    .join("&");
  return `${definition.keyword}|${definition.basePath}|${params}`;
}

export function createSearchDefinition(options: {
  keyword: string;
  originalExpiryMs?: number;
  basePath?: string;
  extraParams?: Record<string, string>;
}): Z2uSearchDefinition {
  const keyword = normalizeKeyword(options.keyword);
  const basePath = options.basePath ?? BASE_PATH;
  const extraParams = options.extraParams ?? {};
  const base = {
    keyword,
    basePath,
    extraParams,
    originalExpiryMs: options.originalExpiryMs,
  } satisfies Omit<Z2uSearchDefinition, "stableDefinition" | "fingerprint">;
  const definition: Z2uSearchDefinition = {
    ...base,
    stableDefinition: stableDefinition(base),
    fingerprint: "",
  };
  definition.fingerprint = fingerprintFor(definition);
  return definition;
}

/**
 * Parse and normalize a supported Z2U ItemStore search URL.
 *
 * This function does not ignore the expiring opaque parameter; it decodes it
 * and extracts the stable keyword/filter definition.
 */
export function normalizeZ2uMonitorUrl(rawUrl: string): Z2uNormalizedMonitorUrl {
  const originalUrl = rawUrl.trim();
  const url = ensureHttps(originalUrl);
  const host = url.hostname.toLowerCase().replace(/^\./, "");

  if (host !== SUPPORTED_HOST && host !== "z2u.com" && host !== `www.${SUPPORTED_HOST.replace(/^www\./, "")}`) {
    throw new Error("Only www.z2u.com ItemStore search URLs are supported.");
  }
  url.hostname = SUPPORTED_HOST;

  const path = canonicalPathname(url.pathname);
  if (path !== BASE_PATH) {
    throw new Error("Only Z2U /ItemStore.html search pages are supported.");
  }

  const rawSearchParam = url.searchParams.get("search");
  if (!rawSearchParam || rawSearchParam.length === 0) {
    throw new Error("The supplied Z2U URL does not contain a search parameter.");
  }

  const decoded = decodeSearchParam(rawSearchParam);
  if (!decoded.keyword) {
    throw new Error("The Z2U search parameter could not be decoded to a stable keyword.");
  }

  const extraParams = extractExtraParams(url);
  const searchDefinition = createSearchDefinition({
    keyword: decoded.keyword,
    originalExpiryMs: decoded.expiryMs ?? undefined,
    basePath: path,
    extraParams,
  });

  const normalizedUrlUrl = new URL(`https://${SUPPORTED_HOST}${path}`);
  normalizedUrlUrl.searchParams.set("search", rawSearchParam);
  for (const [key, value] of Object.entries(extraParams)) {
    normalizedUrlUrl.searchParams.set(key, value);
  }

  return {
    normalizedUrl: normalizeQuery(normalizedUrlUrl).toString(),
    originalUrl,
    searchParam: rawSearchParam,
    searchDefinition,
  };
}

/**
 * Build a canonical Z2U search URL for a stored definition.
 *
 * When Z2U_REFRESH_EXPIRY is true (the default), the old expiry is replaced
 * with `now + Z2U_EXPIRY_WINDOW_MINUTES` so the monitor does not depend on a
 * one-time opaque link. When false, the original expiry is reused if it is
 * still in the future.
 */
export function buildZ2uSearchUrl(
  definition: Z2uSearchDefinition,
  env: Pick<Env, "Z2U_REFRESH_EXPIRY" | "Z2U_EXPIRY_WINDOW_MINUTES">,
  nowMs: number
): string {
  const refresh = env.Z2U_REFRESH_EXPIRY === undefined ? true : env.Z2U_REFRESH_EXPIRY === "true";
  const windowMs =
    (Number(env.Z2U_EXPIRY_WINDOW_MINUTES ?? "1440") || 1440) * 60 * 1000;

  let expiryMs = definition.originalExpiryMs ?? nowMs + windowMs;
  if (refresh || expiryMs < nowMs) {
    expiryMs = nowMs + windowMs;
  }

  const url = new URL(`https://${SUPPORTED_HOST}${definition.basePath}`);
  url.searchParams.set("search", encodeSearchParam(definition.keyword, expiryMs));
  for (const [key, value] of Object.entries(definition.extraParams)) {
    url.searchParams.set(key, value);
  }
  return normalizeQuery(url).toString();
}
