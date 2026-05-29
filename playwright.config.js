const { defineConfig, devices } = require('@playwright/test');

const port = Number(process.env.KRYPTO_E2E_PORT || 8772);
const baseURL = process.env.KRYPTO_E2E_BASE_URL || `http://127.0.0.1:${port}`;

module.exports = defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  snapshotPathTemplate: '{testDir}/../../docs/screenshots/{arg}{ext}',
  expect: {
    timeout: 5000,
    toHaveScreenshot: {
      animations: 'disabled',
      maxDiffPixelRatio: 0.02
    }
  },
  use: {
    baseURL,
    actionTimeout: 10000,
    navigationTimeout: 15000,
    locale: 'en-US',
    timezoneId: 'UTC',
    trace: 'retain-on-failure'
  },
  webServer: process.env.KRYPTO_E2E_SKIP_WEBSERVER === '1' ? undefined : {
    command: `php -S 127.0.0.1:${port} -t .`,
    url: `${baseURL}/tests/e2e/fixtures/public-swap-page.php`,
    reuseExistingServer: !process.env.CI,
    stdout: 'pipe',
    stderr: 'pipe'
  },
  projects: [
    {
      name: 'desktop',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 900 }
      }
    },
    {
      name: 'mobile-portrait',
      use: {
        ...devices['Pixel 5']
      }
    }
  ]
});
