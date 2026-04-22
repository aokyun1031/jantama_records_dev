import { test, expect } from '../helpers/fixtures';

test.describe('選手一覧ページ', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/players');
  });

  test('ページが正常に表示される', async ({ page }) => {
    await expect.soft(page).toHaveTitle(/選手一覧/);
    await expect.soft(page.locator('.page-hero-badge')).toContainText('PLAYERS');
  });

  test('選手カードが表示される', async ({ page }) => {
    await expect(page.locator('.player-card')).not.toHaveCount(0);
  });

  test('選手カードに名前が表示される', async ({ page }) => {
    await expect(page.locator('.player-card').first().locator('.player-name')).not.toBeEmpty();
  });

  test('選手カードのリンクが正しい', async ({ page }) => {
    const href = await page.locator('.player-card').first().getAttribute('href');
    expect(href).toMatch(/player\?id=\d+/);
  });

  test('選手を追加ボタンが存在する', async ({ page }) => {
    await expect(page.locator('a[href="player_new"]')).toBeVisible();
  });

  test('トップページへの戻るリンクがある', async ({ page }) => {
    await expect(page.locator('a.site-logo')).toBeVisible();
  });

  test('選手カードに画像またはプレースホルダーがある', async ({ page }) => {
    const firstCard = page.locator('.player-card').first();
    const icons = await firstCard.locator('.player-icon, .player-icon-placeholder').count();
    expect(icons).toBeGreaterThan(0);
  });
});
