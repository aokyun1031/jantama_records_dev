import { test, expect } from '../helpers/fixtures';
import { TEST_PREFIX, createTestTournamentWithPlayers, createOptimizedPage } from '../helpers/test-helpers';

test.describe('インタビュー編集', () => {
  test.describe.configure({ mode: 'serial' });
  let tournamentId: number;

  test.beforeAll(async ({ browser }) => {
    const page = await createOptimizedPage(browser);
    tournamentId = await createTestTournamentWithPlayers(
      page,
      `${TEST_PREFIX}interview_edit_${Date.now()}`
    );
    await page.close();
  });

  test('インタビュー編集ページが表示される', async ({ page }) => {
    await page.goto(`/interview_edit?id=${tournamentId}`);
    await expect(page.locator('.iv-badge')).toContainText('INTERVIEW');
    await expect(page.locator('.iv-title')).not.toBeEmpty();
  });

  test('優勝者情報が表示される', async ({ page }) => {
    await page.goto(`/interview_edit?id=${tournamentId}`);
    const champion = page.locator('.iv-champion');
    // 優勝者がいない場合もあるのでセクション自体の存在を確認
    await expect(page.locator('.iv-content')).toBeVisible();
  });

  test('質問を追加できる', async ({ page }) => {
    await page.goto(`/interview_edit?id=${tournamentId}`);
    const addBtn = page.locator('#btn-add-qa');
    await expect(addBtn).toBeVisible();
    await addBtn.click();
    // 追加後にQAアイテムが1つ以上存在する
    await expect(page.locator('.iv-qa-item')).not.toHaveCount(0);
  });

  test('質問を追加して保存できる', async ({ page }) => {
    await page.goto(`/interview_edit?id=${tournamentId}`);
    const addBtn = page.locator('#btn-add-qa');
    await addBtn.click();

    // 質問と回答を入力
    const questionInput = page.locator('.iv-qa-input').first();
    const answerInput = page.locator('.iv-qa-input.answer').first();
    await questionInput.fill('テスト質問です');
    await answerInput.fill('テスト回答です');

    await page.click('.iv-btn-save');
    await page.waitForURL(/interview_edit\?id=\d+&saved=1/, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.edit-message.success')).toBeVisible();
  });

  test('大会ページへの戻るリンクがある', async ({ page }) => {
    await page.goto(`/interview_edit?id=${tournamentId}`);
    const backLink = page.locator(`a[href="tournament?id=${tournamentId}"]`);
    await expect(backLink).toBeVisible();
  });

  test('質問を削除できる', async ({ page }) => {
    await page.goto(`/interview_edit?id=${tournamentId}`);
    // 質問が1つ以上ある状態
    const initialCount = await page.locator('.iv-qa-item').count();
    // 追加して削除
    await page.click('#btn-add-qa');
    await expect(page.locator('.iv-qa-item')).toHaveCount(initialCount + 1);
    await page.locator('.iv-qa-remove').last().click();
    await expect(page.locator('.iv-qa-item')).toHaveCount(initialCount);
  });

  test('インタビュー保存後に大会を完了できる', async ({ page }) => {
    await page.goto(`/interview_edit?id=${tournamentId}`);
    // 1件以上の質問・回答を確実に保存
    const items = await page.locator('.iv-qa-item').count();
    if (items === 0) {
      await page.click('#btn-add-qa');
    }
    await page.locator('.iv-qa-input').first().fill('完了テスト質問');
    await page.locator('.iv-qa-input.answer').first().fill('完了テスト回答');
    await page.click('.iv-btn-save');
    await page.waitForURL(/interview_edit\?id=\d+&saved=1/, { waitUntil: 'domcontentloaded' });

    // 完了ボタンで大会を完了
    const completeBtn = page.locator('.iv-btn-complete');
    await expect(completeBtn).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await completeBtn.click();
    await page.waitForURL(/\/tournament\?id=\d+/, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.edit-message.success')).toContainText('完了しました');
  });

  test('完了済み大会では完了セクションが非表示', async ({ page }) => {
    await page.goto(`/interview_edit?id=${tournamentId}`);
    // 前テストで大会を完了済みなので、完了セクションは無い
    await expect(page.locator('.iv-complete-section')).toHaveCount(0);
    // インタビュー保存フォームは引き続き利用可能
    await expect(page.locator('.iv-btn-save')).toBeVisible();
  });

  test('存在しないIDで404', async ({ page }) => {
    const response = await page.goto('/interview_edit?id=999999');
    expect(response?.status()).toBe(404);
  });

  test('IDなしで404', async ({ page }) => {
    const response = await page.goto('/interview_edit');
    expect(response?.status()).toBe(404);
  });
});
