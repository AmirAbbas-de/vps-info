import { describe, expect, it } from "vitest";
import { diffListings, type StoredListingSnapshot } from "../src/services/listing-service";
import type { Z2uListing } from "../src/types/z2u";

function listing(externalId: string, priceCents: number): Z2uListing {
  return {
    externalId,
    title: `Product ${externalId}`,
    seller: null,
    priceCents,
    currency: "USD",
    url: `https://www.z2u.com/product-${externalId}/x.html`,
    availability: "available",
    offerCount: 1,
  };
}

describe("listing diff", () => {
  it("initial snapshot persists listings but does not notify", () => {
    const scanned = [listing("1", 1000), listing("2", 2000)];
    const result = diffListings([], scanned, true);
    expect(result.newPersisted).toHaveLength(2);
    expect(result.newNotificationCandidates).toHaveLength(0);
    expect(result.priceDropCandidates).toHaveLength(0);
  });

  it("detects a genuinely new listing on a later check", () => {
    const stored: StoredListingSnapshot[] = [{ externalId: "1", priceCents: 1000 }];
    const scanned = [listing("1", 1000), listing("2", 2000)];
    const result = diffListings(stored, scanned, false);
    expect(result.newPersisted.map((l) => l.externalId)).toEqual(["2"]);
    expect(result.newNotificationCandidates.map((l) => l.externalId)).toEqual(["2"]);
  });

  it("detects a price drop for an existing listing", () => {
    const stored: StoredListingSnapshot[] = [{ externalId: "1", priceCents: 920 }];
    const scanned = [listing("1", 750)];
    const result = diffListings(stored, scanned, false);
    expect(result.priceDropCandidates).toHaveLength(1);
    expect(result.priceDropCandidates[0].previousPriceCents).toBe(920);
    expect(result.priceDropCandidates[0].currentPriceCents).toBe(750);
  });

  it("does not notify when price is unchanged", () => {
    const stored: StoredListingSnapshot[] = [{ externalId: "1", priceCents: 750 }];
    const scanned = [listing("1", 750)];
    const result = diffListings(stored, scanned, false);
    expect(result.priceDropCandidates).toHaveLength(0);
  });

  it("does not notify price increases", () => {
    const stored: StoredListingSnapshot[] = [{ externalId: "1", priceCents: 750 }];
    const scanned = [listing("1", 920)];
    const result = diffListings(stored, scanned, false);
    expect(result.priceDropCandidates).toHaveLength(0);
  });
});
