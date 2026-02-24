// ============================================================
// notifier.ts — Telegram notification service
// ============================================================

import type { Config, SellerHealthRow, AnalysisResult, HealthStatus } from './types.js';
import { getSellerHealth, updateLastAlertSent, getSellerById } from './db.js';

function log(msg: string): void {
  const ts = new Date().toISOString();
  console.log(`[${ts}] [notifier] ${msg}`);
}

const STATUS_LABELS: Record<HealthStatus, string> = {
  active: '✅ Ativa',
  warning: '⚠️ Alerta (7d)',
  alert: '🟠 Alerta (14d)',
  critical: '🔴 Crítico (30d+)',
  new: '🆕 Nova',
  watch_247: '👁️ Suspeita 24/7',
};

/**
 * Send a message via Telegram Bot API.
 * Uses parse_mode=HTML for formatting.
 */
export async function sendTelegram(config: Config, text: string): Promise<boolean> {
  const url = `https://api.telegram.org/bot${config.telegramBotToken}/sendMessage`;

  try {
    const resp = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        chat_id: config.telegramChatId,
        text,
        parse_mode: 'HTML',
        disable_web_page_preview: true,
      }),
      signal: AbortSignal.timeout(15_000),
    });

    if (!resp.ok) {
      const body = await resp.text();
      log(`Telegram API error: HTTP ${resp.status} — ${body}`);
      return false;
    }

    log('Telegram message sent successfully');
    return true;
  } catch (err) {
    log(`Telegram send failed: ${err instanceof Error ? err.message : err}`);
    return false;
  }
}

/**
 * Check if an alert should be sent for this seller.
 * Triggers when:
 * - Status changed from previous
 * - Status crossed a threshold (e.g. active → warning)
 * - Score 24/7 crossed threshold
 */
export async function sendAlertIfNeeded(
  config: Config,
  result: AnalysisResult,
): Promise<boolean> {
  const prevHealth = getSellerHealth(result.sellerId);
  const prevStatus = prevHealth?.status as HealthStatus | undefined;

  // Only alert on status change or first time
  if (prevStatus === result.status && prevHealth) {
    return false;
  }

  // Don't spam: skip if transitioning active → active
  if (prevStatus === 'active' && result.status === 'active') {
    return false;
  }

  const seller = getSellerById(result.sellerId);
  const nickname = seller?.nickname ?? result.nickname;

  const label = STATUS_LABELS[result.status] ?? result.status;
  const prevLabel = prevStatus ? (STATUS_LABELS[prevStatus] ?? prevStatus) : '(primeiro check)';

  let msg = `🔔 <b>Account Monitor — Mudança de Status</b>\n\n`;
  msg += `<b>Seller:</b> ${escapeHtml(nickname)}\n`;
  msg += `<b>Status:</b> ${label}\n`;
  msg += `<b>Anterior:</b> ${prevLabel}\n`;

  if (result.daysSinceLastSale >= 0) {
    msg += `<b>Dias sem venda:</b> ${result.daysSinceLastSale}\n`;
  } else {
    msg += `<b>Dias sem venda:</b> ∞ (nunca vendeu)\n`;
  }

  if (result.lastPaidOrderAt) {
    msg += `<b>Última venda:</b> ${formatDate(result.lastPaidOrderAt)}\n`;
  }

  if (result.movement247.score > 0) {
    msg += `<b>Score 24/7:</b> ${result.movement247.score}/100\n`;
  }

  if (result.reasons.length > 0) {
    msg += `\n<b>Motivos:</b>\n`;
    for (const reason of result.reasons) {
      msg += `  • ${escapeHtml(reason)}\n`;
    }
  }

  const sent = await sendTelegram(config, msg);
  if (sent) {
    updateLastAlertSent(result.sellerId);
  }
  return sent;
}

/**
 * Process analysis results and send alerts for status changes.
 */
export async function processAlerts(config: Config, results: AnalysisResult[]): Promise<number> {
  let alertCount = 0;

  for (const result of results) {
    if (result.status === 'active') continue; // Don't alert for healthy sellers (unless status changed)

    const prevHealth = getSellerHealth(result.sellerId);
    const prevStatus = prevHealth?.status as HealthStatus | undefined;

    // Alert if status changed or first analysis
    if (!prevStatus || prevStatus !== result.status) {
      const sent = await sendAlertIfNeeded(config, result);
      if (sent) alertCount++;
    }
  }

  if (alertCount > 0) {
    log(`Sent ${alertCount} alert(s)`);
  } else {
    log('No alerts needed');
  }

  return alertCount;
}

// ========== Helpers ==========

function escapeHtml(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function formatDate(iso: string): string {
  try {
    const d = new Date(iso);
    return d.toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' });
  } catch {
    return iso;
  }
}
