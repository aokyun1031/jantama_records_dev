import { test as base } from '@playwright/test';

/**
 * 外部リソースをブロックするカスタムfixture。
 * Google Fonts等のテストに不要な外部通信を排除して高速化する。
 */
export const test = base.extend({
  page: async ({ page }, use) => {
    await page.route(
      /fonts\.(googleapis|gstatic)\.com/,
      (route) => route.abort()
    );
    await use(page);
  },
});

export { expect } from '@playwright/test';
