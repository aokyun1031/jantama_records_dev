import { test, expect } from '../helpers/fixtures';
import { TEST_PREFIX, createTestPlayer, bypassRequired } from '../helpers/test-helpers';

test.describe.configure({ mode: 'serial' });
test.describe('選手新規登録', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/player_new');
  });

  test('フォームが正しく表示される', async ({ page }) => {
    await expect(page.locator('.edit-badge')).toContainText('NEW PLAYER');
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('input[name="nickname"]')).toBeVisible();
    await expect(page.locator('input[name="csrf_token"]')).toBeHidden();
    await expect(page.locator('.chara-option')).not.toHaveCount(0);
  });

  test('空のフォーム送信でバリデーションエラー', async ({ page }) => {
    await bypassRequired(page);
    await page.fill('input[name="name"]', '');
    await page.click('button.btn-save');
    await expect(page.locator('.edit-message.error')).toBeVisible();
  });

  test('選手を正常に登録できる', async ({ page }) => {
    const name = `${TEST_PREFIX}${Date.now()}`;
    const id = await createTestPlayer(page, name, 'テスト呼称');

    expect(id).toBeGreaterThan(0);
    await expect(page.locator('.player-title')).toContainText(name);
    await expect(page.locator('.edit-message.success')).toContainText('登録しました');
  });

  test('重複名で登録するとエラー', async ({ page }) => {
    const name = `${TEST_PREFIX}dup_${Date.now()}`;
    await createTestPlayer(page, name, '1回目');

    // 同じ名前で再登録
    await page.goto('/player_new');
    await page.fill('input[name="name"]', name);
    await page.fill('input[name="nickname"]', '2回目');
    await page.locator('.chara-option').first().click();
    await page.click('button.btn-save');

    await expect(page.locator('.edit-message.error')).toContainText('既に使用されています');
  });

  test('キャラクター未選択でエラー', async ({ page }) => {
    await page.fill('input[name="name"]', `${TEST_PREFIX}nochar_${Date.now()}`);
    await page.fill('input[name="nickname"]', 'テスト');
    await page.click('button.btn-save');
    await expect(page.locator('.edit-message.error')).toContainText('キャラクターを選択');
  });
});
