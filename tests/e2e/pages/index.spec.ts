import { test, expect } from '../helpers/fixtures';

test.describe('トップページ', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('ページが正常に表示される', async ({ page }) => {
    await expect(page).toHaveTitle(/雀魂部屋主催/);
  });

  test('チャンピオンセクションが表示される', async ({ page }) => {
    await expect.soft(page.locator('.lp-champion-name')).toBeVisible();
    await expect.soft(page.locator('.lp-champion-tournament')).toBeVisible();
  });

  test('統計情報が3つ表示される', async ({ page }) => {
    await expect(page.locator('.lp-stat')).toHaveCount(3);
  });

  test('機能紹介カードが4枚表示される', async ({ page }) => {
    await expect(page.locator('.lp-feature')).toHaveCount(4);
  });

  test('大会一覧セクションが存在する', async ({ page }) => {
    await expect(page.locator('.lp-tournaments')).toBeVisible();
    await expect(page.locator('.lp-tournament-card')).not.toHaveCount(0);
  });

  test('選手一覧へのリンクが機能する', async ({ page }) => {
    await page.click('a[href="players"]');
    await expect(page).toHaveURL(/\/players/);
  });

  test('大会一覧へのリンクが存在する', async ({ page }) => {
    await expect(page.locator('a[href="tournaments"]')).not.toHaveCount(0);
  });

  test('フッターに著作権表記がある', async ({ page }) => {
    await expect.soft(page.locator('.footer-copyright')).toContainText('Soul Games');
    await expect.soft(page.locator('.footer-copyright')).toContainText('Yostar');
  });
});
