import { test, expect } from '../helpers/fixtures';
import { expectNotFound } from '../helpers/test-helpers';

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
    await expectNotFound(page, '/player?id=abc');
  });

  test('不正な分析ページIDで404', async ({ page }) => {
    await expectNotFound(page, '/player_analysis?id=-1');
  });
});
