import { test, expect } from '../helpers/fixtures';
import {
  TEST_PREFIX,
  NONEXISTENT_ID,
  createOptimizedPage,
  createTestTournamentWithPlayers,
  expectNotFound,
  playFinalToCompletion,
} from '../helpers/test-helpers';

test.describe.configure({ mode: 'serial' });
test.describe('インタビュー編集', () => {
  let tournamentId: number;

  test.beforeAll(async ({ browser }) => {
    const page = await createOptimizedPage(browser);
    tournamentId = await createTestTournamentWithPlayers(
      page,
      `${TEST_PREFIX}interview_edit_${Date.now()}`
    );
    await page.close();
  });

  test.beforeEach(async ({ page }) => {
    await page.goto(`/interview_edit?id=${tournamentId}`);
  });

  test('インタビュー編集ページが表示される', async ({ page }) => {
    await expect(page.locator('.iv-badge')).toContainText('INTERVIEW');
    await expect(page.locator('.iv-title')).not.toBeEmpty();
  });

  test('優勝者情報が表示される', async ({ page }) => {
    // 優勝者がいない場合もあるのでセクション自体の存在を確認
    await expect(page.locator('.iv-content')).toBeVisible();
  });

  test('質問を追加できる', async ({ page }) => {
    const addBtn = page.locator('#btn-add-qa');
    await expect(addBtn).toBeVisible();
    await addBtn.click();
    await expect(page.locator('.iv-qa-item')).not.toHaveCount(0);
  });

  test('質問を追加して保存できる', async ({ page }) => {
    await page.click('#btn-add-qa');
    await page.locator('.iv-qa-input').first().fill('テスト質問です');
    await page.locator('.iv-qa-input.answer').first().fill('テスト回答です');

    await page.click('.iv-btn-save');
    await page.waitForURL(/interview_edit\?id=\d+&saved=1/);
    await expect(page.locator('.edit-message.success')).toBeVisible();
  });

  test('大会ページへの戻るリンクがある', async ({ page }) => {
    await expect(page.locator(`a[href="tournament?id=${tournamentId}"]`)).toBeVisible();
  });

  test('質問を削除できる', async ({ page }) => {
    const items = page.locator('.iv-qa-item');
    const initialCount = await items.count();
    await page.click('#btn-add-qa');
    await expect(items).toHaveCount(initialCount + 1);
    await page.locator('.iv-qa-remove').last().click();
    await expect(items).toHaveCount(initialCount);
  });

  test('決勝が完了していない大会では完了セクションが表示されない', async ({ page }) => {
    // この大会は卓を作成していない（決勝未完了）ので、完了ボタンは出ない
    await expect(page.locator('.iv-complete-section')).toHaveCount(0);
    await expect(page.locator('.iv-btn-save')).toBeVisible();
  });

  test('決勝が完了していない大会への完了リクエストは拒否される', async ({ page }) => {
    // 画面にボタンが無くても直接 POST できるため、サーバ側で拒否されることを確認する
    const csrfToken = await page.locator('#interview-form input[name="csrf_token"]').inputValue();
    const response = await page.request.post(`/interview_edit?id=${tournamentId}`, {
      form: { csrf_token: csrfToken, action: 'complete' },
      maxRedirects: 0,
    });
    expect(response.status()).toBe(200);
    expect(await response.text()).toContain('決勝ラウンドの全卓が完了している開催中の大会のみ完了できます');

    // 大会は完了になっていない（管理ページに完了済み表示が出ない）
    await page.goto(`/tournament_edit?id=${tournamentId}`);
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await page.goto(`/table_new?tournament_id=${tournamentId}`);
    await expect(page.locator('.tn-badge')).toContainText('NEW TABLES');
  });

  test('存在しないIDで404', async ({ page }) => {
    await expectNotFound(page, `/interview_edit?id=${NONEXISTENT_ID}`);
  });

  test('IDなしで404', async ({ page }) => {
    await expectNotFound(page, '/interview_edit');
  });
});

test.describe('大会の完了（決勝完了後）', () => {
  let tournamentId: number;

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(120000);
    const page = await createOptimizedPage(browser);
    // 4 人なら 1 卓に収まるので、最初のラウンドがそのまま決勝になる
    tournamentId = await createTestTournamentWithPlayers(
      page,
      `${TEST_PREFIX}interview_complete_${Date.now()}`,
      4
    );
    await playFinalToCompletion(page, tournamentId);
    await page.close();
  });

  test.beforeEach(async ({ page }) => {
    await page.goto(`/interview_edit?id=${tournamentId}`);
  });

  test('インタビュー保存後に大会を完了できる', async ({ page }) => {
    await page.click('#btn-add-qa');
    await page.locator('.iv-qa-input').first().fill('完了テスト質問');
    await page.locator('.iv-qa-input.answer').first().fill('完了テスト回答');
    await page.click('.iv-btn-save');
    await page.waitForURL(/interview_edit\?id=\d+&saved=1/);

    const completeBtn = page.locator('.iv-btn-complete');
    await expect(completeBtn).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await completeBtn.click();
    await page.waitForURL(/\/tournament\?id=\d+/);
    await expect(page.locator('.edit-message.success')).toContainText('完了しました');
  });

  test('完了済み大会では完了セクションが非表示', async ({ page }) => {
    // 前テストで大会を完了済みなので、完了セクションは無い（インタビューの編集は引き続き可能）
    await expect(page.locator('.iv-complete-section')).toHaveCount(0);
    await expect(page.locator('.iv-btn-save')).toBeVisible();
  });

  test('完了済み大会への完了リクエストは拒否される', async ({ page }) => {
    // 再送で完了告知が二重に投稿されないよう、完了済みの大会は受け付けない
    const csrfToken = await page.locator('#interview-form input[name="csrf_token"]').inputValue();
    const response = await page.request.post(`/interview_edit?id=${tournamentId}`, {
      form: { csrf_token: csrfToken, action: 'complete' },
      maxRedirects: 0,
    });
    expect(response.status()).toBe(200);
    expect(await response.text()).toContain('決勝ラウンドの全卓が完了している開催中の大会のみ完了できます');
  });
});
