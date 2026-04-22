import { test, expect } from '../helpers/fixtures';
import {
  TEST_PREFIX,
  NONEXISTENT_ID,
  createOptimizedPage,
  createTestTournamentWithPlayers,
  expectNotFound,
} from '../helpers/test-helpers';

test.describe.configure({ mode: 'serial' });
test.describe('大会詳細', () => {
  let tournamentId: number;

  test.beforeAll(async ({ browser }) => {
    const page = await createOptimizedPage(browser);
    tournamentId = await createTestTournamentWithPlayers(
      page,
      `${TEST_PREFIX}detail_${Date.now()}`
    );
    await page.close();
  });

  test.beforeEach(async ({ page }) => {
    await page.goto(`/tournament?id=${tournamentId}`);
  });

  test('大会詳細ページが表示される', async ({ page }) => {
    await expect(page.locator('.td-badge')).toContainText('TOURNAMENT');
    await expect(page.locator('.td-title')).not.toBeEmpty();
    await expect(page.locator('.td-rules')).toBeVisible();
  });

  test('卓作成を促すCTAバナーが表示される', async ({ page }) => {
    const cta = page.locator('.td-cta');
    await expect(cta).toBeVisible();
    await expect(cta).toContainText('卓を作成');
  });

  test('大会名の横に編集アイコンがある', async ({ page }) => {
    const editLink = page.locator('.td-edit-link');
    await expect(editLink).toBeVisible();
    await expect(editLink).toHaveAttribute('href', `tournament_edit?id=${tournamentId}`);
  });

  test('登録選手一覧が表示される', async ({ page }) => {
    await expect(page.locator('.td-standings')).toBeVisible();
    await expect(page.locator('.td-standings-title')).toContainText('登録選手一覧');
    await expect(page.locator('.td-standings-table tbody tr')).not.toHaveCount(0);
  });

  test('大会一覧への戻るリンクがある', async ({ page }) => {
    await expect(page.locator('a.btn-cancel[href="tournaments"]')).toBeVisible();
  });

  test('卓未作成の大会で削除セクションが表示される', async ({ page }) => {
    await expect(page.locator('.td-delete')).toBeVisible();
    await expect(page.locator('.td-btn-delete')).toBeVisible();
  });

  test('大会を削除できる', async ({ page }) => {
    const deleteId = await createTestTournamentWithPlayers(
      page,
      `${TEST_PREFIX}delete_from_detail_${Date.now()}`
    );

    await page.goto(`/tournament?id=${deleteId}`);
    page.once('dialog', (dialog) => dialog.accept());
    await page.click('.td-btn-delete');
    await page.waitForURL(/\/tournaments/);
    await expect(page.locator('.edit-message.success')).toContainText('削除しました');
  });

  test('存在しないIDで404', async ({ page }) => {
    await expectNotFound(page, `/tournament?id=${NONEXISTENT_ID}`);
  });

  test('IDなしで404', async ({ page }) => {
    await expectNotFound(page, '/tournament');
  });
});
