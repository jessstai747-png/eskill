// ============================================================
// analyzer.ts — Business logic for seller health analysis
// ============================================================

import type { EventRow, HealthStatus, Movement247Result, AnalysisResult, Config } from './types.js';
import {
  getAllSellers,
  getSellerHealth,
  getEventsForSeller,
  upsertSellerHealth,
} from './db.js';

function log(msg: string): void {
  const ts = new Date().toISOString();
  console.log(`[${ts}] [analyzer] ${msg}`);
}

// ========== Pure computation functions ==========

/**
 * How many full days since last paid order?
 * Returns Infinity if never sold.
 */
export function computeDaysSinceLastSale(lastPaidOrderAt: string | null, now: Date): number {
  if (!lastPaidOrderAt) return Infinity;
  const lastSale = new Date(lastPaidOrderAt);
  const diffMs = now.getTime() - lastSale.getTime();
  return Math.floor(diffMs / (1000 * 60 * 60 * 24));
}

/**
 * Is this a new account (created within thresholdDays)?
 */
export function computeIsNew(createdAt: string, now: Date, thresholdDays = 7): boolean {
  const created = new Date(createdAt);
  const diffMs = now.getTime() - created.getTime();
  const diffDays = diffMs / (1000 * 60 * 60 * 24);
  return diffDays <= thresholdDays;
}

/**
 * Compute 24/7 movement score (0–100) from order events in the last 7 days.
 *
 * Rules:
 * - Activity in 00–05h (nighttime) on >=4 out of 7 days → +40
 * - >=18 distinct active hours on >=3 days → +40
 * - High volume (>=50 orders in 7 days) → +20
 *
 * Returns score + reasons explaining each component.
 */
export function computeMovement247Score(ordersLast7d: EventRow[]): Movement247Result {
  let score = 0;
  const reasons: string[] = [];

  if (ordersLast7d.length === 0) {
    return { score: 0, reasons: ['Sem vendas nos últimos 7 dias'] };
  }

  // Group by day → set of hours
  const dayHours = new Map<string, Set<number>>();

  for (const event of ordersLast7d) {
    const dt = new Date(event.ts);
    const dayKey = dt.toISOString().slice(0, 10); // YYYY-MM-DD
    const hour = dt.getHours();

    if (!dayHours.has(dayKey)) {
      dayHours.set(dayKey, new Set());
    }
    dayHours.get(dayKey)!.add(hour);
  }

  // Rule 1: Night activity (00–05h) on >= 4 days
  let nightActiveDays = 0;
  for (const [, hours] of dayHours) {
    const hasNight = Array.from(hours).some(h => h >= 0 && h <= 5);
    if (hasNight) nightActiveDays++;
  }

  if (nightActiveDays >= 4) {
    score += 40;
    reasons.push(`Atividade noturna (00-05h) em ${nightActiveDays}/7 dias`);
  }

  // Rule 2: >= 18 distinct active hours on >= 3 days
  let highCoverageDays = 0;
  for (const [, hours] of dayHours) {
    if (hours.size >= 18) highCoverageDays++;
  }

  if (highCoverageDays >= 3) {
    score += 40;
    reasons.push(`>=18 horas ativas em ${highCoverageDays} dias`);
  }

  // Rule 3: High volume (>= 50 orders in 7 days)
  if (ordersLast7d.length >= 50) {
    score += 20;
    reasons.push(`Volume alto: ${ordersLast7d.length} vendas em 7 dias`);
  }

  return { score: Math.min(score, 100), reasons };
}

/**
 * Derive overall status from analysis inputs.
 */
export function deriveStatus(
  daysSinceLastSale: number,
  isNew: boolean,
  score247: number,
  config: Config,
): HealthStatus {
  // 24/7 watch takes precedence (suspicious behavior)
  if (score247 >= config.score247Threshold) {
    return 'watch_247';
  }

  // New account with no sales yet
  if (isNew && daysSinceLastSale === Infinity) {
    return 'new';
  }

  // Severity levels based on days without sales
  if (daysSinceLastSale >= config.daysCritical) {
    return 'critical';
  }
  if (daysSinceLastSale >= config.daysAlert) {
    return 'alert';
  }
  if (daysSinceLastSale >= config.daysWarning) {
    return 'warning';
  }

  return 'active';
}

// ========== Main analysis job ==========

/**
 * Run analysis for all sellers. Returns array of results for notification purposes.
 */
export function runAnalysis(config: Config): AnalysisResult[] {
  const now = new Date();
  const sellers = getAllSellers();
  const results: AnalysisResult[] = [];

  log(`Analyzing ${sellers.length} seller(s)...`);

  for (const seller of sellers) {
    // Get the latest paid order timestamp from events
    const sevenDaysAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000).toISOString();
    const thirtyDaysAgo = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000).toISOString();

    // Get all paid order events for 24/7 analysis (last 7 days)
    const recentEvents = getEventsForSeller(seller.seller_id, 'order_paid', sevenDaysAgo);

    // Get the most recent event ever (for last sale date)
    const allRecentEvents = getEventsForSeller(seller.seller_id, 'order_paid', thirtyDaysAgo);
    const latestEvent = allRecentEvents.length > 0
      ? allRecentEvents[allRecentEvents.length - 1]
      : null;

    // Also check seller_health for previous data
    const prevHealth = getSellerHealth(seller.seller_id);
    const lastPaidOrderAt = latestEvent?.ts ?? prevHealth?.last_paid_order_at ?? null;

    // Compute metrics
    const daysSinceLastSale = computeDaysSinceLastSale(lastPaidOrderAt, now);
    const isNew = computeIsNew(seller.created_at, now, config.newAccountDays);
    const movement247 = computeMovement247Score(recentEvents);
    const status = deriveStatus(daysSinceLastSale, isNew, movement247.score, config);

    // Build reasons list
    const reasons: string[] = [];
    if (daysSinceLastSale === Infinity) {
      reasons.push('Nenhuma venda registrada');
    } else if (daysSinceLastSale >= config.daysWarning) {
      reasons.push(`${daysSinceLastSale} dias sem venda`);
    }
    if (isNew) {
      reasons.push('Conta nova (< 7 dias)');
    }
    if (movement247.reasons.length > 0) {
      reasons.push(...movement247.reasons);
    }

    const result: AnalysisResult = {
      sellerId: seller.seller_id,
      nickname: seller.nickname,
      lastPaidOrderAt: lastPaidOrderAt,
      daysSinceLastSale: daysSinceLastSale === Infinity ? -1 : daysSinceLastSale,
      isNew,
      movement247,
      status,
      reasons,
    };

    // Persist health
    upsertSellerHealth(seller.seller_id, {
      lastPaidOrderAt,
      daysSinceLastSale: daysSinceLastSale === Infinity ? -1 : daysSinceLastSale,
      isNew,
      movement247Score: movement247.score,
      status,
      reasons,
    });

    results.push(result);

    const statusIcon = STATUS_ICONS[status] || '❓';
    log(`  ${statusIcon} ${seller.nickname}: status=${status}, days_since_sale=${daysSinceLastSale === Infinity ? '∞' : daysSinceLastSale}, score_247=${movement247.score}`);
  }

  log(`Analysis complete. Results: ${results.map(r => `${r.nickname}=${r.status}`).join(', ')}`);
  return results;
}

const STATUS_ICONS: Record<HealthStatus, string> = {
  active: '✅',
  warning: '⚠️',
  alert: '🟠',
  critical: '🔴',
  new: '🆕',
  watch_247: '👁️',
};
