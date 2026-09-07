import type { ListingRow } from "../types/db";
import type { Z2uListing } from "../types/z2u";
import { isPriceDrop, percentChange } from "../utils/money";

export interface StoredListingSnapshot {
  externalId: string;
  priceCents: number;
}

export interface PriceDropChange {
  listing: Z2uListing;
  previousPriceCents: number;
  currentPriceCents: number;
  changePercent: number;
}

export interface ListingDiffResult {
  /** New listings that should be persisted (all new rows). */
  newPersisted: Z2uListing[];
  /** New listings that should produce a Telegram notification. */
  newNotificationCandidates: Z2uListing[];
  /** Price drops that should produce a Telegram notification. */
  priceDropCandidates: PriceDropChange[];
  /** All scanned external ids that exist in the current response. */
  seenExternalIds: string[];
}

/**
 * Diff an existing D1 snapshot against a freshly parsed Z2U page.
 *
 * On the very first snapshot, every scanned listing is considered
 * "persistable" but none should generate notifications. This is the
 * initial-snapshot behavior that prevents alert spam for existing products.
 */
export function diffListings(
  stored: StoredListingSnapshot[],
  scanned: Z2uListing[],
  isFirstSnapshot: boolean
): ListingDiffResult {
  const storedByExternalId = new Map<string, StoredListingSnapshot>();
  for (const item of stored) {
    storedByExternalId.set(item.externalId, item);
  }

  const newPersisted: Z2uListing[] = [];
  const newNotificationCandidates: Z2uListing[] = [];
  const priceDropCandidates: PriceDropChange[] = [];
  const seenExternalIds: string[] = [];

  for (const listing of scanned) {
    seenExternalIds.push(listing.externalId);
    const previous = storedByExternalId.get(listing.externalId);

    if (!previous) {
      newPersisted.push(listing);
      if (!isFirstSnapshot) {
        newNotificationCandidates.push(listing);
      }
      continue;
    }

    if (
      !isFirstSnapshot &&
      previous.priceCents > 0 &&
      listing.priceCents > 0 &&
      isPriceDrop(previous.priceCents, listing.priceCents)
    ) {
      priceDropCandidates.push({
        listing,
        previousPriceCents: previous.priceCents,
        currentPriceCents: listing.priceCents,
        changePercent: percentChange(previous.priceCents, listing.priceCents),
      });
    }
  }

  return {
    newPersisted,
    newNotificationCandidates,
    priceDropCandidates,
    seenExternalIds,
  };
}

export function rowToStoredSnapshot(rows: ListingRow[]): StoredListingSnapshot[] {
  return rows.map((row) => ({
    externalId: row.external_id,
    priceCents: row.price_cents,
  }));
}
