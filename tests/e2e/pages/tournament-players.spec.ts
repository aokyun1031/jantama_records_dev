import { test, expect } from '../helpers/fixtures';
import { TEST_PREFIX, deleteTestTournament , createOptimizedPage } from '../helpers/test-helpers';

test.describe.configure({ mode: 'serial' });
test.describe('大会選手登録', () => {
  let tournamentId: number;

  test.beforeAll(async ({ browser }) => {
    const page = await createOptimizedPage(browser);
    const name = `${TEST_PREFIX}players_tournament_${Date.now()}`;
    await page.goto('/tournament_new');
    await page.fill('input[name="name"]', name);
    await page.click('button.btn-save');
    await page.waitForURL(/\/tournaments(\?.*)?$/, { waitUntil: 'domcontentloaded' });

    // 作成した大会のIDを取得
    await page.goto('/tournaments');
    const card = page.locator('.tournament-card', { hasText: name });
    const link = card.locator('a.tournament-link', { hasText: '管理ページ' });
    const href = await link.getAttribute('href');
    tournamentId = parseInt(href!.match(/id=(\d+)/)![1], 10);
    await page.close();
  });

  test.afterAll(async ({ browser }) => {
    const page = await createOptimizedPage(browser);
    await deleteTestTournament(page, tournamentId);
    await page.close();
  });

  test('選手登録ページが表示される', async ({ page }) => {
    await page.goto(`/tournament_players?id=${tournamentId}`);
    await expect(page.locator('.edit-badge')).toContainText('PLAYERS');
    await expect(page.locator('.edit-subtitle')).toContainText('参加選手の登録');
    await expect(page.locator('.player-select-option')).not.toHaveCount(0);
  });

  test('選手を選択して保存できる', async ({ page }) => {
    await page.goto(`/tournament_players?id=${tournamentId}`);
    // 最低4人選択する必要がある
    const checkboxes = page.locator('.player-select-option input[type="checkbox"]');
    const count = Math.min(4, await checkboxes.count());
    for (let i = 0; i < count; i++) {
      await checkboxes.nth(i).check({ force: true });
    }
    await page.click('button.btn-save');
    // POST処理後、成功メッセージまたはエラーメッセージが表示される
    const success = page.locator('.edit-message.success');
    const error = page.locator('.edit-message.error');
    await expect(success.or(error)).toBeVisible({ timeout: 15000 });
    // エラーがなければ成功
    if (await error.isVisible()) {
      const msg = await error.textContent();
      throw new Error(`保存に失敗: ${msg}`);
    }
    await expect(success).toContainText('保存しました');
  });

  test('全選択・全解除が動作する', async ({ page }) => {
    await page.goto(`/tournament_players?id=${tournamentId}`);
    await page.click('#btn-select-all');
    const total = await page.locator('.player-select-option input[type="checkbox"]').count();
    const checked = await page.locator('.player-select-option input[type="checkbox"]:checked').count();
    expect(checked).toBe(total);

    await page.click('#btn-deselect-all');
    const checkedAfter = await page.locator('.player-select-option input[type="checkbox"]:checked').count();
    expect(checkedAfter).toBe(0);
  });

  test('検索フィルターが動作する', async ({ page }) => {
    await page.goto(`/tournament_players?id=${tournamentId}`);
    const initialCount = await page.locator('.player-select-option').count();
    expect(initialCount).toBeGreaterThan(0);

    await page.fill('#player-search', 'zzzzxxxx_no_match');
    await expect(page.locator('.player-select-option:not([hidden])')).toHaveCount(0);

    await page.fill('#player-search', '');
    await expect(page.locator('.player-select-option:not([hidden])')).toHaveCount(initialCount);
  });

  test('存在しない大会IDで404', async ({ page }) => {
    const response = await page.goto('/tournament_players?id=999999');
    expect(response?.status()).toBe(404);
  });

  test('IDなしで404', async ({ page }) => {
    const response = await page.goto('/tournament_players');
    expect(response?.status()).toBe(404);
  });
});
