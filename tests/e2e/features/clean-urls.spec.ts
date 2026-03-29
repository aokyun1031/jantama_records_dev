import { test, expect } from '@playwright/test';

test.describe('クリーンURL', () => {
  test('.php なしでアクセスできる', async ({ page }) => {
    const response = await page.goto('/players');
    expect(response?.status()).toBe(200);
    await expect(page).toHaveTitle(/選手一覧/);
  });

  test('.php 付きでアクセスすると拡張子なしにリダイレクトされる', async ({ page }) => {
    const response = await page.goto('/players.php');
    // 最終URLが .php なしになっている
    expect(page.url()).not.toContain('.php');
    expect(page.url()).toContain('/players');
  });

  test('クエリパラメータ付きでも動作する', async ({ page }) => {
    const response = await page.goto('/player?id=1');
    expect(response?.status()).toBe(200);
  });
});
