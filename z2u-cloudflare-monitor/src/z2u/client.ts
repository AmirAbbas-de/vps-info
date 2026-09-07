import type { Env } from "../types/env";
import type { Z2uFetchResult } from "../types/z2u";
import { structuredLog, type LogLevel } from "../utils/logger";

type LogConfig = LogLevel | string | undefined;

const USER_AGENT =
  "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36";

export interface Z2uClientError extends Error {
  code:
    | "FETCH_FAILED"
    | "FETCH_FORBIDDEN"
    | "FETCH_RATE_LIMITED"
    | "FETCH_EMPTY"
    | "FETCH_HTTP";
  status?: number;
  retry?: boolean;
}

function clientError(
  message: string,
  code: Z2uClientError["code"],
  status?: number,
  retry = false
): Z2uClientError {
  const err = new Error(message) as Z2uClientError;
  err.code = code;
  if (status !== undefined) {
    err.status = status;
  }
  err.retry = retry;
  return err;
}

function timeoutSignal(ms: number): AbortSignal {
  if (typeof AbortSignal !== "undefined" && "timeout" in AbortSignal) {
    return AbortSignal.timeout(ms);
  }
  const controller = new AbortController();
  setTimeout(() => controller.abort(), ms);
  return controller.signal;
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function retryAfter(response?: Response): number | null {
  const header = response?.headers.get("retry-after");
  if (!header) {
    return null;
  }
  const seconds = Number(header);
  if (Number.isFinite(seconds)) {
    return Math.min(60, Math.max(1, seconds));
  }
  return 2;
}

/**
 * Fetch a Z2U search page from Cloudflare Workers using the built-in fetch().
 * Includes a timeout, status validation, a small number of retries for 429/5xx
 * and conservative exponential backoff. It does not solve or bypass CAPTCHA.
 */
export async function fetchZ2uSearchPage(
  url: string,
  env: Env,
  configuredLogLevel?: LogConfig
): Promise<Z2uFetchResult> {
  const timeoutMs = Number(env.Z2U_REQUEST_TIMEOUT_MS ?? "15000") || 15000;
  const maxRetries = Math.max(0, Number(env.Z2U_RETRIES ?? "2") || 2);
  const baseHeaders = {
    "User-Agent": USER_AGENT,
    Accept: "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
    "Accept-Language": "en-US,en;q=0.9",
  };

  let lastError: Z2uClientError | null = null;

  for (let attempt = 0; attempt <= maxRetries; attempt += 1) {
    try {
      const response = await fetch(url, {
        method: "GET",
        redirect: "follow",
        headers: baseHeaders,
        signal: timeoutSignal(timeoutMs),
        cf: {
          cacheTtl: Math.min(120, Math.max(30, timeoutMs / 1000)),
          cacheEverything: true,
        },
      });

      if (response.status === 429) {
        structuredLog("warn", "z2u_rate_limited", { status: 429, attempt: attempt + 1 }, configuredLogLevel);
        lastError = clientError("Z2U rate limited (HTTP 429).", "FETCH_RATE_LIMITED", 429, true);
        if (attempt < maxRetries) {
          await sleep((retryAfter(response) ?? 2) * 1000);
          continue;
        }
        throw lastError;
      }

      if (response.status === 403 || response.status === 401) {
        throw clientError("Z2U refused the request (HTTP 403/401).", "FETCH_FORBIDDEN", response.status);
      }

      if (response.status >= 500) {
        lastError = clientError(`Z2U server error (HTTP ${response.status}).`, "FETCH_HTTP", response.status, true);
        if (attempt < maxRetries) {
          await sleep(Math.min(2000 * 2 ** attempt, 8000));
          continue;
        }
        throw lastError;
      }

      if (response.status < 200 || response.status >= 300) {
        throw clientError(`Unexpected Z2U HTTP status ${response.status}.`, "FETCH_HTTP", response.status);
      }

      const html = await response.text();
      if (!html || html.trim().length < 200) {
        throw clientError("Z2U returned an unexpectedly empty response.", "FETCH_EMPTY", response.status);
      }

      structuredLog("debug", "z2u_fetch_ok", { status: response.status, bytes: html.length }, configuredLogLevel);
      return { html, status: response.status };
    } catch (error) {
      if (error instanceof Error && (error as Z2uClientError).code !== undefined) {
        const e = error as Z2uClientError;
        if (!e.retry || attempt >= maxRetries) {
          throw e;
        }
        lastError = e;
        await sleep(Math.min(1000 * 2 ** attempt, 4000));
        continue;
      }
      lastError = clientError(
        `Failed to fetch Z2U: ${error instanceof Error ? error.message : String(error)}`,
        "FETCH_FAILED"
      );
      if (attempt < maxRetries) {
        await sleep(Math.min(1000 * 2 ** attempt, 4000));
        continue;
      }
      throw lastError;
    }
  }

  throw lastError ?? clientError("Z2U fetch failed.", "FETCH_FAILED");
}
