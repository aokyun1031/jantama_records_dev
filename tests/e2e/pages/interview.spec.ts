import { test, expect } from '../helpers/fixtures';

test.describe('インタビューページ', () => {
  test.beforeEach(async ({ page }) => {
    // seed の tournament_id=1 にチャンピオン・インタビュー・キャラアイコンが揃っている
    await page.goto('/interview?id=1');
  });

  test('ページが正常に表示される', async ({ page }) => {
    await expect(page).toHaveTitle(/優勝インタビュー/);
  });

  test('チャンピオンプロフィールが表示される', async ({ page }) => {
    await expect(page.locator('.interview-profile-name')).toBeVisible();
    await expect(page.locator('.interview-avatar img')).toBeVisible();
  });

  test('インタビュー項目が表示される', async ({ page }) => {
    await expect(page.locator('.interview-item')).not.toHaveCount(0);
  });

  test('トップページへの戻るリンクがある', async ({ page }) => {
    await expect(page.locator('a.site-logo')).toBeVisible();
  });
});
