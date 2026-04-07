import { test, expect } from '../helpers/fixtures';

test.describe('トップページ', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('ページが正常に表示される', async ({ page }) => {
    await expect(page).toHaveTitle(/最強位戦/);
  });

  test('チャンピオンセクションが表示される', async ({ page }) => {
    await expect(page.locator('.champion-name')).toBeVisible();
    await expect(page.locator('.champion-score')).toBeVisible();
  });

  test('決勝カードが4枚表示される', async ({ page }) => {
    await expect(page.locator('.finalist-card')).toHaveCount(4);
  });

  test('プログレストラッカーが表示される', async ({ page }) => {
    await expect(page.locator('.step-circle')).toHaveCount(4);
  });

  test('タブボタンが4つ存在する', async ({ page }) => {
    await expect(page.locator('.tab-btn')).toHaveCount(4);
  });

  test('総合ポイントセクションが存在する', async ({ page }) => {
    await expect(page.locator('#standings')).toBeVisible();
    await expect(page.locator('.standing-item')).not.toHaveCount(0);
  });

  test('選手一覧へのリンクが機能する', async ({ page }) => {
    await page.click('a[href="players"]');
    await expect(page).toHaveURL(/\/players/);
  });

  test('インタビューリンクが存在する', async ({ page }) => {
    await expect(page.locator('a[href="interview"]')).toBeVisible();
  });

  test('フッターに著作権表記がある', async ({ page }) => {
    await expect(page.locator('.footer-copyright')).toContainText('Soul Games');
    await expect(page.locator('.footer-copyright')).toContainText('Yostar');
  });
});
