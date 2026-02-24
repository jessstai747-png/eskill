// ============================================================
// collector.ts — Data collection job (sellers + orders sync)
// ============================================================

import type { Config } from './types.js';
import { EskillClient } from './eskillClient.js';
import {
  upsertSeller,
  getAllSellers,
  getSyncState,
  upsertSyncState,
  insertEvent,
  eventExists,
} from './db.js';

function log(msg: string): void {
  const ts = new Date().toISOString();
  console.log(`[${ts}] [collector] ${msg}`);
}

/**
 * Main collection job:
 * 1. Fetch sellers from API and upsert locally
 * 2. For each seller, fetch paid orders since last sync (delta)
 * 3. Save new orders as events
 * 4. Update sync state
 */
export async function runCollector(config: Config): Promise<void> {
  const client = new EskillClient(config);

  // Step 1: Health check
  log('Running health check...');
  try {
    const health = await client.checkHealth();
    log(`API healthy: ${health.service} v${health.version} — db=${health.db}`);
  } catch (err) {
    log(`WARNING: Health check failed: ${err instanceof Error ? err.message : err}`);
    log('Continuing anyway...');
  }

  // Step 2: Sync sellers
  log('Syncing sellers...');
  const sellersResp = await client.listSellers();

  for (const seller of sellersResp.sellers) {
    upsertSeller(seller);
  }
  log(`Upserted ${sellersResp.sellers.length} seller(s)`);

  // Step 3: Sync orders for each seller
  const sellers = getAllSellers();
  const now = new Date();

  for (const seller of sellers) {
    const syncState = getSyncState(seller.seller_id);

    // Default: 30 days back on first sync
    let dateFrom: string;
    if (syncState) {
      dateFrom = syncState.orders_last_sync_at;
    } else {
      const thirtyDaysAgo = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
      dateFrom = thirtyDaysAgo.toISOString().slice(0, 19);
      log(`  First sync for seller ${seller.nickname} — fetching from ${dateFrom}`);
    }

    try {
      const orders = await client.listPaidOrders(seller.seller_id, dateFrom);

      let newCount = 0;
      let latestTs = dateFrom;

      for (const order of orders) {
        // Deduplicate: skip if event already stored
        if (eventExists(seller.seller_id, 'order_paid', order.id)) {
          continue;
        }

        const orderTs = order.date_created;
        insertEvent(seller.seller_id, 'order_paid', orderTs, {
          id: order.id,
          status: order.status,
          total_amount: order.total_amount,
          buyer_nickname: order.buyer?.nickname ?? 'unknown',
          items_count: order.order_items?.length ?? 0,
        });
        newCount++;

        // Track latest timestamp for sync state
        if (orderTs > latestTs) {
          latestTs = orderTs;
        }
      }

      // Update sync state to latest order seen (or now if no orders)
      const newSyncAt = orders.length > 0 ? latestTs : now.toISOString().slice(0, 19);
      upsertSyncState(seller.seller_id, newSyncAt);

      log(`  ${seller.nickname}: ${orders.length} order(s) fetched, ${newCount} new event(s) stored, sync_at=${newSyncAt}`);
    } catch (err) {
      log(`  ERROR syncing orders for ${seller.nickname}: ${err instanceof Error ? err.message : err}`);
      // Continue with other sellers
    }
  }

  log('Collection complete.');
}
