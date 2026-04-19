import { test, expect } from '../helpers/fixtures';

test.describe('landing2（POP LP）', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/landing2');
  });

  test('ページが正常に表示される', async ({ page }) => {
    await expect(page).toHaveTitle(/雀魂部屋主催/);
  });

  test('ヒーローパレードセクションが存在する', async ({ page }) => {
    await expect(page.locator('.lp3-hero')).toBeVisible();
    await expect(page.locator('.lp3-parade')).toBeVisible();
    await expect(page.locator('.lp3-orbit-char')).not.toHaveCount(0);
  });

  test('統計情報が6つ表示される', async ({ page }) => {
    await expect(page.locator('.lp3-stat')).toHaveCount(6);
  });

  test('シリーズタイルが5つ表示される', async ({ page }) => {
    await expect(page.locator('.lp3-series-tile')).toHaveCount(5);
  });

  test('ライト固定（theme-dark が無効化されている）', async ({ page }) => {
    const disabled = await page.evaluate(() => {
      const el = document.getElementById('theme-dark') as HTMLLinkElement | null;
      return el ? el.disabled : null;
    });
    expect(disabled).toBe(true);
  });

  test('選手一覧へのリンクが機能する', async ({ page }) => {
    await page.locator('a[href="players"]').first().click();
    await expect(page).toHaveURL(/\/players/);
  });

  test('大会一覧へのリンクが存在する', async ({ page }) => {
    await expect(page.locator('a[href="tournaments"]')).not.toHaveCount(0);
  });

  test('装飾SVGがレンダリングされている', async ({ page }) => {
    await expect(page.locator('.lp3-deco .lp3-deco-item').first()).toBeAttached();
  });

  test('フッターに著作権表記がある', async ({ page }) => {
    await expect.soft(page.locator('.footer-copyright')).toContainText('Soul Games');
    await expect.soft(page.locator('.footer-copyright')).toContainText('Yostar');
  });
});
