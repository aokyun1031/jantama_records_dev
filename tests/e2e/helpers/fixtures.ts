import { test as base, type Page } from '@playwright/test';

const EXTERNAL_FONTS = /fonts\.(googleapis|gstatic)\.com/;
const IMAGE_ASSETS = /\.(png|jpg|jpeg|webp|gif|svg|ico)(\?.*)?$/;

/**
 * テストに不要な外部フォント・画像をブロックして高速化する。
 * page fixture と createOptimizedPage で共通利用。
 */
export async function blockHeavyResources(page: Page): Promise<void> {
  await page.route(EXTERNAL_FONTS, (route) => route.abort());
  await page.route(IMAGE_ASSETS, (route) => route.abort());
}

export const test = base.extend({
  page: async ({ page }, use) => {
    await blockHeavyResources(page);
    await use(page);
  },
});

export { expect } from '@playwright/test';
