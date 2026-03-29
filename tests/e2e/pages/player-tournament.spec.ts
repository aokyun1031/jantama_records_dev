import { test, expect } from '@playwright/test';

test.describe('選手大会戦績ページ', () => {
  test('正常に表示される', async ({ page }) => {
    await page.goto('/player_tournament?player_id=1&tournament_id=1');
    await expect(page.locator('.result-badge')).toBeVisible();
    await expect(page.locator('.result-title')).not.toBeEmpty();
  });

  test('ラウンドセクションが表示される', async ({ page }) => {
    await page.goto('/player_tournament?player_id=1&tournament_id=1');
    await expect(page.locator('.round-section')).not.toHaveCount(0);
  });

  test('卓メンバーが表示される', async ({ page }) => {
    await page.goto('/player_tournament?player_id=1&tournament_id=1');
    await expect(page.locator('.member-row')).not.toHaveCount(0);
  });

  test('パラメータ不足で404', async ({ page }) => {
    const response = await page.goto('/player_tournament?player_id=1');
    expect(response?.status()).toBe(404);
  });

  test('存在しないプレイヤーで404', async ({ page }) => {
    const response = await page.goto('/player_tournament?player_id=99999&tournament_id=1');
    expect(response?.status()).toBe(404);
  });

  test('個人ページへの戻るリンクがある', async ({ page }) => {
    await page.goto('/player_tournament?player_id=1&tournament_id=1');
    await expect(page.locator('a[href*="player?id=1"]')).toBeVisible();
  });
});
