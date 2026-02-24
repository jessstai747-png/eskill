// ============================================================
// digest.ts — Daily digest report sent via Telegram
// ============================================================

import type { Config, HealthStatus, SellerHealthRow } from './types.js';
import { getAllSellers, getAllSellerHealth, countEventsToday, sumAmountToday } from './db.js';
import { sendTelegram } from './notifier.js';

function log(msg: string): void {
  const ts = new Date().toISOString();
  console.log(`[${ts}] [digest] ${msg}`);
}

const STATUS_EMOJI: Record<HealthStatus, string> = {
  active: '✅',
  warning: '⚠️',
  alert: '🟠',
  critical: '🔴',
  new: '🆕',
  watch_247: '👁️',
};

/**
 * Build and send daily digest message via Telegram.
 *
 * Sections:
 * (A) Sales summary per seller (today)
 * (B) Sellers without sales (7/14/30 days)
 * (C) New accounts
 * (D) 24/7 watch list
 */
export async function runDailyDigest(config: Config): Promise<void> {
  log('Building daily digest...');

  const sellers = getAllSellers();
  const healthRows = getAllSellerHealth();
  const healthMap = new Map<number, SellerHealthRow>();
  for (const h of healthRows) {
    healthMap.set(h.seller_id, h);
  }

  const now = new Date();
  const dateStr = now.toLocaleDateString('pt-BR', { timeZone: 'America/Sao_Paulo' });
  const timeStr = now.toLocaleTimeString('pt-BR', { timeZone: 'America/Sao_Paulo' });

  let msg = `📊 <b>Digest Diário — ${dateStr} ${timeStr}</b>\n`;
  msg += `━━━━━━━━━━━━━━━━━━━━━━\n\n`;

  // ---- Section A: Sales summary (today) ----
  msg += `<b>💰 Vendas Hoje</b>\n`;

  let totalOrdersToday = 0;
  let totalAmountToday = 0;
  const sellerSales: Array<{ nickname: string; count: number; amount: number }> = [];

  for (const seller of sellers) {
    const count = countEventsToday(seller.seller_id, 'order_paid');
    const amount = sumAmountToday(seller.seller_id);
    totalOrdersToday += count;
    totalAmountToday += amount;
    sellerSales.push({ nickname: seller.nickname, count, amount });
  }

  if (totalOrdersToday === 0) {
    msg += `  Nenhuma venda registrada hoje.\n`;
  } else {
    for (const s of sellerSales) {
      if (s.count > 0) {
        msg += `  • <b>${escapeHtml(s.nickname)}</b>: ${s.count} pedido(s), R$ ${s.amount.toFixed(2)}\n`;
      }
    }
    msg += `  <b>Total:</b> ${totalOrdersToday} pedido(s), R$ ${totalAmountToday.toFixed(2)}\n`;
  }
  msg += `\n`;

  // ---- Section B: Without sales ----
  const noSales7: string[] = [];
  const noSales14: string[] = [];
  const noSales30: string[] = [];

  for (const seller of sellers) {
    const health = healthMap.get(seller.seller_id);
    if (!health) continue;

    const days = health.days_since_last_sale;
    if (days < 0 || days >= 30) {
      noSales30.push(seller.nickname);
    } else if (days >= 14) {
      noSales14.push(seller.nickname);
    } else if (days >= 7) {
      noSales7.push(seller.nickname);
    }
  }

  msg += `<b>🚫 Sem Vendas</b>\n`;
  if (noSales30.length > 0) {
    msg += `  🔴 30+ dias: ${noSales30.map(n => escapeHtml(n)).join(', ')}\n`;
  }
  if (noSales14.length > 0) {
    msg += `  🟠 14+ dias: ${noSales14.map(n => escapeHtml(n)).join(', ')}\n`;
  }
  if (noSales7.length > 0) {
    msg += `  ⚠️ 7+ dias: ${noSales7.map(n => escapeHtml(n)).join(', ')}\n`;
  }
  if (noSales30.length === 0 && noSales14.length === 0 && noSales7.length === 0) {
    msg += `  Todas as contas venderam nos últimos 7 dias ✅\n`;
  }
  msg += `\n`;

  // ---- Section C: New accounts ----
  const newAccounts: string[] = [];
  for (const seller of sellers) {
    const health = healthMap.get(seller.seller_id);
    if (health && health.is_new) {
      newAccounts.push(seller.nickname);
    }
  }

  msg += `<b>🆕 Contas Novas (< 7 dias)</b>\n`;
  if (newAccounts.length > 0) {
    msg += `  ${newAccounts.map(n => escapeHtml(n)).join(', ')}\n`;
  } else {
    msg += `  Nenhuma conta nova.\n`;
  }
  msg += `\n`;

  // ---- Section D: 24/7 Watch ----
  const watch247: Array<{ nickname: string; score: number; reasons: string[] }> = [];
  for (const seller of sellers) {
    const health = healthMap.get(seller.seller_id);
    if (health && health.movement_247_score >= config.score247Threshold) {
      const reasons = JSON.parse(health.reasons_json || '[]') as string[];
      watch247.push({
        nickname: seller.nickname,
        score: health.movement_247_score,
        reasons,
      });
    }
  }

  msg += `<b>👁️ Watch 24/7 (score >= ${config.score247Threshold})</b>\n`;
  if (watch247.length > 0) {
    for (const w of watch247) {
      msg += `  • <b>${escapeHtml(w.nickname)}</b> — score ${w.score}/100\n`;
      for (const r of w.reasons) {
        msg += `    ↳ ${escapeHtml(r)}\n`;
      }
    }
  } else {
    msg += `  Nenhuma conta com atividade suspeita.\n`;
  }
  msg += `\n`;

  // ---- Section E: Status overview ----
  msg += `<b>📋 Status Geral</b>\n`;
  const statusCounts = new Map<HealthStatus, number>();
  for (const health of healthRows) {
    const s = health.status as HealthStatus;
    statusCounts.set(s, (statusCounts.get(s) || 0) + 1);
  }

  const statusOrder: HealthStatus[] = ['active', 'new', 'warning', 'alert', 'critical', 'watch_247'];
  for (const s of statusOrder) {
    const count = statusCounts.get(s) || 0;
    if (count > 0) {
      msg += `  ${STATUS_EMOJI[s]} ${s}: ${count}\n`;
    }
  }

  msg += `\n━━━━━━━━━━━━━━━━━━━━━━\n`;
  msg += `<i>Account Monitor v1.0 — ${sellers.length} conta(s) monitorada(s)</i>`;

  // Send it
  const sent = await sendTelegram(config, msg);
  if (sent) {
    log('Daily digest sent successfully');
  } else {
    log('ERROR: Failed to send daily digest');
  }
}

function escapeHtml(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}
