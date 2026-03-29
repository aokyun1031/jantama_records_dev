import { test, expect } from '@playwright/test';

test.describe('選手戦績分析ページ', () => {
  test('大会参加済み選手の分析が表示される', async ({ page }) => {
    await page.goto('/player_analysis?id=1');
    await expect(page.locator('.analysis-badge')).toContainText('ANALYSIS');
    await expect(page.locator('.analysis-title')).not.toBeEmpty();
  });

  test('サマリーカードが表示される', async ({ page }) => {
    await page.goto('/player_analysis?id=1');
    await expect(page.locator('.summary-card')).not.toHaveCount(0);
  });

  test('対戦成績テーブルが表示される', async ({ page }) => {
    await page.goto('/player_analysis?id=1');
    await expect(page.locator('.h2h-table')).toBeVisible();
  });

  test('スコア推移テーブルが表示される', async ({ page }) => {
    await page.goto('/player_analysis?id=1');
    await expect(page.locator('.history-table')).toBeVisible();
  });

  test('存在しないIDで404', async ({ page }) => {
    const response = await page.goto('/player_analysis?id=99999');
    expect(response?.status()).toBe(404);
  });

  test('個人ページへの戻るリンクがある', async ({ page }) => {
    await page.goto('/player_analysis?id=1');
    await expect(page.locator('a[href*="player?id=1"]')).toBeVisible();
  });
});
