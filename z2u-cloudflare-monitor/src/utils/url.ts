/**
 * General URL helpers.
 */

const UTM_PARAMS = new Set(["utm_source", "utm_medium", "utm_campaign", "utm_term", "utm_content"]);

/** Add https when a scheme is missing and ensure the URL is parseable. */
export function ensureHttps(input: string): URL {
  const trimmed = input.trim();
  const withProtocol = /^https?:\/\//i.test(trimmed) ? trimmed : `https://${trimmed}`;
  const url = new URL(withProtocol);
  if (url.protocol === "http:") {
    url.protocol = "https:";
  }
  return url;
}

/** Resolve a possibly relative or protocol-relative link against a base. */
export function absoluteUrl(href: string, base: string): string {
  if (/^https?:\/\//i.test(href)) {
    return new URL(href).toString();
  }
  if (href.startsWith("//")) {
    return `https:${href}`;
  }
  return new URL(href, base).toString();
}

/** Sort query params alphabetically and remove tracking params. */
export function normalizeQuery(url: URL): URL {
  const normalized = new URL(url.toString());
  const params = new URLSearchParams();
  const entries = Array.from(normalized.searchParams.entries());
  const keys: string[] = [];
  for (const [key] of entries) {
    if (!keys.includes(key)) {
      keys.push(key);
    }
  }
  keys.sort();
  for (const key of keys) {
    if (UTM_PARAMS.has(key) || key.startsWith("utm_")) {
      continue;
    }
    const values = normalized.searchParams.getAll(key).sort();
    for (const value of values) {
      params.append(key, value);
    }
  }
  normalized.search = params.toString();
  return normalized;
}

/** Strip a trailing slash from a pathname for canonical comparison. */
export function canonicalPathname(pathname: string): string {
  if (pathname === "/" || pathname.length === 0) {
    return "/";
  }
  return pathname.endsWith("/") ? pathname.slice(0, -1) : pathname;
}
