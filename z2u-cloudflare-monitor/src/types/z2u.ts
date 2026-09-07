export interface Z2uSearchDefinition {
  /** Normalized, lower-cased search keyword, e.g. "chatgpt plus". */
  keyword: string;
  /** Original opaque expiry if it was present in the supplied URL. */
  originalExpiryMs?: number;
  /** The stable, expiring-independent definition stored in D1. */
  stableDefinition: string;
  /** Fingerprint used to detect equivalent searches. */
  fingerprint: string;
  /** Supported base path, normally /ItemStore.html. */
  basePath: string;
  /** Non-secret/filter query parameters to preserve when rebuilding the URL. */
  extraParams: Record<string, string>;
}

export interface Z2uNormalizedMonitorUrl {
  normalizedUrl: string;
  originalUrl: string;
  searchParam: string;
  searchDefinition: Z2uSearchDefinition;
}

export interface Z2uListing {
  /** Stable product id from the Z2U /product-<id>/ URL. */
  externalId: string;
  title: string;
  seller: string | null;
  priceCents: number;
  currency: string;
  url: string;
  availability: "available" | "sold_out" | "unknown";
  offerCount: number | null;
}

export interface Z2uParseResult {
  valid: true;
  listings: Z2uListing[];
  pageNumber: number;
  hasZeroResults: boolean;
  productCountText: string | null;
}

export interface Z2uFetchResult {
  html: string;
  status: number;
}

export interface Z2uSearchError extends Error {
  code:
    | "INVALID_URL"
    | "INVALID_SEARCH_PARAM"
    | "DUPLICATE"
    | "FETCH_FAILED"
    | "FETCH_FORBIDDEN"
    | "FETCH_RATE_LIMITED"
    | "FETCH_EMPTY"
    | "PAGE_INVALID"
    | "PAGE_CHANGED"
    | "PARSER_FAILED";
}
