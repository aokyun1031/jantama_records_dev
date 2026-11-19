import { defineConfig, devices } from '@playwright/test';
import { BASE_URL } from './helpers/stack';

export default defineConfig({
  testDir: '.',
  testMatch: ['pages/**/*.spec.ts', 'features/**/*.spec.ts'],
  globalSetup: './global-setup.ts',
  fullyParallel: true,
  workers: 2,
  retries: 1,
  reporter: 'list',
  timeout: 30000,
  use: {
    baseURL: BASE_URL,
    locale: 'ja-JP',
    navigationTimeout: 60000,
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
