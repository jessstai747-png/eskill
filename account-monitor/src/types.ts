// ============================================================
// types.ts — Shared TypeScript types for Account Monitor
// ============================================================

/** Seller as returned by eskill API */
export interface ApiSeller {
  id: number;
  ml_user_id: number;
  nickname: string;
  email: string;
  site_id: string;
  status: string;
  last_synced_at: string | null;
  created_at: string;
}

/** Order as returned by eskill API */
export interface ApiOrder {
  id: number;
  status: string;
  total_amount: number;
  date_created: string;
  buyer: {
    id: number;
    nickname: string;
  };
  order_items: Array<{
    item: {
      id: string;
      title: string;
      variation_id: string | null;
    };
    quantity: number;
    unit_price: number;
  }>;
  shipping: { id: number } | null;
  payments: Array<{
    id: number;
    status: string;
    transaction_amount: number;
    payment_type: string;
  }>;
  account_nickname: string;
}

/** Paginated orders response */
export interface OrdersResponse {
  success: boolean;
  source: string;
  orders: ApiOrder[];
  page: number;
  pages: number;
  limit: number;
  total: number;
  has_more: boolean;
}

/** Sellers response */
export interface SellersResponse {
  success: boolean;
  sellers: ApiSeller[];
}

/** Health response */
export interface HealthResponse {
  success: boolean;
  service: string;
  version: string;
  time: string;
  db: string;
}

// ---- DB row types ----

export interface SellerRow {
  seller_id: number;
  nickname: string;
  email: string;
  status: string;
  created_at: string;
  last_synced_at: string | null;
  updated_at: string;
}

export interface SyncStateRow {
  seller_id: number;
  orders_last_sync_at: string;
}

export interface EventRow {
  id: number;
  seller_id: number;
  type: string;
  ts: string;
  payload_json: string;
}

export type HealthStatus = 'active' | 'warning' | 'alert' | 'critical' | 'new' | 'watch_247';

export interface SellerHealthRow {
  seller_id: number;
  last_paid_order_at: string | null;
  days_since_last_sale: number;
  is_new: number; // SQLite boolean (0/1)
  movement_247_score: number;
  status: HealthStatus;
  reasons_json: string;
  last_alert_sent: string | null;
  updated_at: string;
}

// ---- Analyzer outputs ----

export interface Movement247Result {
  score: number;
  reasons: string[];
}

export interface AnalysisResult {
  sellerId: number;
  nickname: string;
  lastPaidOrderAt: string | null;
  daysSinceLastSale: number;
  isNew: boolean;
  movement247: Movement247Result;
  status: HealthStatus;
  reasons: string[];
}

// ---- Config ----

export interface Config {
  eskillBaseUrl: string;
  eskillToken: string;
  telegramBotToken: string;
  telegramChatId: string;
  dbPath: string;
  daysWarning: number;
  daysAlert: number;
  daysCritical: number;
  newAccountDays: number;
  score247Threshold: number;
}
