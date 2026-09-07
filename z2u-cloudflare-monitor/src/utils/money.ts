/**
 * Money helpers.
 *
 * Z2U prices are parsed with string math and stored as integer minor units
 * (cents for USD). Floating point arithmetic is never used for comparison.
 */

const PRICE_PATTERN = /^([0-9][0-9,]*)(?:\.([0-9]{1,6}))?\s*(k|m)?$/i;

/**
 * Parse a human price such as "$16.5", "16.50", "1,234.56", "$1.2k" into
 * integer minor units. Returns null when the value cannot be confidently
 * parsed.
 */
export function parsePriceToCents(input: string): number | null {
  if (typeof input !== "string" || input.trim().length === 0) {
    return null;
  }
  const cleaned = input
    .trim()
    .replace(/\$/g, "")
    .replace(/[€£¥]/g, "")
    .replace(/\s+/g, "")
    .replace(/,/g, "");

  const match = PRICE_PATTERN.exec(cleaned);
  if (!match) {
    return null;
  }

  const whole = match[1].replace(/,/g, "");
  if (!/^[0-9]+$/.test(whole)) {
    return null;
  }
  const fraction = (match[2] ?? "").slice(0, 2).padEnd(2, "0");
  const suffix = (match[3] ?? "").toLowerCase();

  let centsBig = BigInt(whole) * 100n + BigInt(fraction);
  if (suffix === "k") {
    centsBig = centsBig * 1000n;
  } else if (suffix === "m") {
    centsBig = centsBig * 1000000n;
  }
  if (centsBig > BigInt(Number.MAX_SAFE_INTEGER)) {
    return null;
  }
  return Number(centsBig);
}

export function formatCents(cents: number, currency: string = "USD"): string {
  const sign = cents < 0 ? "-" : "";
  const abs = Math.abs(cents);
  const major = Math.floor(abs / 100);
  const minor = (abs % 100).toString().padStart(2, "0");
  const formatted = `${major.toLocaleString("en-US")}.${minor}`;
  switch (currency.toUpperCase()) {
    case "USD":
      return `${sign}$${formatted}`;
    case "EUR":
      return `${sign}€${formatted}`;
    case "GBP":
      return `${sign}£${formatted}`;
    default:
      return `${sign}${currency.toUpperCase()} ${formatted}`;
  }
}

export function compareCents(a: number, b: number): -1 | 0 | 1 {
  if (a < b) return -1;
  if (a > b) return 1;
  return 0;
}

export function isPriceDrop(previousCents: number, currentCents: number): boolean {
  return compareCents(previousCents, currentCents) > 0;
}

export function isPriceIncrease(previousCents: number, currentCents: number): boolean {
  return compareCents(previousCents, currentCents) < 0;
}

/**
 * Percent change from oldPrice to newPrice, rounded to two decimal places.
 * Returns 0 when previous is 0 to avoid divide-by-zero.
 */
export function percentChange(previousCents: number, newCents: number): number {
  if (previousCents === 0) {
    return 0;
  }
  const exact = Number(previousCents - newCents) / Number(previousCents) * 100;
  return Math.round(exact * 100) / 100;
}
