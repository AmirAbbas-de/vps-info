import { describe, expect, it } from "vitest";
import {
  compareCents,
  formatCents,
  isPriceDrop,
  isPriceIncrease,
  parsePriceToCents,
  percentChange,
} from "../src/utils/money";

describe("money helpers", () => {
  it("parses decimal prices into integer cents without float math", () => {
    expect(parsePriceToCents("16.5")).toBe(1650);
    expect(parsePriceToCents("21.28")).toBe(2128);
    expect(parsePriceToCents("232.78")).toBe(23278);
    expect(parsePriceToCents("$1,234.56")).toBe(123456);
    expect(parsePriceToCents("1.2k")).toBe(120000);
    expect(parsePriceToCents("invalid")).toBeNull();
    expect(parsePriceToCents("")).toBeNull();
  });

  it("formats cents", () => {
    expect(formatCents(1650)).toBe("$16.50");
    expect(formatCents(2128)).toBe("$21.28");
    expect(formatCents(120000)).toBe("$1,200.00");
  });

  it("compares integer prices", () => {
    expect(compareCents(1000, 1000)).toBe(0);
    expect(compareCents(1000, 2000)).toBe(-1);
    expect(compareCents(2000, 1000)).toBe(1);
  });

  it("detects price direction", () => {
    expect(isPriceDrop(920, 750)).toBe(true);
    expect(isPriceDrop(750, 750)).toBe(false);
    expect(isPriceIncrease(750, 920)).toBe(true);
  });

  it("computes human percent change", () => {
    expect(percentChange(920, 750)).toBe(18.48);
    expect(percentChange(1000, 800)).toBe(20);
    expect(percentChange(0, 10)).toBe(0);
  });
});
