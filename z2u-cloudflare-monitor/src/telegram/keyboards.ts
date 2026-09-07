import type { MonitorRow } from "../types/db";

export interface InlineButton {
  text: string;
  callback_data: string;
}

export function monitorListKeyboard(monitors: MonitorRow[]): {
  inline_keyboard: InlineButton[][];
} {
  const rows: InlineButton[][] = [];
  for (const monitor of monitors) {
    const row: InlineButton[] = [
      { text: "Check", callback_data: `check:${monitor.id}` },
    ];
    row.push(
      monitor.enabled === 1
        ? { text: "Disable", callback_data: `toggle:${monitor.id}` }
        : { text: "Enable", callback_data: `toggle:${monitor.id}` }
    );
    row.push({ text: "Delete", callback_data: `remove:${monitor.id}` });
    rows.push(row);
  }
  return { inline_keyboard: rows };
}

export function removeConfirmKeyboard(monitorId: number): {
  inline_keyboard: InlineButton[][];
} {
  return {
    inline_keyboard: [
      [
        { text: "Yes, delete", callback_data: `confirm_remove:${monitorId}` },
        { text: "Cancel", callback_data: `cancel_cb` },
      ],
    ],
  };
}

export function addCancelKeyboard(): {
  inline_keyboard: InlineButton[][];
} {
  return {
    inline_keyboard: [
      [{ text: "Cancel", callback_data: "cancel_cb" }],
    ],
  };
}
