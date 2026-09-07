import { describe, expect, it } from "vitest";
import { handleTelegramWebhook } from "../src/telegram/router";
import type { Env } from "../src/types/env";

function env(overrides: Partial<Env> = {}): Env {
  return {
    DB: {} as never,
    TELEGRAM_WEBHOOK_SECRET: "secret-token",
    TELEGRAM_ALLOWED_USER_IDS: "111",
    ...overrides,
  };
}

describe("Telegram webhook", () => {
  it("rejects requests without the webhook secret", async () => {
    const response = await handleTelegramWebhook(
      new Request("https://worker.example/telegram/webhook", { method: "POST", body: "{}" }),
      env()
    );
    expect(response.status).toBe(403);
  });

  it("rejects requests with an incorrect secret", async () => {
    const request = new Request("https://worker.example/telegram/webhook", {
      method: "POST",
      headers: { "X-Telegram-Bot-Api-Secret-Token": "wrong" },
      body: JSON.stringify({ update_id: 1 }),
    });
    const response = await handleTelegramWebhook(request, env());
    expect(response.status).toBe(403);
  });

  it("accepts a valid secret and reports malformed JSON", async () => {
    const request = new Request("https://worker.example/telegram/webhook", {
      method: "POST",
      headers: { "X-Telegram-Bot-Api-Secret-Token": "secret-token" },
      body: "{bad json",
    });
    const response = await handleTelegramWebhook(request, env());
    expect(response.status).toBe(400);
  });

  it("accepts malformed update without crashing", async () => {
    const request = new Request("https://worker.example/telegram/webhook", {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-Telegram-Bot-Api-Secret-Token": "secret-token" },
      body: JSON.stringify({ foo: "bar" }),
    });
    const response = await handleTelegramWebhook(request, env());
    expect(response.status).toBe(400);
  });

  it("returns ok for an unauthorized user without disclosing configuration", async () => {
    const request = new Request("https://worker.example/telegram/webhook", {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-Telegram-Bot-Api-Secret-Token": "secret-token" },
      body: JSON.stringify({ update_id: 3, message: { message_id: 1, chat: { id: 999, type: "private" }, from: { id: 999 }, date: 1, text: "/start" } }),
    });
    const response = await handleTelegramWebhook(request, env());
    expect(response.status).toBe(200);
  });
});
