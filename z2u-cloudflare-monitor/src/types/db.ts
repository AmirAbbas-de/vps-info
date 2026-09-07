export interface MonitorRow {
  id: number;
  name: string;
  original_url: string;
  normalized_url: string;
  search_keyword: string | null;
  search_definition: string;
  search_fingerprint: string;
  enabled: number;
  initialized: number;
  created_at: string;
  updated_at: string;
  last_checked_at: string | null;
  last_success_at: string | null;
  last_error: string | null;
  last_error_at: string | null;
  error_notified_at: string | null;
  check_lock_until: string | null;
}

export interface ListingRow {
  id: number;
  monitor_id: number;
  external_id: string;
  title: string;
  seller: string | null;
  price_cents: number;
  currency: string;
  url: string;
  availability: string;
  first_seen_at: string;
  last_seen_at: string;
  updated_at: string;
}

export interface PriceHistoryRow {
  id: number;
  listing_id: number;
  price_cents: number;
  currency: string;
  checked_at: string;
}

export interface NotificationEventRow {
  id: number;
  monitor_id: number;
  listing_id: number | null;
  event_type: string;
  event_key: string;
  event_value: string | null;
  created_at: string;
}

export interface TelegramStateRow {
  user_id: number;
  state: string;
  data: string | null;
  updated_at: string;
}

export interface AppMetaRow {
  key: string;
  value: string;
  updated_at: string;
}

export interface PendingAddState {
  state: "awaiting_z2u_url";
  messageId?: number;
}
