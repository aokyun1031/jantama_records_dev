import { test, expect } from '../helpers/fixtures';
import {
  TEST_PREFIX,
  NONEXISTENT_ID,
  createOptimizedPage,
  createTestTournamentWithPlayers,
  expectNotFound,
} from '../helpers/test-helpers';

test.describe.configure({ mode: 'serial', timeout: 90000 });
test.describe('卓作成', () => {
  let tournamentId: number;

  test.beforeAll(async ({ browser }) => {
    const page = await createOptimizedPage(browser);
    tournamentId = await createTestTournamentWithPlayers(
      page,
      `${TEST_PREFIX}table_new_${Date.now()}`
    );
    await page.close();
  });

  test.beforeEach(async ({ page }) => {
    await page.goto(`/table_new?tournament_id=${tournamentId}`);
  });

  test('卓作成ページが表示される', async ({ page }) => {
    await expect(page.locator('.tn-badge')).toContainText('NEW TABLES');
    await expect(page.locator('#btn-generate')).toBeVisible();
    await expect(page.locator('#btn-save')).toBeDisabled();
  });

  test('ランダム生成で卓が作成される', async ({ page }) => {
    await page.click('#btn-generate');
    await expect(page.locator('.tn-table')).not.toHaveCount(0);
    await expect(page.locator('#btn-save')).toBeEnabled();
    await expect(page.locator('#generate-info')).not.toBeEmpty();
  });

  test('生成した卓を保存できる', async ({ page }) => {
    await page.click('#btn-generate');
    await expect(page.locator('.tn-table')).not.toHaveCount(0);
    await page.click('#btn-save');
    await page.waitForURL(/\/tournament\?id=\d+/);
    await expect(page.locator('.edit-message.success')).toContainText('卓を作成しました');
  });

  test('tournament_idなしで404', async ({ page }) => {
    await expectNotFound(page, '/table_new');
  });

  test('存在しないtournament_idで404', async ({ page }) => {
    await expectNotFound(page, `/table_new?tournament_id=${NONEXISTENT_ID}`);
  });
});
