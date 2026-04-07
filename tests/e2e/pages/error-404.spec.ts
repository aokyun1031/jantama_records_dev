import { test, expect } from '../helpers/fixtures';

test.describe('404エラーページ', () => {
  test('存在しないURLで404が表示される', async ({ page }) => {
    const response = await page.goto('/nonexistent-page');
    expect(response?.status()).toBe(404);
    await expect(page.locator('.error-code')).toContainText('404');
    await expect(page.locator('.error-message')).toContainText('ページが見つかりません');
  });

  test('トップページへのリンクがある', async ({ page }) => {
    await page.goto('/nonexistent-page');
    await expect(page.locator('.error-back')).toBeVisible();
  });

  test('不正なプレイヤーIDで404', async ({ page }) => {
    const response = await page.goto('/player?id=abc');
    expect(response?.status()).toBe(404);
  });

  test('不正な分析ページIDで404', async ({ page }) => {
    const response = await page.goto('/player_analysis?id=-1');
    expect(response?.status()).toBe(404);
  });
});
