import { test, expect } from '@playwright/test';
import { TEST_PREFIX, createTestPlayer, deleteTestPlayer, cleanupTestPlayers } from '../helpers/test-helpers';

test.describe('選手編集・削除', () => {
  let testPlayerId: number;
  const testName = `${TEST_PREFIX}edit_${Date.now()}`;

  test.beforeAll(async ({ browser }, testInfo) => {
    testInfo.setTimeout(60000);
    const page = await browser.newPage();
    testPlayerId = await createTestPlayer(page, testName, '編集前');
    await page.close();
  });

  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage();
    // テスト名で検索して全て削除（beforeAll失敗時の孤立データも含む）
    await cleanupTestPlayers(page);
    await page.close();
  });

  test('編集フォームが表示される', async ({ page }) => {
    await page.goto(`/player_edit?id=${testPlayerId}`);
    await expect(page.locator('.edit-badge')).toContainText('EDIT');
    await expect(page.locator('.edit-title')).toContainText(testName);
    await expect(page.locator('input[name="nickname"]')).toHaveValue('編集前');
  });

  test('CSRFトークンが存在する', async ({ page }) => {
    await page.goto(`/player_edit?id=${testPlayerId}`);
    const token = await page.locator('input[name="csrf_token"]').first().inputValue();
    expect(token).toBeTruthy();
    expect(token.length).toBe(64); // bin2hex(random_bytes(32))
  });

  test('呼称を変更して保存できる', async ({ page }) => {
    await page.goto(`/player_edit?id=${testPlayerId}`);
    await page.fill('input[name="nickname"]', '編集後');
    await page.click('button.btn-save');

    await page.waitForURL(/saved=1/);
    await expect(page.locator('.edit-message.success')).toBeVisible();

    // 値が更新されている
    await expect(page.locator('input[name="nickname"]')).toHaveValue('編集後');
  });

  test('空の呼称で保存するとエラー', async ({ page }) => {
    await page.goto(`/player_edit?id=${testPlayerId}`);
    await page.evaluate(() => {
      (document.querySelector('input[name="nickname"]') as HTMLInputElement).removeAttribute('required');
    });
    await page.fill('input[name="nickname"]', '');
    await page.click('button.btn-save');
    await expect(page.locator('.edit-message.error')).toContainText('呼称を入力');
  });

  test('キャラクターを変更できる', async ({ page }) => {
    await page.goto(`/player_edit?id=${testPlayerId}`);
    // 2番目のキャラクターを選択
    const options = page.locator('.chara-option');
    if (await options.count() > 1) {
      await options.nth(1).click();
      await page.click('button.btn-save');
      await page.waitForURL(/saved=1/);
      await expect(page.locator('.edit-message.success')).toBeVisible();
    }
  });

  test('削除セクションが表示される', async ({ page }) => {
    await page.goto(`/player_edit?id=${testPlayerId}`);
    await expect(page.locator('.edit-danger-section')).toBeVisible();
    await expect(page.locator('.edit-danger-header')).toContainText('選手の削除');
  });

  test('選手を削除できる', async ({ page }) => {
    await page.goto(`/player_edit?id=${testPlayerId}`);
    const deleteBtn = page.locator('button.btn-delete');

    if (await deleteBtn.isVisible()) {
      page.on('dialog', (dialog) => dialog.accept());
      await deleteBtn.click();
      await page.waitForURL(/\/players/);

      // フラッシュメッセージ
      await expect(page.locator('.players-message')).toContainText('削除しました');

      // 削除後は404
      const response = await page.goto(`/player?id=${testPlayerId}`);
      expect(response?.status()).toBe(404);
    }
  });
});
