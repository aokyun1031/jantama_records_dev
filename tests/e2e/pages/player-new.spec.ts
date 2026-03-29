import { test, expect } from '@playwright/test';
import { TEST_PREFIX, createTestPlayer, deleteTestPlayer, cleanupTestPlayers } from '../helpers/test-helpers';

test.describe('選手新規登録', () => {
  let createdIds: number[] = [];

  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage();
    await cleanupTestPlayers(page);
    await page.close();
  });

  test('フォームが正しく表示される', async ({ page }) => {
    await page.goto('/player_new');
    await expect(page.locator('.edit-badge')).toContainText('NEW PLAYER');
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('input[name="nickname"]')).toBeVisible();
    await expect(page.locator('input[name="csrf_token"]')).toBeHidden();
    await expect(page.locator('.chara-option')).not.toHaveCount(0);
  });

  test('空のフォーム送信でバリデーションエラー', async ({ page }) => {
    await page.goto('/player_new');
    // name を空のまま送信（HTML5 required でブロックされるのでJSで回避）
    await page.evaluate(() => {
      (document.querySelector('input[name="name"]') as HTMLInputElement).removeAttribute('required');
      (document.querySelector('input[name="nickname"]') as HTMLInputElement).removeAttribute('required');
    });
    await page.fill('input[name="name"]', '');
    await page.click('button.btn-save');
    await expect(page.locator('.edit-message.error')).toBeVisible();
  });

  test('選手を正常に登録できる', async ({ page }) => {
    const name = `${TEST_PREFIX}${Date.now()}`;
    const id = await createTestPlayer(page, name, 'テスト呼称');
    createdIds.push(id);

    expect(id).toBeGreaterThan(0);
    await expect(page.locator('.player-title')).toContainText(name);
    // フラッシュメッセージ
    await expect(page.locator('.player-message')).toContainText('登録しました');
  });

  test('重複名で登録するとエラー', async ({ page }) => {
    const name = `${TEST_PREFIX}dup_${Date.now()}`;
    const id = await createTestPlayer(page, name, '1回目');
    createdIds.push(id);

    // 同じ名前で再登録
    await page.goto('/player_new');
    await page.fill('input[name="name"]', name);
    await page.fill('input[name="nickname"]', '2回目');
    await page.locator('.chara-option').first().click();
    await page.click('button.btn-save');

    await expect(page.locator('.edit-message.error')).toContainText('既に使用されています');
  });

  test('キャラクター未選択でエラー', async ({ page }) => {
    await page.goto('/player_new', { waitUntil: 'networkidle' });
    await page.fill('input[name="name"]', `${TEST_PREFIX}nochar_${Date.now()}`);
    await page.fill('input[name="nickname"]', 'テスト');
    // キャラクター未選択のまま送信
    await page.click('button.btn-save');
    await expect(page.locator('.edit-message.error')).toContainText('キャラクターを選択');
  });
});
