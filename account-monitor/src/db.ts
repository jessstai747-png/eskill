// ============================================================
// db.ts — SQLite init, migrations, and data access layer
// ============================================================

import Database from 'better-sqlite3';
import { mkdirSync } from 'fs';
import { dirname, resolve } from 'path';
import { fileURLToPath } from 'url';
import type {
  SellerRow,
  SyncStateRow,
  EventRow,
  SellerHealthRow,
  HealthStatus,
  ApiSeller,
} from './types.js';

const __dirname = dirname(fileURLToPath(import.meta.url));

let db: Database.Database | null = null;

function log(msg: string): void {
  const ts = new Date().toISOString();
  console.log(`[${ts}] [db] ${msg}`);
}

/** Initialize SQLite and run migrations */
export function initDb(dbPath: string): Database.Database {
  const resolvedPath = resolve(__dirname, '..', dbPath);
  mkdirSync(dirname(resolvedPath), { recursive: true });

  db = new Database(resolvedPath);
  db.pragma('journal_mode = WAL');
  db.pragma('foreign_keys = ON');

  runMigrations(db);

  log(`Database initialized at ${resolvedPath}`);
  return db;
}

export function getDb(): Database.Database {
  if (!db) throw new Error('Database not initialized. Call initDb() first.');
  return db;
}

function runMigrations(db: Database.Database): void {
  db.exec(`
    CREATE TABLE IF NOT EXISTS sellers (
      seller_id   INTEGER PRIMARY KEY,
      nickname    TEXT NOT NULL DEFAULT '',
      email       TEXT NOT NULL DEFAULT '',
      status      TEXT NOT NULL DEFAULT 'unknown',
      created_at  TEXT NOT NULL DEFAULT '',
      last_synced_at TEXT,
      updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS sync_state (
      seller_id          INTEGER PRIMARY KEY,
      orders_last_sync_at TEXT NOT NULL,
      FOREIGN KEY (seller_id) REFERENCES sellers(seller_id)
    );

    CREATE TABLE IF NOT EXISTS events (
      id            INTEGER PRIMARY KEY AUTOINCREMENT,
      seller_id     INTEGER NOT NULL,
      type          TEXT NOT NULL,
      ts            TEXT NOT NULL,
      payload_json  TEXT NOT NULL DEFAULT '{}',
      FOREIGN KEY (seller_id) REFERENCES sellers(seller_id)
    );
    CREATE INDEX IF NOT EXISTS idx_events_seller_type ON events(seller_id, type);
    CREATE INDEX IF NOT EXISTS idx_events_ts ON events(ts);

    CREATE TABLE IF NOT EXISTS seller_health (
      seller_id           INTEGER PRIMARY KEY,
      last_paid_order_at  TEXT,
      days_since_last_sale INTEGER NOT NULL DEFAULT 0,
      is_new              INTEGER NOT NULL DEFAULT 0,
      movement_247_score  INTEGER NOT NULL DEFAULT 0,
      status              TEXT NOT NULL DEFAULT 'active',
      reasons_json        TEXT NOT NULL DEFAULT '[]',
      last_alert_sent     TEXT,
      updated_at          TEXT NOT NULL DEFAULT (datetime('now')),
      FOREIGN KEY (seller_id) REFERENCES sellers(seller_id)
    );
  `);
}

// ========== Sellers ==========

export function upsertSeller(seller: ApiSeller): void {
  const db = getDb();
  db.prepare(`
    INSERT INTO sellers (seller_id, nickname, email, status, created_at, last_synced_at, updated_at)
    VALUES (@seller_id, @nickname, @email, @status, @created_at, @last_synced_at, datetime('now'))
    ON CONFLICT(seller_id) DO UPDATE SET
      nickname       = excluded.nickname,
      email          = excluded.email,
      status         = excluded.status,
      last_synced_at = excluded.last_synced_at,
      updated_at     = datetime('now')
  `).run({
    seller_id: seller.id,
    nickname: seller.nickname,
    email: seller.email,
    status: seller.status,
    created_at: seller.created_at,
    last_synced_at: seller.last_synced_at,
  });
}

export function getAllSellers(): SellerRow[] {
  return getDb().prepare('SELECT * FROM sellers ORDER BY seller_id').all() as SellerRow[];
}

export function getSellerById(sellerId: number): SellerRow | undefined {
  return getDb().prepare('SELECT * FROM sellers WHERE seller_id = ?').get(sellerId) as SellerRow | undefined;
}

// ========== Sync State ==========

export function getSyncState(sellerId: number): SyncStateRow | undefined {
  return getDb().prepare('SELECT * FROM sync_state WHERE seller_id = ?').get(sellerId) as SyncStateRow | undefined;
}

export function upsertSyncState(sellerId: number, ordersLastSyncAt: string): void {
  getDb().prepare(`
    INSERT INTO sync_state (seller_id, orders_last_sync_at)
    VALUES (?, ?)
    ON CONFLICT(seller_id) DO UPDATE SET
      orders_last_sync_at = excluded.orders_last_sync_at
  `).run(sellerId, ordersLastSyncAt);
}

// ========== Events ==========

export function insertEvent(sellerId: number, type: string, ts: string, payload: unknown): void {
  getDb().prepare(`
    INSERT INTO events (seller_id, type, ts, payload_json)
    VALUES (?, ?, ?, ?)
  `).run(sellerId, type, ts, JSON.stringify(payload));
}

export function eventExists(sellerId: number, type: string, orderId: number): boolean {
  const row = getDb().prepare(`
    SELECT 1 FROM events
    WHERE seller_id = ? AND type = ? AND json_extract(payload_json, '$.id') = ?
    LIMIT 1
  `).get(sellerId, type, orderId);
  return !!row;
}

export function getEventsForSeller(sellerId: number, type: string, since: string): EventRow[] {
  return getDb().prepare(`
    SELECT * FROM events
    WHERE seller_id = ? AND type = ? AND ts >= ?
    ORDER BY ts ASC
  `).all(sellerId, type, since) as EventRow[];
}

export function countEventsToday(sellerId: number, type: string): number {
  const today = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
  const row = getDb().prepare(`
    SELECT COUNT(*) as cnt FROM events
    WHERE seller_id = ? AND type = ? AND ts >= ?
  `).get(sellerId, type, today + 'T00:00:00') as { cnt: number };
  return row.cnt;
}

export function sumAmountToday(sellerId: number): number {
  const today = new Date().toISOString().slice(0, 10);
  const row = getDb().prepare(`
    SELECT COALESCE(SUM(json_extract(payload_json, '$.total_amount')), 0) as total
    FROM events
    WHERE seller_id = ? AND type = 'order_paid' AND ts >= ?
  `).get(sellerId, today + 'T00:00:00') as { total: number };
  return row.total;
}

// ========== Seller Health ==========

export function getSellerHealth(sellerId: number): SellerHealthRow | undefined {
  return getDb().prepare('SELECT * FROM seller_health WHERE seller_id = ?').get(sellerId) as SellerHealthRow | undefined;
}

export function getAllSellerHealth(): SellerHealthRow[] {
  return getDb().prepare('SELECT * FROM seller_health ORDER BY seller_id').all() as SellerHealthRow[];
}

export function upsertSellerHealth(
  sellerId: number,
  data: {
    lastPaidOrderAt: string | null;
    daysSinceLastSale: number;
    isNew: boolean;
    movement247Score: number;
    status: HealthStatus;
    reasons: string[];
  },
): void {
  getDb().prepare(`
    INSERT INTO seller_health (seller_id, last_paid_order_at, days_since_last_sale, is_new, movement_247_score, status, reasons_json, updated_at)
    VALUES (@seller_id, @last_paid_order_at, @days_since_last_sale, @is_new, @movement_247_score, @status, @reasons_json, datetime('now'))
    ON CONFLICT(seller_id) DO UPDATE SET
      last_paid_order_at  = excluded.last_paid_order_at,
      days_since_last_sale = excluded.days_since_last_sale,
      is_new              = excluded.is_new,
      movement_247_score  = excluded.movement_247_score,
      status              = excluded.status,
      reasons_json        = excluded.reasons_json,
      updated_at          = datetime('now')
  `).run({
    seller_id: sellerId,
    last_paid_order_at: data.lastPaidOrderAt,
    days_since_last_sale: data.daysSinceLastSale,
    is_new: data.isNew ? 1 : 0,
    movement_247_score: data.movement247Score,
    status: data.status,
    reasons_json: JSON.stringify(data.reasons),
  });
}

export function updateLastAlertSent(sellerId: number): void {
  getDb().prepare(`
    UPDATE seller_health SET last_alert_sent = datetime('now') WHERE seller_id = ?
  `).run(sellerId);
}

export function closeDb(): void {
  if (db) {
    db.close();
    db = null;
  }
}
