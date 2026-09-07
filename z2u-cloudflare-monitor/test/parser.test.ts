import { readFileSync } from "node:fs";
import { describe, expect, it } from "vitest";
import { parseSearchResults } from "../src/z2u/parser";

const fixture = readFileSync(new URL("../fixtures/z2u-search-example.html", import.meta.url), "utf8");

describe("Z2U search parser", () => {
  it("parses product listings from the fixture", () => {
    const result = parseSearchResults(fixture, "https://www.z2u.com/ItemStore.html");
    expect(result.valid).toBe(true);
    expect(result.listings).toHaveLength(3);
    expect(result.listings[0].externalId).toBe("7942");
    expect(result.listings[0].priceCents).toBe(1650);
    expect(result.listings[0].seller).toBe("GlobalAccount");
    expect(result.listings[0].availability).toBe("available");
    expect(result.listings[0].url).toBe("https://www.z2u.com/product-7942/CG-Plus-personal-exclusive-Account.html");

    expect(result.listings[1].externalId).toBe("31019");
    expect(result.listings[1].priceCents).toBe(2128);
  });

  it("marks a sold out listing with no displayed price", () => {
    const result = parseSearchResults(fixture, "https://www.z2u.com/ItemStore.html");
    const soldOut = result.listings.find((l) => l.externalId === "672315");
    expect(soldOut).toBeDefined();
    expect(soldOut?.availability).toBe("sold_out");
    expect(soldOut?.priceCents).toBe(0);
  });

  it("throws on an unrecognized page instead of returning empty listings", () => {
    expect(() =>
      parseSearchResults("<html><body><h1>Cloudflare challenge</h1></body></html>", "https://www.z2u.com/ItemStore.html")
    ).toThrow();
  });

  it("accepts an explicit zero-result page", () => {
    const zero =
      '<html><body><div>Product Name: nope</div><div>Product Contain 0 Offers</div><div>Unable to find desired results. click feedback</div></body></html>';
    const result = parseSearchResults(zero, "https://www.z2u.com/ItemStore.html");
    expect(result.hasZeroResults).toBe(true);
    expect(result.listings).toHaveLength(0);
  });
});
