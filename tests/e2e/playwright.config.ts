import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  testMatch: ['pages/**/*.spec.ts', 'features/**/*.spec.ts'],
  fullyParallel: true,
  workers: process.env.CI ? 2 : 4,
  retries: 1,
  reporter: 'list',
  timeout: 30000,
  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://localhost:8080',
    locale: 'ja-JP',
    navigationTimeout: 15000,
    waitUntil: 'domcontentloaded',
    reducedMotion: 'reduce',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
