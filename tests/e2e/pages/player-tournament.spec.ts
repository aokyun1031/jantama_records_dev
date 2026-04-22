import { test, expect } from '../helpers/fixtures';
import { NONEXISTENT_ID, expectNotFound } from '../helpers/test-helpers';

test.describe('選手大会戦績ページ', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/player_tournament?player_id=1&tournament_id=1');
  });

  test('正常に表示される', async ({ page }) => {
    await expect(page.locator('.result-badge')).toBeVisible();
    await expect(page.locator('.result-title')).not.toBeEmpty();
  });

  test('ラウンドセクションが表示される', async ({ page }) => {
    await expect(page.locator('.round-section')).not.toHaveCount(0);
  });

  test('卓メンバーが表示される', async ({ page }) => {
    await expect(page.locator('.member-row')).not.toHaveCount(0);
  });

  test('個人ページへの戻るリンクがある', async ({ page }) => {
    await expect(page.locator('a.btn-cancel[href="player?id=1"]')).toBeVisible();
  });

  test('パラメータ不足で404', async ({ page }) => {
    await expectNotFound(page, '/player_tournament?player_id=1');
  });

  test('存在しないプレイヤーで404', async ({ page }) => {
    await expectNotFound(page, `/player_tournament?player_id=${NONEXISTENT_ID}&tournament_id=1`);
  });
});
