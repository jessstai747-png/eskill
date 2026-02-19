"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _test = require("@playwright/test");
/**
 * Playwright E2E Test Configuration
 * See https://playwright.dev/docs/test-configuration
 */
var _default = exports.default = (0, _test.defineConfig)({
  testDir: './tests/e2e',
  /* Run tests in files in parallel */
  fullyParallel: true,
  /* Fail the build on CI if you accidentally left test.only in the source code */
  forbidOnly: !!process.env.CI,
  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,
  /* Opt out of parallel tests on CI */
  workers: process.env.CI ? 1 : undefined,
  /* Reporter to use */
  reporter: 'html',
  /* Shared settings for all the projects below */
  use: {
    /* Base URL to use in actions like `await page.goto('/')` */
    baseURL: 'http://localhost:3000',
    /* Collect trace when retrying the failed test */
    trace: 'on-first-retry',
    /* Screenshot on failure */
    screenshot: 'only-on-failure',
    /* Video on failure */
    video: 'retain-on-failure'
  },
  /* Configure projects for major browsers */
  projects: [{
    name: 'chromium',
    use: {
      ..._test.devices['Desktop Chrome']
    }
  }],
  /* Run your local dev server before starting the tests */
  webServer: {
    command: 'APP_ENV=testing OPENAI_API_KEY=dummy OPENAI_API_BASE_URL=http://127.0.0.1:3100 php -S localhost:3000 -t public',
    url: 'http://localhost:3000',
    reuseExistingServer: !process.env.CI,
    timeout: 120 * 1000
  },
  /* Global timeout for each test */
  timeout: 30 * 1000,
  /* Global timeout for the whole test run */
  globalTimeout: 10 * 60 * 1000
});
//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJuYW1lcyI6WyJfdGVzdCIsInJlcXVpcmUiLCJfZGVmYXVsdCIsImV4cG9ydHMiLCJkZWZhdWx0IiwiZGVmaW5lQ29uZmlnIiwidGVzdERpciIsImZ1bGx5UGFyYWxsZWwiLCJmb3JiaWRPbmx5IiwicHJvY2VzcyIsImVudiIsIkNJIiwicmV0cmllcyIsIndvcmtlcnMiLCJ1bmRlZmluZWQiLCJyZXBvcnRlciIsInVzZSIsImJhc2VVUkwiLCJ0cmFjZSIsInNjcmVlbnNob3QiLCJ2aWRlbyIsInByb2plY3RzIiwibmFtZSIsImRldmljZXMiLCJ3ZWJTZXJ2ZXIiLCJjb21tYW5kIiwidXJsIiwicmV1c2VFeGlzdGluZ1NlcnZlciIsInRpbWVvdXQiLCJnbG9iYWxUaW1lb3V0Il0sInNvdXJjZXMiOlsicGxheXdyaWdodC5jb25maWcudHMiXSwic291cmNlc0NvbnRlbnQiOlsiaW1wb3J0IHsgZGVmaW5lQ29uZmlnLCBkZXZpY2VzIH0gZnJvbSAnQHBsYXl3cmlnaHQvdGVzdCc7XG5cbi8qKlxuICogUGxheXdyaWdodCBFMkUgVGVzdCBDb25maWd1cmF0aW9uXG4gKiBTZWUgaHR0cHM6Ly9wbGF5d3JpZ2h0LmRldi9kb2NzL3Rlc3QtY29uZmlndXJhdGlvblxuICovXG5leHBvcnQgZGVmYXVsdCBkZWZpbmVDb25maWcoe1xuICAgIHRlc3REaXI6ICcuL3Rlc3RzL2UyZScsXG5cbiAgICAvKiBSdW4gdGVzdHMgaW4gZmlsZXMgaW4gcGFyYWxsZWwgKi9cbiAgICBmdWxseVBhcmFsbGVsOiB0cnVlLFxuXG4gICAgLyogRmFpbCB0aGUgYnVpbGQgb24gQ0kgaWYgeW91IGFjY2lkZW50YWxseSBsZWZ0IHRlc3Qub25seSBpbiB0aGUgc291cmNlIGNvZGUgKi9cbiAgICBmb3JiaWRPbmx5OiAhIXByb2Nlc3MuZW52LkNJLFxuXG4gICAgLyogUmV0cnkgb24gQ0kgb25seSAqL1xuICAgIHJldHJpZXM6IHByb2Nlc3MuZW52LkNJID8gMiA6IDAsXG5cbiAgICAvKiBPcHQgb3V0IG9mIHBhcmFsbGVsIHRlc3RzIG9uIENJICovXG4gICAgd29ya2VyczogcHJvY2Vzcy5lbnYuQ0kgPyAxIDogdW5kZWZpbmVkLFxuXG4gICAgLyogUmVwb3J0ZXIgdG8gdXNlICovXG4gICAgcmVwb3J0ZXI6ICdodG1sJyxcblxuICAgIC8qIFNoYXJlZCBzZXR0aW5ncyBmb3IgYWxsIHRoZSBwcm9qZWN0cyBiZWxvdyAqL1xuICAgIHVzZToge1xuICAgICAgICAvKiBCYXNlIFVSTCB0byB1c2UgaW4gYWN0aW9ucyBsaWtlIGBhd2FpdCBwYWdlLmdvdG8oJy8nKWAgKi9cbiAgICAgICAgYmFzZVVSTDogJ2h0dHA6Ly9sb2NhbGhvc3Q6MzAwMCcsXG5cbiAgICAgICAgLyogQ29sbGVjdCB0cmFjZSB3aGVuIHJldHJ5aW5nIHRoZSBmYWlsZWQgdGVzdCAqL1xuICAgICAgICB0cmFjZTogJ29uLWZpcnN0LXJldHJ5JyxcblxuICAgICAgICAvKiBTY3JlZW5zaG90IG9uIGZhaWx1cmUgKi9cbiAgICAgICAgc2NyZWVuc2hvdDogJ29ubHktb24tZmFpbHVyZScsXG5cbiAgICAgICAgLyogVmlkZW8gb24gZmFpbHVyZSAqL1xuICAgICAgICB2aWRlbzogJ3JldGFpbi1vbi1mYWlsdXJlJyxcbiAgICB9LFxuXG4gICAgLyogQ29uZmlndXJlIHByb2plY3RzIGZvciBtYWpvciBicm93c2VycyAqL1xuICAgIHByb2plY3RzOiBbXG4gICAgICAgIHtcbiAgICAgICAgICAgIG5hbWU6ICdjaHJvbWl1bScsXG4gICAgICAgICAgICB1c2U6IHsgLi4uZGV2aWNlc1snRGVza3RvcCBDaHJvbWUnXSB9LFxuICAgICAgICB9LFxuICAgIF0sXG5cbiAgICAvKiBSdW4geW91ciBsb2NhbCBkZXYgc2VydmVyIGJlZm9yZSBzdGFydGluZyB0aGUgdGVzdHMgKi9cbiAgICB3ZWJTZXJ2ZXI6IHtcbiAgICAgICAgIGNvbW1hbmQ6ICdBUFBfRU5WPXRlc3RpbmcgT1BFTkFJX0FQSV9LRVk9ZHVtbXkgT1BFTkFJX0FQSV9CQVNFX1VSTD1odHRwOi8vMTI3LjAuMC4xOjMxMDAgcGhwIC1TIGxvY2FsaG9zdDozMDAwIC10IHB1YmxpYycsXG4gICAgICAgICB1cmw6ICdodHRwOi8vbG9jYWxob3N0OjMwMDAnLFxuICAgICAgICAgcmV1c2VFeGlzdGluZ1NlcnZlcjogIXByb2Nlc3MuZW52LkNJLFxuICAgICAgICAgdGltZW91dDogMTIwICogMTAwMCxcbiAgICAgfSxcblxuICAgIC8qIEdsb2JhbCB0aW1lb3V0IGZvciBlYWNoIHRlc3QgKi9cbiAgICB0aW1lb3V0OiAzMCAqIDEwMDAsXG5cbiAgICAvKiBHbG9iYWwgdGltZW91dCBmb3IgdGhlIHdob2xlIHRlc3QgcnVuICovXG4gICAgZ2xvYmFsVGltZW91dDogMTAgKiA2MCAqIDEwMDAsXG59KTtcbiJdLCJtYXBwaW5ncyI6Ijs7Ozs7O0FBQUEsSUFBQUEsS0FBQSxHQUFBQyxPQUFBO0FBRUE7QUFDQTtBQUNBO0FBQ0E7QUFIQSxJQUFBQyxRQUFBLEdBQUFDLE9BQUEsQ0FBQUMsT0FBQSxHQUllLElBQUFDLGtCQUFZLEVBQUM7RUFDeEJDLE9BQU8sRUFBRSxhQUFhO0VBRXRCO0VBQ0FDLGFBQWEsRUFBRSxJQUFJO0VBRW5CO0VBQ0FDLFVBQVUsRUFBRSxDQUFDLENBQUNDLE9BQU8sQ0FBQ0MsR0FBRyxDQUFDQyxFQUFFO0VBRTVCO0VBQ0FDLE9BQU8sRUFBRUgsT0FBTyxDQUFDQyxHQUFHLENBQUNDLEVBQUUsR0FBRyxDQUFDLEdBQUcsQ0FBQztFQUUvQjtFQUNBRSxPQUFPLEVBQUVKLE9BQU8sQ0FBQ0MsR0FBRyxDQUFDQyxFQUFFLEdBQUcsQ0FBQyxHQUFHRyxTQUFTO0VBRXZDO0VBQ0FDLFFBQVEsRUFBRSxNQUFNO0VBRWhCO0VBQ0FDLEdBQUcsRUFBRTtJQUNEO0lBQ0FDLE9BQU8sRUFBRSx1QkFBdUI7SUFFaEM7SUFDQUMsS0FBSyxFQUFFLGdCQUFnQjtJQUV2QjtJQUNBQyxVQUFVLEVBQUUsaUJBQWlCO0lBRTdCO0lBQ0FDLEtBQUssRUFBRTtFQUNYLENBQUM7RUFFRDtFQUNBQyxRQUFRLEVBQUUsQ0FDTjtJQUNJQyxJQUFJLEVBQUUsVUFBVTtJQUNoQk4sR0FBRyxFQUFFO01BQUUsR0FBR08sYUFBTyxDQUFDLGdCQUFnQjtJQUFFO0VBQ3hDLENBQUMsQ0FDSjtFQUVEO0VBQ0FDLFNBQVMsRUFBRTtJQUNOQyxPQUFPLEVBQUUsZ0hBQWdIO0lBQ3pIQyxHQUFHLEVBQUUsdUJBQXVCO0lBQzVCQyxtQkFBbUIsRUFBRSxDQUFDbEIsT0FBTyxDQUFDQyxHQUFHLENBQUNDLEVBQUU7SUFDcENpQixPQUFPLEVBQUUsR0FBRyxHQUFHO0VBQ25CLENBQUM7RUFFRjtFQUNBQSxPQUFPLEVBQUUsRUFBRSxHQUFHLElBQUk7RUFFbEI7RUFDQUMsYUFBYSxFQUFFLEVBQUUsR0FBRyxFQUFFLEdBQUc7QUFDN0IsQ0FBQyxDQUFDIiwiaWdub3JlTGlzdCI6W119