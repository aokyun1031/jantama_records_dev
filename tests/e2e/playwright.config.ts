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
    // reducedMotion は use 直下では無視される（型エラーになり、実行時も効かない）。contextOptions に置く
    contextOptions: { reducedMotion: 'reduce' },
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
