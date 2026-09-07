import type { Env } from "../types/env";

export interface TelegramAuthConfig {
  allowedUserIds: string;
  chatId?: string;
}

export function parseAllowedUserIds(value: string | undefined): number[] {
  if (!value || value.trim().length === 0) {
    return [];
  }
  const ids = new Set<number>();
  for (const part of value.split(",")) {
    const trimmed = part.trim();
    if (/^[0-9]+$/.test(trimmed)) {
      ids.add(Number(trimmed));
    }
  }
  return Array.from(ids);
}

export function authorizedChatIds(config: TelegramAuthConfig): number[] {
  const ids = parseAllowedUserIds(config.allowedUserIds);
  if (config.chatId && /^[0-9]+$/.test(config.chatId)) {
    const chatId = Number(config.chatId);
    if (!ids.includes(chatId)) {
      ids.push(chatId);
    }
  }
  return ids;
}

export function isAuthorized(
  config: TelegramAuthConfig,
  userId: number | undefined
): boolean {
  if (userId === undefined) {
    return false;
  }
  return authorizedChatIds(config).includes(userId);
}

export function authConfigFor(env: Env): TelegramAuthConfig {
  return {
    allowedUserIds: env.TELEGRAM_ALLOWED_USER_IDS ?? "",
    chatId: env.TELEGRAM_CHAT_ID,
  };
}
