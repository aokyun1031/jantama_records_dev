import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  testMatch: ['pages/**/*.spec.ts', 'features/**/*.spec.ts'],
  fullyParallel: true,
  workers: 2,
  retries: 1,
  reporter: 'list',
  timeout: 30000,
  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://localhost:8080',
    locale: 'ja-JP',
    navigationTimeout: 15000,
    waitUntil: 'domcontentloaded',
    reducedMotion: 'reduce',
    screenshot: 'off',
    trace: 'off',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
