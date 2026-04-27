import { test, expect } from '../helpers/fixtures';
import {
  TEST_PREFIX,
  NONEXISTENT_ID,
  createOptimizedPage,
  createTestTournamentWithPlayers,
  expectNotFound,
} from '../helpers/test-helpers';

test.describe.configure({ mode: 'serial' });
test.describe('参加表明（選手公開URL）', () => {
  let tournamentId: number;
  let joinedPlayerId: number;
  let unjoinedPlayerId: number;

  test.beforeAll(async ({ browser }) => {
    const page = await createOptimizedPage(browser);
    tournamentId = await createTestTournamentWithPlayers(
      page,
      `${TEST_PREFIX}join_${Date.now()}`,
      4 // 4人だけ登録 → 残り選手は未参加状態
    );

    // 大会選手登録ページのチェックボックスから参加・未参加の player_id を取得
    await page.goto(`/tournament_players?id=${tournamentId}`);
    const joinedValue = await page
      .locator('.player-select-option input[type="checkbox"]:checked')
      .first()
      .getAttribute('value');
    joinedPlayerId = Number(joinedValue ?? 0);

    const unjoinedValue = await page
      .locator('.player-select-option input[type="checkbox"]:not(:checked)')
      .first()
      .getAttribute('value');
    unjoinedPlayerId = Number(unjoinedValue ?? 0);

    await page.close();
  });

  test('参加表明ページが表示される', async ({ page }) => {
    await page.goto(`/tournament_join?tournament_id=${tournamentId}&player_id=${joinedPlayerId}`);
    await expect(page.locator('.tj-badge')).toContainText('JOIN');
    await expect(page.locator('.tj-self-name')).not.toBeEmpty();
  });

  test('参加中の選手は「参加を取り消す」ボタンが表示される', async ({ page }) => {
    await page.goto(`/tournament_join?tournament_id=${tournamentId}&player_id=${joinedPlayerId}`);
    await expect(page.locator('.tj-status-badge.joined')).toBeVisible();
    await expect(page.locator('.tj-btn-leave')).toBeVisible();
  });

  test('未参加の選手は「参加する」ボタンが表示される', async ({ page }) => {
    test.skip(unjoinedPlayerId === 0, '未参加選手なし');
    await page.goto(`/tournament_join?tournament_id=${tournamentId}&player_id=${unjoinedPlayerId}`);
    await expect(page.locator('.tj-status-badge.not-joined')).toBeVisible();
    await expect(page.locator('.tj-btn-join')).toBeVisible();
  });

  test('未参加 → 参加 → 取消の往復ができる', async ({ page }) => {
    test.skip(unjoinedPlayerId === 0, '未参加選手なし');
    const url = `/tournament_join?tournament_id=${tournamentId}&player_id=${unjoinedPlayerId}`;

    await page.goto(url);
    await page.click('.tj-btn-join');
    await page.waitForURL(/tournament_join/);
    await expect(page.locator('.tj-status-badge.joined')).toBeVisible();
    await expect(page.locator('.tj-flash.joined .tj-flash-title')).toContainText('参加表明 完了');

    page.once('dialog', (d) => d.accept());
    await page.click('.tj-btn-leave');
    await page.waitForURL(/tournament_join/);
    await expect(page.locator('.tj-status-badge.not-joined')).toBeVisible();
  });

  test('不正な player_id で 404', async ({ page }) => {
    await expectNotFound(page, `/tournament_join?tournament_id=${tournamentId}&player_id=${NONEXISTENT_ID}`);
  });

  test('不正な tournament_id で 404', async ({ page }) => {
    await expectNotFound(page, `/tournament_join?tournament_id=${NONEXISTENT_ID}&player_id=${joinedPlayerId}`);
  });

  test('パラメータ欠落で 404', async ({ page }) => {
    await expectNotFound(page, '/tournament_join');
  });
});
