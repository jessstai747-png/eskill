const fs = require('fs');
const path = require('path');
const { chromium } = require('@playwright/test');

const baseURL = 'https://eskill.com.br';
const routes = [
  '/dashboard',
  '/dashboard/accounts',
  '/dashboard/analytics',
  '/dashboard/account-health',
  '/dashboard/items',
  '/dashboard/orders',
  '/dashboard/questions',
  '/dashboard/messages',
  '/dashboard/claims',
  '/dashboard/seo-killer',
  '/dashboard/financials',
  '/dashboard/pricing',
];

const email = process.env.SMOKE_EMAIL || '';
const password = process.env.SMOKE_PASSWORD || '';

if (!email || !password) {
  console.error('Missing credentials: set SMOKE_EMAIL and SMOKE_PASSWORD');
  process.exit(1);
}

function slug(route) {
  return route === '/dashboard'
    ? 'dashboard'
    : route.replace(/^\//, '').replace(/\//g, '_').replace(/[^a-zA-Z0-9_\-]/g, '_');
}

function classifyFailure(result) {
  if (result.docStatus !== null && Number(result.docStatus) >= 500) return 'P1';
  if (result.uiFailure.length > 0) return 'P1';
  if (result.navError) return 'P1';
  if (result.criticalConsoleErrors.length > 0) return 'P2';
  if (result.failedRequests.length > 0) return 'P2';
  return null;
}

async function shortSettle(page) {
  await page.waitForLoadState('domcontentloaded', { timeout: 20000 }).catch(() => {});
  await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
  await page.waitForTimeout(2500);
}

async function login(page, outDir) {
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded', timeout: 45000 });

  const emailCandidates = page.locator(
    'input[name=\"email\"], input[type=\"email\"], #email, input[autocomplete=\"username\"], input[type=\"text\"]'
  );
  const passCandidates = page.locator(
    'input[name=\"password\"], input[type=\"password\"], #password, input[autocomplete=\"current-password\"]'
  );

  let emailInput = emailCandidates.first();
  let passInput = passCandidates.first();

  if ((await emailCandidates.count()) === 0 || (await passCandidates.count()) === 0) {
    const formInputs = page.locator('form input:not([type=\"hidden\"]):not([type=\"checkbox\"])');
    if ((await formInputs.count()) >= 2) {
      emailInput = formInputs.nth(0);
      passInput = formInputs.nth(1);
    }
  }

  await emailInput.waitFor({ state: 'visible', timeout: 15000 });
  await passInput.waitFor({ state: 'visible', timeout: 15000 });

  await emailInput.fill('');
  await emailInput.fill(email);
  await passInput.fill('');
  await passInput.fill(password);

  const submit = page
    .locator('button[type="submit"], input[type="submit"], button:has-text("Entrar"), button:has-text("Login")')
    .first();

  const navPromise = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 25000 }).catch(() => null);
  await submit.click({ timeout: 10000 });
  await navPromise;
  await shortSettle(page);

  const currentUrl = page.url();
  if (!currentUrl.includes('/dashboard')) {
    const shot = path.join(outDir, 'login_failed.png');
    await page.screenshot({ path: shot, fullPage: true }).catch(() => {});
    throw new Error(`Login failed, current URL: ${currentUrl}`);
  }

  const shot = path.join(outDir, '00_after_login.png');
  await page.screenshot({ path: shot, fullPage: true }).catch(() => {});
}

async function visitRoute(page, route, index, outDir) {
  const consoleErrors = [];
  const failedRequests = [];
  let docStatus = null;
  let navError = null;
  let method = 'click';

  const consoleHandler = (msg) => {
    if (msg.type() === 'error') {
      consoleErrors.push(msg.text());
    }
  };

  const responseHandler = (resp) => {
    const req = resp.request();
    const type = req.resourceType();
    if ((type === 'xhr' || type === 'fetch') && resp.status() >= 500) {
      failedRequests.push({ url: resp.url(), status: resp.status(), type });
    }
  };

  const requestFailedHandler = (req) => {
    const type = req.resourceType();
    const failure = req.failure() ? req.failure().errorText : 'unknown';
    if (type === 'xhr' || type === 'fetch') {
      // Ignore aborted XHR/fetch during route transitions to avoid false negatives.
      if (failure.includes('ERR_ABORTED')) return;
      failedRequests.push({
        url: req.url(),
        status: 'REQUEST_FAILED',
        type,
        error: failure,
      });
    }
  };

  page.on('console', consoleHandler);
  page.on('response', responseHandler);
  page.on('requestfailed', requestFailedHandler);

  try {
    const link = page.locator(`a[href="${route}"]`).first();
    if ((await link.count()) > 0) {
      const docResponsePromise = page
        .waitForResponse((resp) => resp.request().resourceType() === 'document', { timeout: 18000 })
        .catch(() => null);

      await link.click({ timeout: 7000 });
      const docResp = await docResponsePromise;
      if (docResp) docStatus = docResp.status();
      await shortSettle(page);

      if (!page.url().includes(route)) {
        method = 'goto_fallback';
      }
    } else {
      method = 'goto_fallback';
    }

    if (method !== 'click') {
      const resp = await page.goto(`${baseURL}${route}`, { waitUntil: 'domcontentloaded', timeout: 30000 });
      if (resp) docStatus = resp.status();
      await shortSettle(page);
    }
  } catch (error) {
    navError = error.message;
    try {
      method = 'goto_recovery';
      const resp = await page.goto(`${baseURL}${route}`, { waitUntil: 'domcontentloaded', timeout: 30000 });
      if (resp) docStatus = resp.status();
      await shortSettle(page);
    } catch (recoverError) {
      navError = `${navError} | recovery: ${recoverError.message}`;
    }
  }

  const uiSnapshot = await page.evaluate(() => {
    const main = document.querySelector('.page-content, .main-content, main, #main-content');
    const rect = main ? main.getBoundingClientRect() : null;
    const text = (document.body && document.body.innerText ? document.body.innerText : '').slice(0, 8000);
    const patterns = [
      /internal server error/i,
      /fatal error/i,
      /uncaught/i,
      /falha ao carregar/i,
      /exception/i,
      /cannot read properties of/i,
      /undefined is not/i,
    ];

    const textHits = patterns.filter((pattern) => pattern.test(text)).map((p) => p.toString());

    return {
      url: window.location.pathname + window.location.search + window.location.hash,
      title: document.title || '',
      mainVisible: !!main && !!rect && rect.height > 120,
      textHits,
    };
  });

  const criticalConsoleErrors = consoleErrors.filter((line) => {
    const lower = line.toLowerCase();
    if (lower.includes('favicon')) return false;
    if (lower.includes('third-party')) return false;
    return (
      lower.includes('uncaught') ||
      lower.includes('typeerror') ||
      lower.includes('referenceerror') ||
      lower.includes('syntaxerror') ||
      lower.includes('csp')
    );
  });

  const uiFailure = [];
  if (!uiSnapshot.mainVisible) {
    uiFailure.push('Main content not visible');
  }
  if (uiSnapshot.textHits.length > 0) {
    uiFailure.push(`Error text pattern found: ${uiSnapshot.textHits.join(', ')}`);
  }

  const shotPath = path.join(outDir, `${String(index + 1).padStart(2, '0')}_${slug(route)}.png`);
  await page.screenshot({ path: shotPath, fullPage: true }).catch(() => {});
  await page.waitForTimeout(3500);

  page.off('console', consoleHandler);
  page.off('response', responseHandler);
  page.off('requestfailed', requestFailedHandler);

  return {
    route,
    currentUrl: uiSnapshot.url,
    method,
    docStatus,
    navError,
    consoleErrorCount: consoleErrors.length,
    criticalConsoleErrors,
    failedRequests,
    uiFailure,
    title: uiSnapshot.title,
    screenshot: shotPath,
    result:
      navError ||
      (docStatus !== null && Number(docStatus) >= 500) ||
      uiFailure.length > 0 ||
      criticalConsoleErrors.length > 0
        ? 'FAIL'
        : 'PASS',
  };
}

(async () => {
  const ts = new Date().toISOString().replace(/[:.]/g, '-');
  const outDir = path.join('/tmp', `ui-smoke-production-${ts}`);
  fs.mkdirSync(outDir, { recursive: true });

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1600, height: 900 } });
  const page = await context.newPage();

  const results = [];
  let fatal = null;

  try {
    await login(page, outDir);

    for (let i = 0; i < routes.length; i++) {
      const route = routes[i];
      console.log(`Visiting ${route} ...`);
      const result = await visitRoute(page, route, i, outDir);
      results.push(result);
    }
  } catch (error) {
    fatal = error.message;
  } finally {
    await context.close();
    await browser.close();
  }

  const failures = results.filter((r) => r.result === 'FAIL');
  const findings = failures.map((r) => ({
    route: r.route,
    severity: classifyFailure(r),
    reason: [
      r.navError ? `navigation: ${r.navError}` : null,
      r.docStatus !== null && Number(r.docStatus) >= 500 ? `document_status=${r.docStatus}` : null,
      r.uiFailure.length ? `ui=${r.uiFailure.join(' | ')}` : null,
      r.criticalConsoleErrors.length ? `console=${r.criticalConsoleErrors.slice(0, 3).join(' || ')}` : null,
      r.failedRequests.length ? `failed_requests=${r.failedRequests.slice(0, 3).map((x) => `${x.status} ${x.url}`).join(' || ')}` : null,
    ]
      .filter(Boolean)
      .join(' ; '),
  }));

  const report = {
    baseURL,
    executedAt: new Date().toISOString(),
    outDir,
    fatal,
    summary: {
      total: routes.length,
      passed: results.filter((r) => r.result === 'PASS').length,
      failed: failures.length,
    },
    results,
    findings,
  };

  const jsonPath = path.join(outDir, 'report.json');
  fs.writeFileSync(jsonPath, JSON.stringify(report, null, 2), 'utf8');

  const lines = [];
  lines.push('# UI Smoke Report (Production)');
  lines.push('');
  lines.push(`- Base URL: ${baseURL}`);
  lines.push(`- Executed At: ${report.executedAt}`);
  lines.push(`- Output Dir: ${outDir}`);
  lines.push(`- Summary: PASS=${report.summary.passed} FAIL=${report.summary.failed} TOTAL=${report.summary.total}`);
  if (fatal) lines.push(`- Fatal: ${fatal}`);
  lines.push('');
  lines.push('| Route | Doc Status | Console Errors | Failed XHR/Fetch | Result | Screenshot |');
  lines.push('|---|---:|---:|---:|---|---|');
  for (const r of results) {
    lines.push(`| ${r.route} | ${r.docStatus === null ? 'n/a' : r.docStatus} | ${r.criticalConsoleErrors.length} | ${r.failedRequests.length} | ${r.result} | ${path.basename(r.screenshot)} |`);
  }
  lines.push('');
  lines.push('## Findings');
  if (findings.length === 0) {
    lines.push('- None');
  } else {
    for (const f of findings) {
      lines.push(`- [${f.severity}] ${f.route}: ${f.reason}`);
    }
  }

  const mdPath = path.join(outDir, 'report.md');
  fs.writeFileSync(mdPath, lines.join('\n'), 'utf8');

  console.log(`REPORT_DIR=${outDir}`);
  console.log(`REPORT_JSON=${jsonPath}`);
  console.log(`REPORT_MD=${mdPath}`);
  console.log(`SUMMARY_PASS=${report.summary.passed}`);
  console.log(`SUMMARY_FAIL=${report.summary.failed}`);

  if (fatal) process.exit(2);
})();
