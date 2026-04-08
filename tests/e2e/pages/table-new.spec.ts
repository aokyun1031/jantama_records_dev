import { test, expect } from '../helpers/fixtures';
import { TEST_PREFIX, createTestTournamentWithPlayers, deleteTestTournament , createOptimizedPage } from '../helpers/test-helpers';

test.describe.configure({ mode: 'serial' });
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

  test.afterAll(async ({ browser }) => {
    const page = await createOptimizedPage(browser);
    await deleteTestTournament(page, tournamentId);
    await page.close();
  });

  test('卓作成ページが表示される', async ({ page }) => {
    await page.goto(`/table_new?tournament_id=${tournamentId}`);
    await expect(page.locator('.tn-badge')).toContainText('NEW TABLES');
    await expect(page.locator('#btn-generate')).toBeVisible();
    await expect(page.locator('#btn-save')).toBeDisabled();
  });

  test('ランダム生成で卓が作成される', async ({ page }) => {
    await page.goto(`/table_new?tournament_id=${tournamentId}`);
    await page.click('#btn-generate');
    // 卓カードが表示される
    await expect(page.locator('.tn-table')).not.toHaveCount(0);
    // 保存ボタンが有効になる
    await expect(page.locator('#btn-save')).toBeEnabled();
    // 生成情報が表示される
    await expect(page.locator('#generate-info')).not.toBeEmpty();
  });

  test('生成した卓を保存できる', async ({ page }) => {
    await page.goto(`/table_new?tournament_id=${tournamentId}`);
    await page.click('#btn-generate');
    await expect(page.locator('.tn-table')).not.toHaveCount(0);
    await page.click('#btn-save');
    await page.waitForURL(/\/tournament\?id=\d+/, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.edit-message.success')).toContainText('卓を作成しました');
  });

  test('tournament_idなしで404', async ({ page }) => {
    const response = await page.goto('/table_new');
    expect(response?.status()).toBe(404);
  });

  test('存在しないtournament_idで404', async ({ page }) => {
    const response = await page.goto('/table_new?tournament_id=999999');
    expect(response?.status()).toBe(404);
  });
});
