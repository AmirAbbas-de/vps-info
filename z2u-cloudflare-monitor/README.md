# Z2U Telegram Monitor (Cloudflare Workers)

A production-oriented Telegram bot that monitors Z2U search pages and alerts you when a new listing appears or an existing listing becomes cheaper. It runs entirely on Cloudflare Workers + D1 + Cron Triggers. No VPS, no Docker, no nginx, no permanently running process.

```
Telegram
  |
  | HTTPS webhook
  v
Cloudflare Worker
  |
  +-- Telegram command handlers
  +-- Z2U monitor service
  +-- D1 database
  +-- Cron Trigger
  |
  v
Z2U
```

## Project status

- TypeScript strict build: **passes**
- Vitest tests: **38 passing**
- D1 local migration: **applies successfully**
- Wrangler local Worker started and `/health` responded
- Cloudflare deployment: **NOT performed in this environment** (no Cloudflare credentials were available)
- Cloudflare-side Z2U egress validation: **NOT performed** (no deployed Worker; sandbox TLS/egress to Z2U was blocked). See [Z2U investigation](#z2u-investigation-findings).

The project is complete as an implementation. The only honest limitation is that live Cloudflare network validation of Z2U could not be done from this sandbox; that must be completed after you deploy.

## Repository layout

```
z2u-cloudflare-monitor/
├── src/
│   ├── index.ts
│   ├── telegram/
│   │   ├── api.ts
│   │   ├── auth.ts
│   │   ├── commands.ts
│   │   ├── keyboards.ts
│   │   └── router.ts
│   ├── z2u/
│   │   ├── client.ts
│   │   ├── parser.ts
│   │   ├── search.ts
│   │   └── types.ts
│   ├── services/
│   │   ├── listing-service.ts
│   │   ├── monitor-service.ts
│   │   ├── notification-service.ts
│   │   └── seed-service.ts
│   ├── db/
│   │   └── queries.ts
│   ├── cron/
│   │   └── handler.ts
│   ├── utils/
│   │   ├── hashing.ts
│   │   ├── logger.ts
│   │   ├── money.ts
│   │   └── url.ts
│   └── types/
│       ├── db.ts
│       ├── env.ts
│       ├── telegram.ts
│       └── z2u.ts
├── migrations/
│   └── 0001_initial.sql
├── test/
│   ├── auth.test.ts
│   ├── listing.test.ts
│   ├── monitor.test.ts
│   ├── parser.test.ts
│   ├── pricing.test.ts
│   ├── router.test.ts
│   ├── search.test.ts
│   └── urls.test.ts
├── fixtures/
│   └── z2u-search-example.html
├── scripts/
│   └── setup-telegram-webhook.mjs
├── wrangler.toml
├── package.json
├── tsconfig.json
└── vitest.config.ts
```

## Prerequisites

- Node.js 18+ (tested with Node 22)
- npm
- A Cloudflare account
- A Telegram bot created with BotFather
- Your personal Telegram user id/chat id

## Telegram BotFather setup

1. Open @BotFather in Telegram.
2. `/newbot`, choose a name and username.
3. Copy the bot token. This is `TELEGRAM_BOT_TOKEN`.
4. Generate a random secret token, for example with:
   ```bash
   openssl rand -hex 24
   ```
   This is `TELEGRAM_WEBHOOK_SECRET`.
5. Get your numeric Telegram user id. If you do not already know it, send `/start` to any bot and note the numeric id, or use `@userinfobot`.

## Cloudflare setup

### 1. Install deps and authenticate Wrangler

```bash
cd z2u-cloudflare-monitor
npm install
npx wrangler login
```

### 2. Create the D1 database

```bash
npx wrangler d1 create z2u-monitor
```

The command prints a `database_id`. Replace the placeholder in `wrangler.toml`:

```toml
[[d1_databases]]
binding = "DB"
database_name = "z2u-monitor"
database_id = "PUT_THE_REAL_ID_HERE"
migrations_dir = "migrations"
```

### 3. Apply migrations

Local:

```bash
npx wrangler d1 migrations apply z2u-monitor --local
```

Remote:

```bash
npx wrangler d1 migrations apply z2u-monitor --remote
```

### 4. Configure secrets

```bash
npx wrangler secret put TELEGRAM_BOT_TOKEN
# paste the BotFather token

npx wrangler secret put TELEGRAM_WEBHOOK_SECRET
# paste the random secret token

npx wrangler secret put TELEGRAM_ALLOWED_USER_IDS
# paste a comma-separated list, e.g. 111222333 or 111222333,444555666
```

Optional notification chat override:

```bash
npx wrangler secret put TELEGRAM_CHAT_ID
```

### 5. Deploy

```bash
npm run deploy
# or:
npx wrangler deploy
```

Take the `workers.dev` URL from the deploy output, e.g. `https://z2u-monitor.<subdomain>.workers.dev`.

### 6. Register the Telegram webhook

```bash
export TELEGRAM_BOT_TOKEN=...
export WORKER_URL=https://z2u-monitor.<subdomain>.workers.dev
export TELEGRAM_WEBHOOK_SECRET=...
node ./scripts/setup-telegram-webhook.mjs
```

The script calls `setWebhook` with `secret_token`, then prints `getWebhookInfo`.

You can manually run the equivalent API calls if you prefer:

```bash
curl -s "https://api.telegram.org/bot<TOKEN>/setWebhook" \
  -H "Content-Type: application/json" \
  -d "{\"url\":\"https://z2u-monitor.<subdomain>.workers.dev/telegram/webhook\",\"secret_token\":\"$TELEGRAM_WEBHOOK_SECRET\",\"allowed_updates\":[\"message\",\"callback_query\"]}"

curl -s "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"
```

Never print the bot token in logs.

## Local development

Copy the example vars and start Wrangler:

```bash
cp .dev.vars.example .dev.vars
# edit .dev.vars with your secrets
npx wrangler dev --local
```

The Worker listens on `http://localhost:8787`.

```bash
curl http://localhost:8787/health
curl -X POST http://localhost:8787/cdn-cgi/local/scheduled
```

Local Telegram webhook testing requires a public HTTPS endpoint. Options:

- Use Wrangler's `--remote` after deploy (simplest).
- Use a tunnel such as Cloudflare Tunnel, ngrok, or a similar service and point Telegram at the public URL ending in `/telegram/webhook`.

## Commands

| Command | Description |
| --- | --- |
| `/start` | Show welcome/help |
| `/help` | Show detailed help |
| `/add` | Start an add-monitor conversation; send a Z2U `ItemStore.html?...` URL |
| `/list` | List all monitors with inline Check/Enable/Disable/Delete buttons |
| `/remove <id>` | Remove a monitor |
| `/enable <id>` | Enable a monitor |
| `/disable <id>` | Disable a monitor |
| `/check [id]` | Check one monitor or all active monitors |
| `/status` | Show worker/D1/webhook status |
| `/cancel` | Cancel pending `/add` |

## Monitor management

### Initial seeds

The first time the Worker runs, it checks `app_meta`. If the D1 database is genuinely new (no `initial_seed_done` key) and no monitors exist, it seeds:

1. **ChatGPT Plus** — first supplied URL (derived keyword `chatgpt plus`)
2. **Spotify** — second supplied URL (derived keyword `spotify`)

The seed flag is set after the first seed, so if you later delete both monitors they are not automatically recreated.

### `/add` flow

1. Send `/add`.
2. Send a full Z2U `https://www.z2u.com/ItemStore.html?search=...` URL.
3. The bot validates the host/path, decodes the opaque `search` parameter, extracts the stable keyword, normalizes the URL, and checks duplicates by normalized URL and by stable search fingerprint.
4. The bot inserts the monitor with `initialized = 0`.
5. It immediately performs an initial baseline fetch.
6. Baseline listings are stored silently; no "new listing" alerts are sent for existing products.

### Duplicate prevention

- `normalized_url` has a unique constraint.
- `search_fingerprint` has a unique constraint.
- The fingerprint ignores the expiry timestamp, so `keyword=chatgpt plus&expiry=1000` and `keyword=chatgpt plus&expiry=999999` are treated as equivalent.

### List / inline actions

`/list` sends a numbered list plus an inline keyboard. Each row has:

- **Check** — run one check
- **Enable/Disable** — toggle the monitor
- **Delete** — ask for confirmation, then delete

Callback queries are authorized again on every call; an unauthorized user cannot manipulate monitors by guessing IDs.

## Cron behavior

`wrangler.toml` configures:

```toml
[triggers]
crons = ["*/5 * * * *"]
```

Change this value to adjust the scheduling interval. The app reads `CHECK_INTERVAL_MINUTES` only for `/status` display; the actual Worker cron schedule is controlled by `wrangler.toml`.

Each scheduled run:

1. Seeds a genuinely new installation.
2. Picks up to `MAX_MONITORS_PER_CRON_RUN` enabled monitors (`MAX_MONITORS_PER_CRON_RUN`, default 3).
3. Uses D1 locks (`check_lock_until`) to prevent overlapping checks for the same monitor.
4. Checks each monitor independently; one failure does not stop the rest.
5. Records `last_error` on the affected monitor.

## Z2U retrieval adapter

The retrieval path is in `src/z2u/`.

- `client.ts` uses Worker `fetch()` with a timeout, status validation, limited retries for 429/5xx, and exponential backoff.
- `parser.ts` extracts product cards from HTML using stable `/product-<id>/...` anchor URLs.
- Invalid/anti-bot/empty responses intentionally throw instead of returning an empty listing set, so previous valid state is preserved.

### Listing identity

Each Z2U search-result card links to a product URL such as:

```
https://www.z2u.com/product-7942/CG-Plus-personal-exclusive-Account.html
```

The numeric product id (`7942`) is the stable identity and is stored in `listings.external_id` with a `UNIQUE(monitor_id, external_id)` rule. This avoids identifying listings by title, which may change over time.

### Price handling

- Prices are parsed to integer minor units (`price_cents`).
- No floating-point comparison is used.
- `price_history` is written only when a price changes or a listing first appears; unchanged prices do not create redundant history rows.

## D1 schema summary

| Table | Purpose |
| --- | --- |
| `app_meta` | Idempotent migration/seed flags |
| `monitors` | Monitor definitions, enable flag, initialization flag, last check/error, lock state |
| `listings` | Current listing snapshots with stable external id |
| `price_history` | Price changes and first-seen price points |
| `notification_events` | Idempotency keys for new-listing/price-drop notifications |
| `telegram_state` | Per-user pending conversations (e.g. awaiting a URL for `/add`) |

## Money and notifications

- Price increase: history is saved, no Telegram notification.
- Price unchanged: no history row, no notification.
- Price decrease: history is saved, one price-drop notification is sent (idempotent per new price).
- New listing: one new-listing notification is sent per listing, deduplicated by `notification_events.event_key`.
- All message text is HTML-escaped before being sent to Telegram.

## Configuration variables

Non-secret variables live in `wrangler.toml [vars]`:

| Variable | Default | Purpose |
| --- | --- | --- |
| `CHECK_INTERVAL_MINUTES` | `5` | Displayed in `/status`; actual cron lives in `wrangler.toml` |
| `Z2U_REQUEST_TIMEOUT_MS` | `15000` | Fetch timeout |
| `Z2U_RETRIES` | `2` | Retry attempts for 429/5xx/network errors |
| `MAX_MONITORS_PER_CRON_RUN` | `3` | Limit checks per cron invocation to stay within Worker limits |
| `MAX_Z2U_PAGES` | `1` | Reserved for future pagination support; version 1 parses the first page |
| `LOG_LEVEL` | `info` | `debug`, `info`, `warn`, `error` |
| `Z2U_REFRESH_EXPIRY` | `true` | Rebuild the opaque `search` value with a fresh expiry instead of relying on the supplied expiry |
| `Z2U_EXPIRY_WINDOW_MINUTES` | `1440` | Fresh expiry window when rebuilding |

Secrets (never in `wrangler.toml`):

- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_WEBHOOK_SECRET`
- `TELEGRAM_ALLOWED_USER_IDS`
- `TELEGRAM_CHAT_ID` (optional notification chat override)

## Testing

```bash
npm run build
npm test
```

Tests cover:

- search parameter decoding/encoding and supplied URL expiration
- URL normalization and duplicate prevention
- equivalent search duplicate prevention
- parser behavior with a fixture
- stable listing identity
- initial snapshot behavior
- new listing detection
- price drop detection
- no notification for unchanged/increased prices
- non-duplicate notification event generation (by event key design)
- invalid page safety
- money comparison
- unauthorized Telegram access
- malformed Telegram update handling

## Cloudflare limits and batching

- The cron handler runs at most `MAX_MONITORS_PER_CRON_RUN` monitors per invocation (default 3).
- Each monitor check uses a D1 lock that expires after 10 minutes; a stale lock cannot block forever.
- Normal D1/Worker CPU quotas are generally fine for a handful of search pages. If you monitor hundreds of pages, increase the cron frequency and consider a multi-check continuation design.
- This project does not use Browser Rendering. If Z2U later blocks datacenter requests or fully relies on client-side rendering, the retrieval adapter is isolated in `src/z2u/client.ts` and `src/z2u/parser.ts`.

## Security notes

- Webhook requests are rejected unless Telegram's `X-Telegram-Bot-Api-Secret-Token` header matches `TELEGRAM_WEBHOOK_SECRET`.
- `TELEGRAM_ALLOWED_USER_IDS` is required. Unauthorized users receive no data and no reply.
- `callback_query` actions are re-authorized.
- Secrets are stored with `wrangler secret`; no secret is committed.
- Health endpoint exposes only safe operational info: `ok`, `service`, `worker`, `db`, `monitors`, `seeded`.

## How to verify the bot is operating correctly

1. Deploy and register the webhook.
2. `curl https://z2u-monitor.<subdomain>.workers.dev/health` — expect `{"ok":true,...,"db":"ok"}`.
3. Open your bot in Telegram and send `/start` with the authorized account.
4. Send `/list` — the default ChatGPT Plus and Spotify monitors should appear (if the seed ran).
5. Send `/check` — a check summary should come back. If Z2U fetch fails, the status will show an error; this is the isolated retrieval limitation.
6. Send `/status` — expect worker/D1 webhook status.
7. Wait for the cron interval, then inspect:
   ```
   curl https://z2u-monitor.<subdomain>.workers.dev/health
   ```
   and Cloudflare Workers logs for `monitor_check_started` / `monitor_check_completed`.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Webhook not receiving updates | Run `node ./scripts/setup-telegram-webhook.mjs`, then `getWebhookInfo`; confirm `secret_token` matches |
| 403 from webhook | `TELEGRAM_WEBHOOK_SECRET` does not match the `setWebhook` secret |
| Bot ignores you | `TELEGRAM_ALLOWED_USER_IDS` does not contain your numeric Telegram user id |
| `/check` returns `Failed to fetch Z2U` | Z2U may be blocking the Worker datacenter IP, or the page/parser changed. Check logs; do not assume listings disappeared. |
| Default monitors missing | Confirm `/health` shows `seeded: true`. If you removed them after seeding, they will not re-seed. |

## Z2U investigation findings

### Decoded search parameter

The supplied URL `search` values are base64 of a URL-encoded payload.

Base64-decode then URL-decode once yields:

```
ChatGPT Plus:
keyword%3Dchatgpt%2520plus%26expiry%3D1788851645752
```

URL-decode and parse again yields:

```
keyword=chatgpt plus
expiry=1788851645752
```

Spotify:

```
keyword%3Dspotify%26expiry%3D1788851676282
keyword=spotify
expiry=1788851676282
```

The expiry values are Unix epoch milliseconds:

- `1788851645752` → 2026-09-08T07:14:05.752Z
- `1788851676282` → 2026-09-08T07:14:36.282Z

These are less than 24 hours from the time the links were generated (the sandbox date is 2026-09-07). They are time-bound shared-search links. The bot does not rely on the supplied expiry.

### Encoding that reproduces the supplied links

The exact encoded form is:

```ts
payload = "keyword=" + encodeURIComponent(keyword) + "&expiry=" + expiry;
searchParam = btoa(encodeURIComponent(payload));
```

`encodeSearchParam("chatgpt plus", 1788851645752)` reproduces the supplied ChatGPT search value. `encodeSearchParam("spotify", 1788851676282)` reproduces the Spotify value.

### Do the supplied URLs expire?

Yes, in the sense that the opaque `search` parameter carries an `expiry` timestamp. The monitor treats that expiry as metadata only. It stores `keyword`, base path, and filter params as the stable definition, and rebuilds the `search` value with a fresh expiry at fetch time (when `Z2U_REFRESH_EXPIRY=true`). If Z2U rejects a rebuilt URL, the only remaining fallback is manual inspection of the URL format.

### Retrieval mechanism

- A page-rendering fetch of both supplied URLs returned product cards with titles, service/seller labels, `N Offers`, `from $...`, sold-out markers, and `/product-<id>/...html` links.
- The search page is usable by the bot's HTML parser; no public JSON API was found and no authentication was used.
- Direct local `fetch()`/`curl` to `www.z2u.com` from this sandbox failed at TLS/network layer (`SSL_ERROR_SYSCALL`, `Network connection lost`). That prevented local live-parser validation here.
- The Worker adapter uses normal `fetch()` + HTML parsing. It does not attempt CAPTCHA, authentication, or anti-bot bypasses.

### Cloudflare compatibility caveat

This sandbox did not have Cloudflare credentials, so no Worker was deployed or executed from Cloudflare infrastructure. The parser was validated against a fixture and the `fetch_page` extraction of the real pages, but the Cloudflare-to-Z2U egress path must be validated after deployment. If Cloudflare Workers egress is blocked by Z2U, the isolated limitation is `src/z2u/client.ts`; the rest of the bot remains functional.
