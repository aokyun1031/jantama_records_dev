import { test, expect } from '@playwright/test';

test.describe('選手一覧ページ', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/players');
  });

  test('ページが正常に表示される', async ({ page }) => {
    await expect(page).toHaveTitle(/選手一覧/);
    await expect(page.locator('.players-badge')).toContainText('PLAYERS');
  });

  test('選手カードが表示される', async ({ page }) => {
    const cards = page.locator('.player-card');
    await expect(cards).not.toHaveCount(0);
  });

  test('選手カードに名前が表示される', async ({ page }) => {
    const firstCard = page.locator('.player-card').first();
    await expect(firstCard.locator('.player-name')).not.toBeEmpty();
  });

  test('選手カードのリンクが正しい', async ({ page }) => {
    const firstCard = page.locator('.player-card').first();
    const href = await firstCard.getAttribute('href');
    expect(href).toMatch(/player\?id=\d+/);
  });

  test('選手を追加ボタンが存在する', async ({ page }) => {
    await expect(page.locator('a[href="player_new"]')).toBeVisible();
  });

  test('トップページへの戻るリンクがある', async ({ page }) => {
    await expect(page.locator('a[href="/"]')).toBeVisible();
  });

  test('選手カードに画像またはプレースホルダーがある', async ({ page }) => {
    const firstCard = page.locator('.player-card').first();
    const hasIcon = await firstCard.locator('.player-icon').count();
    const hasPlaceholder = await firstCard.locator('.player-icon-placeholder').count();
    expect(hasIcon + hasPlaceholder).toBeGreaterThan(0);
  });
});
