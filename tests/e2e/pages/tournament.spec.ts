import { test, expect } from '../helpers/fixtures';
import { TEST_PREFIX, createTestTournamentWithPlayers, deleteTestTournament , createOptimizedPage } from '../helpers/test-helpers';

test.describe('大会詳細', () => {
  test.describe.configure({ mode: 'serial' });
  let tournamentId: number;

  test.beforeAll(async ({ browser }) => {
    const page = await createOptimizedPage(browser);
    tournamentId = await createTestTournamentWithPlayers(
      page,
      `${TEST_PREFIX}detail_${Date.now()}`
    );
    await page.close();
  });

  test.afterAll(async ({ browser }) => {
    const page = await createOptimizedPage(browser);
    await deleteTestTournament(page, tournamentId);
    await page.close();
  });

  test('大会詳細ページが表示される', async ({ page }) => {
    await page.goto(`/tournament?id=${tournamentId}`);
    await expect(page.locator('.td-badge')).toContainText('TOURNAMENT');
    await expect(page.locator('.td-title')).not.toBeEmpty();
    await expect(page.locator('.td-rules')).toBeVisible();
  });

  test('卓作成を促すCTAバナーが表示される', async ({ page }) => {
    await page.goto(`/tournament?id=${tournamentId}`);
    const cta = page.locator('.td-cta');
    await expect(cta).toBeVisible();
    await expect(cta).toContainText('卓を作成');
  });

  test('大会名の横に編集アイコンがある', async ({ page }) => {
    await page.goto(`/tournament?id=${tournamentId}`);
    const editLink = page.locator('.td-edit-link');
    await expect(editLink).toBeVisible();
    await expect(editLink).toHaveAttribute('href', `tournament_edit?id=${tournamentId}`);
  });

  test('登録選手一覧が表示される', async ({ page }) => {
    await page.goto(`/tournament?id=${tournamentId}`);
    await expect(page.locator('.td-standings')).toBeVisible();
    await expect(page.locator('.td-standings-title')).toContainText('登録選手一覧');
    await expect(page.locator('.td-standings-table tbody tr')).not.toHaveCount(0);
  });

  test('大会一覧への戻るリンクがある', async ({ page }) => {
    await page.goto(`/tournament?id=${tournamentId}`);
    await expect(page.locator('a.btn-cancel[href="tournaments"]')).toBeVisible();
  });

  test('卓未作成の大会で削除セクションが表示される', async ({ page }) => {
    await page.goto(`/tournament?id=${tournamentId}`);
    await expect(page.locator('.td-delete')).toBeVisible();
    await expect(page.locator('.td-btn-delete')).toBeVisible();
  });

  test('大会を削除できる', async ({ page }) => {
    // 削除用の大会を作成
    const name = `${TEST_PREFIX}delete_from_detail_${Date.now()}`;
    const deleteId = await createTestTournamentWithPlayers(page, name);

    await page.goto(`/tournament?id=${deleteId}`);
    page.once('dialog', (dialog) => dialog.accept());
    await page.click('.td-btn-delete');
    await page.waitForURL(/\/tournaments/, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.edit-message.success')).toContainText('削除しました');
  });

  test('存在しないIDで404', async ({ page }) => {
    const response = await page.goto('/tournament?id=999999');
    expect(response?.status()).toBe(404);
  });

  test('IDなしで404', async ({ page }) => {
    const response = await page.goto('/tournament');
    expect(response?.status()).toBe(404);
  });
});
