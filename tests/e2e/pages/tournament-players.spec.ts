import { test, expect } from '../helpers/fixtures';
import {
  TEST_PREFIX,
  NONEXISTENT_ID,
  createOptimizedPage,
  createTestTournament,
  expectNotFound,
} from '../helpers/test-helpers';

test.describe.configure({ mode: 'serial' });
test.describe('大会選手登録', () => {
  let tournamentId: number;

  test.beforeAll(async ({ browser }) => {
    const page = await createOptimizedPage(browser);
    tournamentId = await createTestTournament(
      page,
      `${TEST_PREFIX}players_tournament_${Date.now()}`
    );
    await page.close();
  });

  test.beforeEach(async ({ page }) => {
    await page.goto(`/tournament_players?id=${tournamentId}`);
  });

  test('選手登録ページが表示される', async ({ page }) => {
    await expect(page.locator('.edit-badge')).toContainText('PLAYERS');
    await expect(page.locator('.edit-subtitle')).toContainText('参加選手の登録');
    await expect(page.locator('.player-select-option')).not.toHaveCount(0);
  });

  test('選手を選択して保存できる', async ({ page }) => {
    // 最低4人選択する必要がある
    const checkboxes = page.locator('.player-select-option input[type="checkbox"]');
    const count = Math.min(4, await checkboxes.count());
    for (let i = 0; i < count; i++) {
      await checkboxes.nth(i).check({ force: true });
    }
    await page.click('button.btn-save');

    await expect(page.locator('.edit-message.success')).toContainText('保存しました', { timeout: 15000 });
  });

  test('全選択・全解除が動作する', async ({ page }) => {
    const checkboxes = page.locator('.player-select-option input[type="checkbox"]');
    const checkedBoxes = page.locator('.player-select-option input[type="checkbox"]:checked');
    const total = await checkboxes.count();

    await page.click('#btn-select-all');
    expect(await checkedBoxes.count()).toBe(total);

    await page.click('#btn-deselect-all');
    expect(await checkedBoxes.count()).toBe(0);
  });

  test('存在しない大会IDで404', async ({ page }) => {
    await expectNotFound(page, `/tournament_players?id=${NONEXISTENT_ID}`);
  });

  test('IDなしで404', async ({ page }) => {
    await expectNotFound(page, '/tournament_players');
  });
});
