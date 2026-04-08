import { test as base } from '@playwright/test';

/**
 * 外部リソースをブロックするカスタムfixture。
 * Google Fonts等のテストに不要な外部通信を排除して高速化する。
 */
export const test = base.extend({
  page: async ({ page }, use) => {
    // テストに不���な外部リソース・画像���ブロックして高速化
    await page.route(
      /fonts\.(googleapis|gstatic)\.com/,
      (route) => route.abort()
    );
    await page.route(
      /\.(png|jpg|jpeg|webp|gif|svg|ico)(\?.*)?$/,
      (route) => route.abort()
    );
    await use(page);
  },
});

export { expect } from '@playwright/test';
