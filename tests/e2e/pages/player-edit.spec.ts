import { test, expect } from '../helpers/fixtures';
import {
  TEST_PREFIX,
  NONEXISTENT_ID,
  bypassRequired,
  createOptimizedPage,
  createTestPlayer,
  expectNotFound,
} from '../helpers/test-helpers';

test.describe.configure({ mode: 'serial' });
test.describe('選手編集・削除', () => {
  let testPlayerId: number;
  const testName = `${TEST_PREFIX}edit_${Date.now()}`;

  test.beforeAll(async ({ browser }, testInfo) => {
    testInfo.setTimeout(60000);
    const page = await createOptimizedPage(browser);
    testPlayerId = await createTestPlayer(page, testName, '編集前');
    await page.close();
  });

  const gotoEdit = (page: import('@playwright/test').Page) =>
    page.goto(`/player_edit?id=${testPlayerId}`);

  test('編集フォームが表示される', async ({ page }) => {
    await gotoEdit(page);
    await expect(page.locator('.edit-badge')).toContainText('EDIT');
    await expect(page.locator('.edit-title')).toContainText(testName);
    await expect(page.locator('input[name="nickname"]')).toHaveValue('編集前');
  });

  test('CSRFトークンが存在する', async ({ page }) => {
    await gotoEdit(page);
    const token = await page.locator('input[name="csrf_token"]').first().inputValue();
    expect(token).toBeTruthy();
    expect(token).toHaveLength(64); // bin2hex(random_bytes(32))
  });

  test('呼称を変更して保存できる', async ({ page }) => {
    await gotoEdit(page);
    await page.fill('input[name="nickname"]', '編集後');
    await page.click('button.btn-save');

    await expect(page).toHaveURL(/saved=1/);
    await expect(page.locator('.edit-message.success')).toBeVisible();
    await expect(page.locator('input[name="nickname"]')).toHaveValue('編集後');
  });

  test('空の呼称で保存するとエラー', async ({ page }) => {
    await gotoEdit(page);
    await bypassRequired(page);
    await page.fill('input[name="nickname"]', '');
    await page.click('button.btn-save');
    await expect(page.locator('.edit-message.error')).toContainText('呼称を入力');
  });

  test('キャラクターを変更できる', async ({ page }) => {
    await gotoEdit(page);
    await page.locator('.chara-option').nth(1).click();
    await page.click('button.btn-save');
    await expect(page).toHaveURL(/saved=1/);
    await expect(page.locator('.edit-message.success')).toBeVisible();
  });

  test('削除セクションが表示される', async ({ page }) => {
    await gotoEdit(page);
    await expect(page.locator('.edit-danger-section')).toBeVisible();
    await expect(page.locator('.edit-danger-header')).toContainText('選手の削除');
  });

  test('選手を削除できる', async ({ page }) => {
    await gotoEdit(page);
    page.once('dialog', (dialog) => dialog.accept());
    await page.locator('button.btn-delete').click();
    await page.waitForURL(/\/players/);

    await expect(page.locator('.edit-message.success')).toContainText('削除しました');
    await expectNotFound(page, `/player?id=${testPlayerId}`);
  });

  test('存在しないIDで404', async ({ page }) => {
    await expectNotFound(page, `/player_edit?id=${NONEXISTENT_ID}`);
  });

  test('IDなしで404', async ({ page }) => {
    await expectNotFound(page, '/player_edit');
  });
});

// シード id=1 は大会参加中のため、読み取り専用の検証として別 describe で実施
test.describe('選手編集 - 大会参加中の選手', () => {
  test('削除セクションに制約メッセージが表示され削除ボタンが非表示', async ({ page }) => {
    await page.goto('/player_edit?id=1');
    await expect(page.locator('.edit-danger-desc')).toContainText('大会に参加しているため削除できません');
    await expect(page.locator('button.btn-delete')).toHaveCount(0);
  });
});
