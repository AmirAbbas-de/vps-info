import { describe, expect, it } from "vitest";
import {
  authorizedChatIds,
  isAuthorized,
  parseAllowedUserIds,
  type TelegramAuthConfig,
} from "../src/telegram/auth";

describe("Telegram access control", () => {
  it("parses one or more allowed user ids", () => {
    expect(parseAllowedUserIds("123, 456,abc")).toEqual([123, 456]);
    expect(parseAllowedUserIds(undefined)).toEqual([]);
    expect(parseAllowedUserIds("")).toEqual([]);
  });

  it("rejects unauthorized users", () => {
    const config: TelegramAuthConfig = { allowedUserIds: "111" };
    expect(isAuthorized(config, 111)).toBe(true);
    expect(isAuthorized(config, 999)).toBe(false);
    expect(isAuthorized(config, undefined)).toBe(false);
  });

  it("honors an explicit chat id", () => {
    const config: TelegramAuthConfig = { allowedUserIds: "111", chatId: "222" };
    expect(authorizedChatIds(config)).toEqual([111, 222]);
    expect(isAuthorized(config, 222)).toBe(true);
  });
});
