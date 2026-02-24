// ============================================================
// index.ts — CLI entry point & cron scheduler
// ============================================================

import cron from 'node-cron';
import { loadConfig } from './config.js';
import { initDb, closeDb } from './db.js';
import { runCollector } from './collector.js';
import { runAnalysis } from './analyzer.js';
import { processAlerts } from './notifier.js';
import { runDailyDigest } from './digest.js';
import type { Config } from './types.js';

function log(msg: string): void {
  const ts = new Date().toISOString();
  console.log(`[${ts}] [main] ${msg}`);
}

function printUsage(): void {
  console.log(`
Account Monitor — CLI

Usage:
  npx tsx src/index.ts <command>

Commands:
  collect    Fetch sellers + orders from eskill API (delta sync)
  analyze    Run health analysis on all sellers + send alerts
  digest     Send daily digest via Telegram
  run        Run collect → analyze → alerts (full pipeline)
  cron       Start cron scheduler (collect */10, analyze hourly, digest 09:00)
  health     Check eskill API connectivity

Environment:
  Copy .env.example to .env and fill in your values.
`);
}

async function cmdCollect(config: Config): Promise<void> {
  log('=== COLLECT ===');
  await runCollector(config);
  log('=== COLLECT DONE ===');
}

async function cmdAnalyze(config: Config): Promise<void> {
  log('=== ANALYZE ===');
  const results = runAnalysis(config);
  const alertCount = await processAlerts(config, results);
  log(`=== ANALYZE DONE (${results.length} sellers, ${alertCount} alerts) ===`);
}

async function cmdDigest(config: Config): Promise<void> {
  log('=== DIGEST ===');
  await runDailyDigest(config);
  log('=== DIGEST DONE ===');
}

async function cmdRun(config: Config): Promise<void> {
  log('=== FULL PIPELINE ===');
  await cmdCollect(config);
  await cmdAnalyze(config);
  log('=== FULL PIPELINE DONE ===');
}

async function cmdHealth(config: Config): Promise<void> {
  log('=== HEALTH CHECK ===');
  const { EskillClient } = await import('./eskillClient.js');
  const client = new EskillClient(config);
  const health = await client.checkHealth();
  console.log(JSON.stringify(health, null, 2));
  log('=== HEALTH CHECK DONE ===');
}

function cmdCron(config: Config): void {
  log('=== CRON SCHEDULER STARTED ===');
  log('Schedules:');
  log('  collect:  */10 * * * *  (every 10 minutes)');
  log('  analyze:  0 * * * *     (hourly)');
  log('  digest:   0 9 * * *     (daily at 09:00 America/Sao_Paulo)');
  log('Press Ctrl+C to stop.\n');

  // Collect every 10 minutes
  cron.schedule('*/10 * * * *', async () => {
    try {
      await cmdCollect(config);
    } catch (err) {
      log(`CRON collect error: ${err instanceof Error ? err.message : err}`);
    }
  }, { timezone: 'America/Sao_Paulo' });

  // Analyze every hour
  cron.schedule('0 * * * *', async () => {
    try {
      await cmdAnalyze(config);
    } catch (err) {
      log(`CRON analyze error: ${err instanceof Error ? err.message : err}`);
    }
  }, { timezone: 'America/Sao_Paulo' });

  // Daily digest at 09:00
  cron.schedule('0 9 * * *', async () => {
    try {
      await cmdDigest(config);
    } catch (err) {
      log(`CRON digest error: ${err instanceof Error ? err.message : err}`);
    }
  }, { timezone: 'America/Sao_Paulo' });

  // Run initial collect + analyze on startup
  (async () => {
    try {
      log('Running initial collect + analyze...');
      await cmdRun(config);
    } catch (err) {
      log(`Initial run error: ${err instanceof Error ? err.message : err}`);
    }
  })();
}

// ========== Main ==========

async function main(): Promise<void> {
  const command = process.argv[2];

  if (!command || command === '--help' || command === '-h') {
    printUsage();
    process.exit(0);
  }

  // Load config
  let config: Config;
  try {
    config = loadConfig();
  } catch (err) {
    console.error(`Configuration error: ${err instanceof Error ? err.message : err}`);
    process.exit(1);
  }

  // Init database
  initDb(config.dbPath);

  // Handle graceful shutdown
  const shutdown = (): void => {
    log('Shutting down...');
    closeDb();
    process.exit(0);
  };
  process.on('SIGINT', shutdown);
  process.on('SIGTERM', shutdown);

  try {
    switch (command) {
      case 'collect':
        await cmdCollect(config);
        break;
      case 'analyze':
        await cmdAnalyze(config);
        break;
      case 'digest':
        await cmdDigest(config);
        break;
      case 'run':
        await cmdRun(config);
        break;
      case 'health':
        await cmdHealth(config);
        break;
      case 'cron':
        cmdCron(config);
        return; // Don't close DB — cron keeps running
      default:
        console.error(`Unknown command: ${command}`);
        printUsage();
        process.exit(1);
    }
  } catch (err) {
    log(`FATAL: ${err instanceof Error ? err.message : err}`);
    if (err instanceof Error && err.stack) {
      console.error(err.stack);
    }
    process.exit(1);
  } finally {
    if (command !== 'cron') {
      closeDb();
    }
  }
}

main();
