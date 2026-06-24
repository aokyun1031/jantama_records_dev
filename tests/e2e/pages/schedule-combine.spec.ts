import { test, expect } from '../helpers/fixtures';
import {
  TEST_PREFIX,
  NONEXISTENT_ID,
  createOptimizedPage,
  createTestTournamentWithPlayers,
} from '../helpers/test-helpers';

test.describe.configure({ mode: 'serial' });
test.describe('候補日程の回答状況確認', () => {
  let tournamentId: number;

  test.beforeAll(async ({ browser }) => {
    const page = await createOptimizedPage(browser);
    tournamentId = await createTestTournamentWithPlayers(
      page,
      `${TEST_PREFIX}sched_combine_${Date.now()}`
    );
    await page.close();
  });

  test('候補日程未設定では設定ページへの案内が表示される', async ({ page }) => {
    await page.goto(`/schedule_combine?tournament_id=${tournamentId}&round_number=1`);
    await expect(page.locator('.sb-empty')).toContainText('候補日程が未設定です');
  });

  test('候補日程設定後は回答状況・未回答選手一覧・自動生成導線が表示される', async ({ page }) => {
    await page.goto(`/schedule_candidates_new?tournament_id=${tournamentId}`);
    await page.fill('.sc-input-date', '2026-07-02');
    await page.fill('.sc-input-time', '夜');
    await page.click('.sc-btn-save');
    await page.waitForURL(/\/schedule_combine/);

    await expect(page.locator('.sb-candidate-row')).toHaveCount(1);
    await expect(page.locator('.sb-player-card')).not.toHaveCount(0);
    await expect(page.locator('.sb-btn-dispatch-all')).toBeVisible();
    await expect(page.locator('.sb-btn-generate')).toHaveAttribute(
      'href',
      new RegExp(`table_new\\?tournament_id=${tournamentId}`)
    );
  });

  test('未回答選手の代理で回答を登録できる', async ({ page }) => {
    await page.goto(`/schedule_combine?tournament_id=${tournamentId}&round_number=1`);
    const beforeCount = await page.locator('.sb-player-card').count();

    const firstCard = page.locator('.sb-player-card').first();
    await firstCard.locator('input[type="checkbox"]').first().check();
    await firstCard.locator('.sb-btn-respond-save').click();
    await page.waitForURL(/schedule_combine/);

    await expect(page.locator('.edit-message.success')).toContainText('回答を登録しました');
    await expect(page.locator('.sb-player-card')).toHaveCount(beforeCount - 1);
  });

  test('候補未選択で代理登録するとエラー', async ({ page }) => {
    await page.goto(`/schedule_combine?tournament_id=${tournamentId}&round_number=1`);
    const firstCard = page.locator('.sb-player-card').first();
    await firstCard.locator('.sb-btn-respond-save').click();
    await expect(page.locator('.edit-message.error')).toBeVisible();
  });

  test('不正なplayer_idで代理登録するとエラー', async ({ page }) => {
    await page.goto(`/schedule_combine?tournament_id=${tournamentId}&round_number=1`);
    const firstForm = page.locator('.sb-player-respond-form').first();
    await firstForm.locator('input[name="player_id"]').evaluate(
      (el: HTMLInputElement, id: number) => { el.value = String(id); },
      NONEXISTENT_ID
    );
    await firstForm.locator('input[type="checkbox"]').first().check();
    await firstForm.locator('.sb-btn-respond-save').click();
    await expect(page.locator('.edit-message.error')).toContainText('不正な選手です');
  });
});
