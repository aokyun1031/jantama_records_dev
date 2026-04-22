import { test, expect } from '../helpers/fixtures';
import { NONEXISTENT_ID, expectNotFound } from '../helpers/test-helpers';

test.describe('選手詳細ページ', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/player?id=1');
  });

  test('既存選手のページが表示される', async ({ page }) => {
    await expect.soft(page.locator('.player-badge')).toContainText('PLAYER');
    await expect.soft(page.locator('.player-title')).not.toBeEmpty();
  });

  test('キャラクターアイコンが表示される', async ({ page }) => {
    const icon = page.locator('.player-hero-icon');
    if (await icon.count() > 0) {
      await expect(icon).toHaveAttribute('width', '88');
      await expect(icon).toHaveAttribute('height', '88');
    }
  });

  test('編集リンクが表示される', async ({ page }) => {
    await expect.soft(page.locator('.player-edit-link')).toBeVisible();
    await expect.soft(page.locator('.player-edit-link')).toHaveAttribute('href', /player_edit\?id=1/);
  });

  test('大会戦績が表示される', async ({ page }) => {
    await expect(page.locator('.tournament-card')).not.toHaveCount(0);
  });

  test('個人戦績リンクが正しい', async ({ page }) => {
    await expect(page.locator('a[href*="player_tournament"]').first()).toBeVisible();
  });

  test('戦績分析リンクが正しい', async ({ page }) => {
    await expect(page.locator('a[href*="player_analysis"]')).toBeVisible();
  });

  test('存在しないIDで404が返る', async ({ page }) => {
    const response = await page.goto(`/player?id=${NONEXISTENT_ID}`);
    expect.soft(response?.status()).toBe(404);
    await expect.soft(page.locator('.error-code')).toContainText('404');
  });

  test('IDなしで404が返る', async ({ page }) => {
    await expectNotFound(page, '/player');
  });
});
