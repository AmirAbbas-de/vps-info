export type LogLevel = "debug" | "info" | "warn" | "error";

const LEVELS: Record<LogLevel, number> = {
  debug: 10,
  info: 20,
  warn: 30,
  error: 40,
};

export function parseLogLevel(value?: string): LogLevel {
  switch ((value ?? "info").toLowerCase()) {
    case "debug":
      return "debug";
    case "warn":
      return "warn";
    case "error":
      return "error";
    default:
      return "info";
  }
}

export function structuredLog(
  level: LogLevel,
  event: string,
  fields: Record<string, unknown> = {},
  configuredLevel: LogLevel | string = "info"
): void {
  const effectiveLevel = parseLogLevel(configuredLevel);
  if (LEVELS[level] < LEVELS[effectiveLevel]) {
    return;
  }
  const record: Record<string, unknown> = {
    event,
    level,
    ts: new Date().toISOString(),
    ...fields,
  };
  const output = JSON.stringify(record);
  if (level === "error") {
    console.error(output);
  } else if (level === "warn") {
    console.warn(output);
  } else {
    console.log(output);
  }
}
