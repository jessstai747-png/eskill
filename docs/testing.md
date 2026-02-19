# E2E Testing Guide

## Overview

This project uses Playwright for end-to-end testing with a mock render harness system. The harness simulates video rendering operations deterministically without requiring FFmpeg, Redis, or Azure dependencies.

## Prerequisites

- Node.js 18+ and npm
- PHP 8.0+
- Playwright browsers installed

## Quick Start

### 1. Install Dependencies

```bash
npm install
npx playwright install
```

### 2. Run Tests

Run all E2E tests with automatic server startup:

```bash
npm run test:e2e
```

Run tests in headed mode (with browser UI):

```bash
npm run test:e2e:headed
```

Run tests in UI mode (interactive):

```bash
npm run test:e2e:ui
```

Debug tests:

```bash
npm run test:e2e:debug
```

### 3. Manual Testing

If you need to run the server and tests separately:

```bash
# Terminal 1: Start the server
php -S localhost:3000 -t public

# Terminal 2: Run Playwright tests
npx playwright test
```

## Environment Configuration

### Test Mode Variables

The following environment variables control test behavior:

#### `RENDER_HARNESS` (default: `false`)

When set to `true`, the render service uses a mock harness that:
- Returns deterministic results instantly
- Bypasses FFmpeg, Azure, and Redis
- Generates minimal valid MP4 files
- Suitable for testing and CI/CD environments

**Usage:**
```bash
RENDER_HARNESS=true
```

#### `E2E_TEST_MODE` (default: `false`)

When set to `true`, enables test-only authentication bypass:
- Accepts fixed token `Bearer test-token`
- Bypasses normal API authentication
- **MUST be disabled in production**

**Usage:**
```bash
E2E_TEST_MODE=true
```

### Environment Files

- **`.env.test`**: Test environment configuration (harness and test mode enabled)
- **`.env`**: Development/production configuration (harness and test mode disabled)
- **`.env.example`**: Template with all available options

## Test Authentication

### Test Token

When `E2E_TEST_MODE=true`, you can use the fixed test token for API requests:

```bash
curl -X POST http://localhost:3000/api/render \
  -H "Authorization: Bearer test-token" \
  -H "Content-Type: application/json" \
  -d '{"audio": "test.mp3"}'
```

### Security Warning

⚠️ **NEVER enable `E2E_TEST_MODE` in production environments!**

The test mode bypasses all authentication and should only be used in:
- Local development
- CI/CD pipelines
- Automated testing environments

## Test Structure

### Test Files

- **`tests/e2e/health.spec.ts`**: Basic health check tests
- **`tests/e2e/render.spec.ts`**: Render API tests with harness

### Test Fixtures

- **`tests/fixtures/test-data.json`**: Sample payloads for API testing

## Switching Between Mock and Real Render

### Using Mock Harness (Default for Tests)

```bash
# In .env.test
RENDER_HARNESS=true
```

All tests will use the mock harness and complete instantly.

### Using Real Render Service

```bash
# In .env
RENDER_HARNESS=false
```

Tests will attempt to use the real render service (requires FFmpeg, Azure, Redis).

## Troubleshooting

### Tests Fail with 401 Unauthorized

**Cause:** `E2E_TEST_MODE` is not enabled or server is not using `.env.test`

**Solution:**
1. Verify `.env.test` has `E2E_TEST_MODE=true`
2. Ensure Playwright's `webServer` config loads the correct environment
3. Check that the test token is exactly `test-token`

### Tests Fail with 501 Not Implemented

**Cause:** `RENDER_HARNESS` is not enabled

**Solution:**
1. Verify `.env.test` has `RENDER_HARNESS=true`
2. Restart the server to pick up environment changes

### Server Doesn't Start

**Cause:** Port 3000 is already in use

**Solution:**
1. Kill the process using port 3000: `lsof -ti:3000 | xargs kill -9`
2. Or change the port in `playwright.config.ts`

### Playwright Browsers Not Installed

**Cause:** Playwright browsers are missing

**Solution:**
```bash
npx playwright install
```

## CI/CD Integration

Example GitHub Actions workflow:

```yaml
name: E2E Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: '18'
      - name: Install dependencies
        run: npm install
      - name: Install Playwright browsers
        run: npx playwright install --with-deps
      - name: Run E2E tests
        run: npm run test:e2e
```

## Additional Resources

- [Playwright Documentation](https://playwright.dev)
- [Playwright Test API](https://playwright.dev/docs/api/class-test)
- [Playwright Configuration](https://playwright.dev/docs/test-configuration)
