// ============================================================
// config.ts — Load and validate environment configuration
// ============================================================

import { config as dotenvConfig } from 'dotenv';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';
import type { Config } from './types.js';

const __dirname = dirname(fileURLToPath(import.meta.url));
dotenvConfig({ path: resolve(__dirname, '..', '.env') });

function requireEnv(key: string): string {
  const val = process.env[key];
  if (!val || val.trim() === '') {
    throw new Error(`Missing required env var: ${key}. Check .env file.`);
  }
  return val.trim();
}

function optionalEnvInt(key: string, defaultVal: number): number {
  const val = process.env[key];
  if (!val) return defaultVal;
  const parsed = parseInt(val, 10);
  if (isNaN(parsed)) return defaultVal;
  return parsed;
}

export function loadConfig(): Config {
  return {
    eskillBaseUrl: requireEnv('ESKILL_BASE_URL').replace(/\/+$/, ''),
    eskillToken: requireEnv('ESKILL_TOKEN'),
    telegramBotToken: requireEnv('TELEGRAM_BOT_TOKEN'),
    telegramChatId: requireEnv('TELEGRAM_CHAT_ID'),
    dbPath: process.env['DB_PATH'] || './data/monitor.db',
    daysWarning: optionalEnvInt('DAYS_WARNING', 7),
    daysAlert: optionalEnvInt('DAYS_ALERT', 14),
    daysCritical: optionalEnvInt('DAYS_CRITICAL', 30),
    newAccountDays: optionalEnvInt('NEW_ACCOUNT_DAYS', 7),
    score247Threshold: optionalEnvInt('SCORE_247_THRESHOLD', 80),
  };
}
