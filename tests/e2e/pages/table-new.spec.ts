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

test.describe('卓作成（候補日程あり）', () => {
  let scheduledTournamentId: number;

  test.beforeAll(async ({ browser }) => {
    const page = await createOptimizedPage(browser);
    scheduledTournamentId = await createTestTournamentWithPlayers(
      page,
      `${TEST_PREFIX}table_new_sched_${Date.now()}`
    );

    // 候補日程を2件登録
    await page.goto(`/schedule_candidates_new?tournament_id=${scheduledTournamentId}`);
    await page.fill('.sc-input-date', '2026-07-03');
    await page.fill('.sc-input-time', '昼');
    await page.click('#sc-btn-add');
    await page.locator('.sc-input-date').nth(1).fill('2026-07-04');
    await page.locator('.sc-input-time').nth(1).fill('夜');
    await page.click('.sc-btn-save');
    await page.waitForURL(/\/schedule_combine/);

    // 参加選手を交互に候補1・候補2へ回答させる
    await page.goto(`/tournament_players?id=${scheduledTournamentId}`);
    const values = await page
      .locator('.player-select-option input[type="checkbox"]:checked')
      .evaluateAll((els) => els.map((el) => (el as HTMLInputElement).value));

    for (let i = 0; i < values.length; i++) {
      const candidateIndex = i % 2;
      await page.goto(
        `/schedule_response?tournament_id=${scheduledTournamentId}&round_number=1&player_id=${values[i]}`
      );
      await page.locator('.sr-candidate-option input[type="checkbox"]').nth(candidateIndex).check();
      await page.click('.sr-btn-save');
      await page.waitForURL(/schedule_response/);
    }

    await page.close();
  });

  test.beforeEach(async ({ page }) => {
    await page.goto(`/table_new?tournament_id=${scheduledTournamentId}`);
  });

  test('候補日程ありの場合「候補日程から自動生成」ボタンが表示される', async ({ page }) => {
    await expect(page.locator('#btn-generate-schedule')).toBeVisible();
  });

  test('候補日程から自動生成すると日程ラベル付き卓が作られる', async ({ page }) => {
    await page.click('#btn-generate-schedule');
    await expect(page.locator('.tn-table')).not.toHaveCount(0);
    await expect(page.locator('.tn-table-schedule').first()).not.toBeEmpty();
    await expect(page.locator('#btn-save')).toBeEnabled();
  });
});
