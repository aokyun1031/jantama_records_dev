import { test, expect } from '@playwright/test';

test.describe('インタビューページ', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/interview');
  });

  test('ページが正常に表示される', async ({ page }) => {
    await expect(page).toHaveTitle(/優勝インタビュー/);
  });

  test('チャンピオンプロフィールが表示される', async ({ page }) => {
    await expect(page.locator('.interview-profile-name')).not.toBeEmpty();
    await expect(page.locator('.interview-avatar img')).toBeVisible();
  });

  test('インタビュー項目が表示される', async ({ page }) => {
    const items = page.locator('.interview-item');
    await expect(items).not.toHaveCount(0);
  });

  test('トップページへの戻るリンクがある', async ({ page }) => {
    await expect(page.locator('a[href="/"]')).toBeVisible();
  });
});
