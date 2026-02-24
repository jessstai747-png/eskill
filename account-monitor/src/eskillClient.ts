// ============================================================
// eskillClient.ts — HTTP wrapper for eskill OpenClaw API
// ============================================================

import type { Config, SellersResponse, OrdersResponse, HealthResponse, ApiOrder } from './types.js';

const MAX_RETRIES = 3;
const INITIAL_BACKOFF_MS = 1000;

function log(msg: string): void {
  const ts = new Date().toISOString();
  console.log(`[${ts}] [eskillClient] ${msg}`);
}

async function fetchWithRetry(url: string, headers: Record<string, string>, retries = MAX_RETRIES): Promise<Response> {
  let lastError: Error | null = null;

  for (let attempt = 1; attempt <= retries; attempt++) {
    try {
      const resp = await fetch(url, { headers, signal: AbortSignal.timeout(30_000) });

      // Don't retry client errors (4xx) except 429
      if (resp.status >= 400 && resp.status < 500 && resp.status !== 429) {
        return resp;
      }

      // Retry on 429 and 5xx
      if (resp.status === 429 || resp.status >= 500) {
        const backoff = INITIAL_BACKOFF_MS * Math.pow(2, attempt - 1);
        log(`HTTP ${resp.status} on attempt ${attempt}/${retries}. Retrying in ${backoff}ms...`);
        await sleep(backoff);
        lastError = new Error(`HTTP ${resp.status}: ${resp.statusText}`);
        continue;
      }

      return resp;
    } catch (err) {
      lastError = err instanceof Error ? err : new Error(String(err));
      if (attempt < retries) {
        const backoff = INITIAL_BACKOFF_MS * Math.pow(2, attempt - 1);
        log(`Network error on attempt ${attempt}/${retries}: ${lastError.message}. Retrying in ${backoff}ms...`);
        await sleep(backoff);
      }
    }
  }

  throw new Error(`All ${retries} attempts failed. Last error: ${lastError?.message}`);
}

function sleep(ms: number): Promise<void> {
  return new Promise(resolve => setTimeout(resolve, ms));
}

export class EskillClient {
  private baseUrl: string;
  private headers: Record<string, string>;

  constructor(config: Config) {
    this.baseUrl = config.eskillBaseUrl;
    this.headers = {
      'Authorization': `Bearer ${config.eskillToken}`,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
  }

  /** Health check — verifies API connectivity */
  async checkHealth(): Promise<HealthResponse> {
    const resp = await fetchWithRetry(`${this.baseUrl}/health`, this.headers);
    if (!resp.ok) {
      throw new Error(`Health check failed: HTTP ${resp.status}`);
    }
    return resp.json() as Promise<HealthResponse>;
  }

  /** List all sellers for the authenticated user */
  async listSellers(): Promise<SellersResponse> {
    log('Fetching sellers...');
    const resp = await fetchWithRetry(`${this.baseUrl}/sellers`, this.headers);
    if (!resp.ok) {
      const body = await resp.text();
      throw new Error(`listSellers failed: HTTP ${resp.status} — ${body}`);
    }
    const data = await resp.json() as SellersResponse;
    log(`Found ${data.sellers.length} seller(s)`);
    return data;
  }

  /**
   * List paid orders for a seller, handling pagination.
   * Returns ALL matching orders (follows has_more automatically).
   */
  async listPaidOrders(
    sellerId: number,
    dateFrom: string,
    perPage = 200,
  ): Promise<ApiOrder[]> {
    const allOrders: ApiOrder[] = [];
    let page = 1;
    let hasMore = true;

    while (hasMore) {
      const params = new URLSearchParams({
        status: 'paid',
        date_from: dateFrom,
        sort: 'date_created',
        order: 'ASC',
        page: String(page),
        per_page: String(perPage),
      });

      const url = `${this.baseUrl}/sellers/${sellerId}/orders?${params}`;
      log(`Fetching orders for seller ${sellerId} — page ${page}`);

      const resp = await fetchWithRetry(url, this.headers);
      if (!resp.ok) {
        const body = await resp.text();
        throw new Error(`listPaidOrders(seller=${sellerId}, page=${page}) failed: HTTP ${resp.status} — ${body}`);
      }

      const data = await resp.json() as OrdersResponse;
      allOrders.push(...data.orders);

      hasMore = data.has_more;
      page++;

      // Safety: prevent infinite loops
      if (page > 500) {
        log(`WARNING: Reached page 500 for seller ${sellerId}, stopping pagination`);
        break;
      }
    }

    log(`Fetched ${allOrders.length} paid order(s) for seller ${sellerId} since ${dateFrom}`);
    return allOrders;
  }

  /**
   * List paid orders for a single page (for digest use).
   */
  async listPaidOrdersPage(
    sellerId: number,
    dateFrom: string,
    page = 1,
    perPage = 200,
  ): Promise<OrdersResponse> {
    const params = new URLSearchParams({
      status: 'paid',
      date_from: dateFrom,
      sort: 'date_created',
      order: 'DESC',
      page: String(page),
      per_page: String(perPage),
    });

    const url = `${this.baseUrl}/sellers/${sellerId}/orders?${params}`;
    const resp = await fetchWithRetry(url, this.headers);
    if (!resp.ok) {
      const body = await resp.text();
      throw new Error(`listPaidOrdersPage failed: HTTP ${resp.status} — ${body}`);
    }
    return resp.json() as Promise<OrdersResponse>;
  }
}
