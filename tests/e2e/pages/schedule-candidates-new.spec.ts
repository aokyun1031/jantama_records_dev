import { test, expect } from '../helpers/fixtures';
import {
  TEST_PREFIX,
  NONEXISTENT_ID,
  createOptimizedPage,
  createTestTournamentWithPlayers,
  expectNotFound,
} from '../helpers/test-helpers';

test.describe.configure({ mode: 'serial' });
test.describe('候補日程設定', () => {
  let tournamentId: number;

  test.beforeAll(async ({ browser }) => {
    const page = await createOptimizedPage(browser);
    tournamentId = await createTestTournamentWithPlayers(
      page,
      `${TEST_PREFIX}sched_cand_${Date.now()}`
    );
    await page.close();
  });

  test.beforeEach(async ({ page }) => {
    await page.goto(`/schedule_candidates_new?tournament_id=${tournamentId}`);
  });

  test('候補日程設定ページが表示される', async ({ page }) => {
    await expect(page.locator('.sc-badge')).toContainText('SCHEDULE');
    await expect(page.locator('.sc-candidate-row')).toHaveCount(1);
  });

  test('候補を追加・削除できる', async ({ page }) => {
    await page.click('#sc-btn-add');
    await expect(page.locator('.sc-candidate-row')).toHaveCount(2);
    await page.locator('.sc-btn-remove').first().click();
    await expect(page.locator('.sc-candidate-row')).toHaveCount(1);
  });

  test('昼/夜クイックボタンで時間帯を入力できる', async ({ page }) => {
    await page.locator('.sc-btn-quick', { hasText: '昼' }).first().click();
    await expect(page.locator('.sc-input-time').first()).toHaveValue('昼');
  });

  test('候補日程未入力で保存するとエラー', async ({ page }) => {
    await page.click('.sc-btn-save');
    await expect(page.locator('.edit-message.error')).toBeVisible();
  });

  test('候補日程を保存すると回答状況確認ページへ遷移する', async ({ page }) => {
    await page.fill('.sc-input-date', '2026-07-01');
    await page.fill('.sc-input-time', '昼');
    await page.click('.sc-btn-save');
    await page.waitForURL(/\/schedule_combine\?tournament_id=\d+&round_number=1/);
  });

  test('tournament_idなしで404', async ({ page }) => {
    await expectNotFound(page, '/schedule_candidates_new');
  });

  test('存在しないtournament_idで404', async ({ page }) => {
    await expectNotFound(page, `/schedule_candidates_new?tournament_id=${NONEXISTENT_ID}`);
  });
});
