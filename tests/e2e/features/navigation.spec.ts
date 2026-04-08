import { test, expect } from '../helpers/fixtures';

test.describe('ページ間ナビゲーション', () => {
  test('トップ → 選手一覧 → 選手詳細 → 戦績分析 の遷移', async ({ page }, testInfo) => {
    testInfo.setTimeout(60000);
    // トップページ
    await page.goto('/');

    // 選手一覧へ
    await page.click('a[href="players"]');
    await expect(page).toHaveURL(/\/players/);
    await expect(page.locator('.players-badge')).toBeVisible();

    // 最初の選手カードをクリック
    await page.locator('.player-card').first().click();
    await expect(page).toHaveURL(/\/player\?id=/);
    await expect(page.locator('.player-badge')).toContainText('PLAYER');

    // 戦績分析へ
    await page.click('a[href*="player_analysis"]');
    await expect(page).toHaveURL(/\/player_analysis/);
    await expect(page.locator('.analysis-badge')).toContainText('ANALYSIS');
  });

  test('選手詳細 → 編集 → 戻る の遷移', async ({ page }) => {
    await page.goto('/player?id=1');

    // 編集へ
    await page.click('.player-edit-link');
    await expect(page).toHaveURL(/\/player_edit\?id=1/);
    await expect(page.locator('.edit-badge')).toContainText('EDIT');

    // 戻る
    await page.click('a.btn-cancel');
    await expect(page).toHaveURL(/\/player\?id=1/);
  });

  test('選手一覧 → 新規登録 → 戻る の遷移', async ({ page }) => {
    await page.goto('/players');

    // 新規登録へ
    await page.click('a[href="player_new"]');
    await expect(page).toHaveURL(/\/player_new/);
    await expect(page.locator('.edit-badge')).toContainText('NEW PLAYER');

    // 戻る
    await page.click('a.btn-cancel');
    await expect(page).toHaveURL(/\/players/);
  });

  test('インタビュー → トップへ戻る の遷移', async ({ page }) => {
    await page.goto('/interview');
    await expect(page).toHaveURL(/\/interview/);

    await page.locator('a.site-logo').click();
    await expect(page).toHaveURL(/\/$/);
  });
});
