import { test, expect } from '../helpers/fixtures';
import { TEST_PREFIX, createTestTournamentWithPlayers, deleteTestTournament } from '../helpers/test-helpers';

test.describe.configure({ mode: 'serial' });
test.describe('卓管理', () => {
  let tournamentId: number;
  let tableId: number;

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(90000);
    const page = await browser.newPage();
    tournamentId = await createTestTournamentWithPlayers(
      page,
      `${TEST_PREFIX}table_mgmt_${Date.now()}`
    );

    // 卓を作成（ランダム生成 → 保存）
    await page.goto(`/table_new?tournament_id=${tournamentId}`);
    await page.click('#btn-generate');
    await expect(page.locator('.tn-table')).not.toHaveCount(0);
    await page.click('#btn-save');
    await page.waitForURL(/\/tournament\?id=\d+/, { timeout: 60000, waitUntil: 'domcontentloaded' });

    // 卓IDを取得
    const tableLink = page.locator('.td-table-card').first();
    const href = await tableLink.getAttribute('href');
    tableId = parseInt(href!.match(/id=(\d+)/)![1], 10);
    await page.close();
  });

  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage();
    await deleteTestTournament(page, tournamentId);
    await page.close();
  });

  test('卓管理ページが表示される', async ({ page }) => {
    await page.goto(`/table?id=${tableId}`);
    await expect(page.locator('.tb-title')).toContainText('卓');
    await expect(page.locator('.tb-player')).toHaveCount(4);
  });

  test('対局日を設定できる', async ({ page }) => {
    await page.goto(`/table?id=${tableId}`);
    await page.fill('input[name="played_date"]', '2026-04-10');
    await page.click('form:has(input[value="schedule"]) .tb-btn-small');
    await page.waitForURL(/table\?id=\d+&saved=1/, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.edit-message.success')).toBeVisible();
  });

  test('スコアを入力して一時保存できる', async ({ page }) => {
    await page.goto(`/table?id=${tableId}`);
    const scoreInputs = page.locator('form:has(input[value="game_data"]) input.tb-score-input');
    const count = await scoreInputs.count();
    const scorePool = [25.0, 10.0, -5.0, -30.0, 15.0, -15.0];
    for (let i = 0; i < count; i++) {
      await scoreInputs.nth(i).fill((scorePool[i % scorePool.length]).toString());
    }
    await page.click('.tb-btn-save-draft');
    await page.waitForURL(/table\?id=\d+&saved=1/, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.edit-message.success')).toBeVisible();
  });

  test('対局結果を保存して卓を完了にできる', async ({ page }) => {
    await page.goto(`/table?id=${tableId}`);
    const doneBtn = page.locator('button[name="complete"]');
    await expect(doneBtn).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await doneBtn.click();
    // 大会ページにリダイレクト
    await page.waitForURL(/\/tournament\?id=\d+/, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.edit-message.success')).toContainText('完了しました');
  });

  test('完了した卓は読み取り専用', async ({ page }) => {
    await page.goto(`/table?id=${tableId}`);
    await expect(page.locator('.tb-badge')).toContainText('COMPLETED');
    // フォームが表示されない
    await expect(page.locator('form:has(input[value="game_data"])')).toHaveCount(0);
  });

  test('存在しないIDで404', async ({ page }) => {
    const response = await page.goto('/table?id=999999');
    expect(response?.status()).toBe(404);
  });

  test('IDなしで404', async ({ page }) => {
    const response = await page.goto('/table');
    expect(response?.status()).toBe(404);
  });
});
