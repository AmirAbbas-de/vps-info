import type { Z2uListing, Z2uParseResult } from "../types/z2u";
import { parsePriceToCents } from "../utils/money";
import { absoluteUrl } from "../utils/url";

export interface Z2uParserError extends Error {
  code: "PAGE_INVALID" | "PAGE_CHANGED" | "PARSER_FAILED";
}

export function z2uParserError(message: string, code: Z2uParserError["code"]): Z2uParserError {
  const err = new Error(message) as Z2uParserError;
  err.code = code;
  return err;
}

function decodeEntities(input: string): string {
  return input
    .replace(/&nbsp;/g, " ")
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/&apos;/g, "'");
}

function normalizeText(input: string): string {
  return decodeEntities(input)
    .replace(/<[^>]*>/g, " ")
    .replace(/[\r\n\t]+/g, " ")
    .replace(/[ \u00A0]+/g, " ")
    .trim();
}

function stripTags(input: string): string {
  return input.replace(/<[^>]*>/g, " ");
}

function extractInnerText(block: string): string {
  return normalizeText(stripTags(block));
}

function findHtmlAttribute(block: string, name: string): string | null {
  const re = new RegExp(`(?:^|\\s)${name}\\s*=\\s*["']([^"']*)["']`, "i");
  const match = re.exec(block);
  return match ? match[1].trim() : null;
}

function extractTitle(block: string): string | null {
  const alt = findHtmlAttribute(block, "alt");
  if (alt && alt.length > 2 && !/loading\.gif/i.test(alt)) {
    return normalizeText(alt).replace(/\s+/g, " ").trim();
  }
  const title = findHtmlAttribute(block, "title");
  if (title && title.length > 2) {
    return normalizeText(title);
  }
  const text = extractInnerText(block);
  if (!text) {
    return null;
  }
  // The first line/non-price sentence is usually the product title.
  const lines = text.split(/\s{2,}/).filter(Boolean);
  const first = lines[0] ?? text;
  const withoutMeta = first
    .replace(/from\s*\$?[\d.,kKmM]+/gi, "")
    .replace(/[0-9]+\s*Offers/gi, "")
    .replace(/sold out/gi, "")
    .trim();
  return withoutMeta.length >= 3 ? withoutMeta : null;
}

function extractSeller(block: string): string | null {
  const match = /class=["'][^"']*(?:seller|store)[^"']*["'][^>]*>([^<]+)</i.exec(block);
  if (match) {
    return normalizeText(match[1]);
  }
  const bTag = /<b[^>]*>([^<]+)<\/b>/i.exec(block);
  if (bTag) {
    return normalizeText(bTag[1]);
  }
  const strongTag = /<strong[^>]*>([^<]+)<\/strong>/i.exec(block);
  if (strongTag) {
    return normalizeText(strongTag[1]);
  }
  const text = extractInnerText(block);
  const service = text.match(
    /\b(GlobalAccount|GlobalManual Top Up|GlobalDigital Key|GlobalGift|GlobalSubscriptions|Manual Top Up|Digital key|Gift Cards|Subscriptions|Account Ownership Transfer|Top Up|Accounts)\b/i
  );
  if (service) {
    return service[1];
  }
  return null;
}

function extractPrice(block: string): number | null {
  const text = extractInnerText(block);
  const match = /from\s*\$?\s*([0-9][0-9,.kKmM]*)/i.exec(text);
  if (match) {
    const cents = parsePriceToCents(match[1]);
    if (cents !== null) {
      return cents;
    }
  }
  // Fallback only when the block clearly marks it as a price: a currency
  // symbol or an explicit price class. Never use random card numbers like
  // offer counts or durations from the title.
  const hasPriceMarker = /\$|€|£|price|customer_price/i.test(block);
  if (hasPriceMarker && !/\bsold\s*out\b|\bout\s*of\s*stock\b/i.test(text)) {
    const standalone = /(?:^|\s)\$?\s*([0-9]+(?:\.[0-9]{1,6})?)\s*(?:$|\s)/g.exec(text);
    if (standalone) {
      const cents = parsePriceToCents(standalone[1]);
      if (cents !== null) {
        return cents;
      }
    }
  }
  return null;
}

function extractOfferCount(block: string): number | null {
  const text = extractInnerText(block);
  const match = /\b([0-9]+)\s*Offers\b/i.exec(text);
  if (match) {
    const value = Number(match[1]);
    return Number.isFinite(value) ? value : null;
  }
  return null;
}

function extractAvailability(block: string): Z2uListing["availability"] {
  const text = extractInnerText(block);
  if (/sold\s*out|out\s*of\s*stock|no\s*stock|unavailable/i.test(text)) {
    return "sold_out";
  }
  return "available";
}

interface ProductAnchor {
  url: string;
  externalId: string;
  inner: string;
}

function findProductAnchors(html: string, baseUrl: string): ProductAnchor[] {
  const results: ProductAnchor[] = [];
  const re =
    /<a\b[^>]*href=["']([^"']*(?:\/product-|https?:\/\/[^"']*\/product-)([0-9]+)\/[^"']*\.html[^"']*)["'][^>]*>([\s\S]*?)<\/a>/gi;
  const seen = new Set<string>();
  let match: RegExpExecArray | null;
  while ((match = re.exec(html)) !== null) {
    const href = match[1];
    const externalId = match[2];
    const inner = match[3];
    if (seen.has(externalId)) {
      continue;
    }
    seen.add(externalId);
    results.push({
      url: absoluteUrl(href, baseUrl),
      externalId,
      inner,
    });
  }
  return results;
}

function productCountText(html: string): string | null {
  const match =
    /Product\s+(?:Contain|Name)*\s*(\d+)\s*(?:Products?|Offers?)?/i.exec(html) ??
    /(\d+)\s+(?:products?|offers?)/i.exec(html);
  if (match) {
    return match[0];
  }
  return null;
}

function hasZeroResultsIndication(html: string): boolean {
  const text = normalizeText(html);
  if (/Unable to find desired results/i.test(text)) {
    return true;
  }
  if (/Product\s+Contain\s*0/i.test(text)) {
    return true;
  }
  if (/\b0\s+Offers\b/i.test(text)) {
    return true;
  }
  return false;
}

/**
 * Parse a Z2U ItemStore search response.
 *
 * Important safety rule: only return an empty listing set when the page
 * explicitly indicates zero results. If the page is unrecognizable or
 * contains no product markers and no zero-result indication, throw so the
 * caller never mistakes a broken response for "all listings vanished".
 */
export function parseSearchResults(html: string, baseUrl: string): Z2uParseResult {
  const normalized = normalizeText(html);
  const looksLikeZ2u =
    /ItemStore|Product Name|Product Contain|Unable to find desired results|img\.z2u\.com/i.test(normalized) ||
    /\/product-[0-9]+\//.test(html);
  if (!looksLikeZ2u) {
    throw z2uParserError("Response is not a recognizable Z2U search page.", "PAGE_INVALID");
  }

  const anchors = findProductAnchors(html, baseUrl);
  if (anchors.length === 0 && !hasZeroResultsIndication(html)) {
    throw z2uParserError(
      "Z2U response contained no product anchors and no zero-result indication.",
      "PAGE_CHANGED"
    );
  }

  const listings: Z2uListing[] = [];
  for (const anchor of anchors) {
    const title = extractTitle(anchor.inner);
    if (!title) {
      continue;
    }
    const priceCents = extractPrice(anchor.inner);
    const offerCount = extractOfferCount(anchor.inner);
    const availability = extractAvailability(anchor.inner);

    // A sold-out product may still have no displayed price; that is a valid
    // known-unavailable record.
    listings.push({
      externalId: anchor.externalId,
      title,
      seller: extractSeller(anchor.inner),
      priceCents: priceCents ?? 0,
      currency: "USD",
      url: anchor.url,
      availability,
      offerCount,
    });
  }

  return {
    valid: true,
    listings,
    pageNumber: 1,
    hasZeroResults: listings.length === 0,
    productCountText: productCountText(html),
  };
}
