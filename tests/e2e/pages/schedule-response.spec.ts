import { test, expect } from '../helpers/fixtures';
import {
  TEST_PREFIX,
  NONEXISTENT_ID,
  createOptimizedPage,
  createTestTournamentWithPlayers,
  expectNotFound,
} from '../helpers/test-helpers';

test.describe.configure({ mode: 'serial' });
test.describe('参加可能日回答（選手公開URL）', () => {
  let tournamentId: number;
  let playerId: number;
  let unjoinedPlayerId: number;

  test.beforeAll(async ({ browser }) => {
    const page = await createOptimizedPage(browser);
    tournamentId = await createTestTournamentWithPlayers(
      page,
      `${TEST_PREFIX}sched_resp_${Date.now()}`
    );

    await page.goto(`/schedule_candidates_new?tournament_id=${tournamentId}`);
    await page.fill('.sc-input-date', '2026-07-01');
    await page.fill('.sc-input-time', '昼');
    await page.click('.sc-btn-save');
    await page.waitForURL(/\/schedule_combine/);

    await page.goto(`/tournament_players?id=${tournamentId}`);
    const value = await page
      .locator('.player-select-option input[type="checkbox"]:checked')
      .first()
      .getAttribute('value');
    playerId = Number(value ?? 0);

    const unjoinedValue = await page
      .locator('.player-select-option input[type="checkbox"]:not(:checked)')
      .first()
      .getAttribute('value');
    unjoinedPlayerId = Number(unjoinedValue ?? 0);

    await page.close();
  });

  test('回答ページが表示される', async ({ page }) => {
    await page.goto(`/schedule_response?tournament_id=${tournamentId}&round_number=1&player_id=${playerId}`);
    await expect(page.locator('.sr-badge')).toContainText('SCHEDULE');
    await expect(page.locator('.sr-candidate-option')).toHaveCount(1);
  });

  test('候補を選択して回答できる', async ({ page }) => {
    await page.goto(`/schedule_response?tournament_id=${tournamentId}&round_number=1&player_id=${playerId}`);
    await page.locator('.sr-candidate-option input[type="checkbox"]').first().check();
    await page.click('.sr-btn-save');
    await page.waitForURL(/schedule_response/);
    await expect(page.locator('.edit-message.success')).toContainText('回答しました');
    await expect(page.locator('.sr-candidate-option input[type="checkbox"]').first()).toBeChecked();
  });

  test('未選択で回答するとエラー', async ({ page }) => {
    await page.goto(`/schedule_response?tournament_id=${tournamentId}&round_number=1&player_id=${playerId}`);
    await page.locator('.sr-candidate-option input[type="checkbox"]').first().uncheck();
    await page.click('.sr-btn-save');
    await expect(page.locator('.edit-message.error')).toBeVisible();
  });

  test('候補日程未設定ラウンドへのアクセスは404', async ({ page }) => {
    await expectNotFound(page, `/schedule_response?tournament_id=${tournamentId}&round_number=99&player_id=${playerId}`);
  });

  test('不正なplayer_idで404', async ({ page }) => {
    await expectNotFound(page, `/schedule_response?tournament_id=${tournamentId}&round_number=1&player_id=${NONEXISTENT_ID}`);
  });

  test('大会に参加していない選手IDで404', async ({ page }) => {
    test.skip(unjoinedPlayerId === 0, '未参加選手なし');
    await expectNotFound(page, `/schedule_response?tournament_id=${tournamentId}&round_number=1&player_id=${unjoinedPlayerId}`);
  });

  test('不正なtournament_idで404', async ({ page }) => {
    await expectNotFound(page, `/schedule_response?tournament_id=${NONEXISTENT_ID}&round_number=1&player_id=${playerId}`);
  });

  test('パラメータ欠落で404', async ({ page }) => {
    await expectNotFound(page, '/schedule_response');
  });
});
