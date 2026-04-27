import { test, expect } from '../helpers/fixtures';
import { TEST_PREFIX, bypassRequired } from '../helpers/test-helpers';

test.describe.configure({ mode: 'serial' });
test.describe('大会新規作成', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/tournament_new');
  });

  test('フォームが正しく表示される', async ({ page }) => {
    await expect(page.locator('.edit-badge')).toContainText('NEW TOURNAMENT');
    await expect(page.locator('input[name="event_type"]')).toHaveCount(5);
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('input[name="player_mode"]')).toHaveCount(2);
    await expect(page.locator('input[name="round_type"]')).toHaveCount(3);
    await expect(page.locator('select[name="thinking_time"]')).toBeVisible();
    await expect(page.locator('input[name="starting_points"]')).toBeVisible();
    await expect(page.locator('input[name="return_points"]')).toBeVisible();
    await expect(page.locator('input[name="red_dora"]')).toHaveCount(3);
    await expect(page.locator('input[name="open_tanyao"]')).toHaveCount(2);
    await expect(page.locator('input[name="han_restriction"]')).toHaveCount(3);
    await expect(page.locator('.player-select-option')).not.toHaveCount(0);
  });

  test('大会名未入力でバリデーションエラー', async ({ page }) => {
    await bypassRequired(page);
    await page.fill('input[name="name"]', '');
    await page.click('button.btn-save');
    await expect(page.locator('.edit-message.error')).toContainText('大会名を入力');
  });

  test('全選択・全解除ボタンが動作する', async ({ page }) => {
    const checkboxes = page.locator('.player-select-option input[type="checkbox"]');
    const checkedBoxes = page.locator('.player-select-option input[type="checkbox"]:checked');
    const total = await checkboxes.count();

    await page.click('#btn-select-all');
    expect(await checkedBoxes.count()).toBe(total);
    await expect(page.locator('#selected-count')).toContainText(`${total}人選択中`);

    await page.click('#btn-deselect-all');
    expect(await checkedBoxes.count()).toBe(0);
    await expect(page.locator('#selected-count')).toContainText('0人選択中');
  });

  test('選手未選択でも大会を作成できる', async ({ page }) => {
    await page.fill('input[name="name"]', `${TEST_PREFIX}大会_${Date.now()}`);
    await page.click('button.btn-save');
    await page.waitForURL(/\/tournament\?id=\d+/);
  });

  test('選手を選択して大会を作成できる', async ({ page }) => {
    await page.fill('input[name="name"]', `${TEST_PREFIX}大会_${Date.now()}`);
    await page.locator('.player-select-option input[type="checkbox"]').first().check({ force: true });
    await page.click('button.btn-save');
    await page.waitForURL(/\/tournament\?id=\d+/);
  });

  test('バリデーションエラー後に入力値が保持される', async ({ page }) => {
    await bypassRequired(page);

    await page.fill('input[name="name"]', `${TEST_PREFIX}persist_test`);
    await page.locator('input[name="player_mode"][value="3"]').check({ force: true });
    await page.locator('input[name="round_type"][value="tonpu"]').check({ force: true });
    await page.fill('input[name="starting_points"]', '35000');
    await page.fill('input[name="return_points"]', '40000');
    // 大会名を空にしてサーバーサイドバリデーションを発火
    await page.fill('input[name="name"]', '');
    await page.click('button.btn-save');

    await expect(page.locator('.edit-message.error')).toBeVisible();
    // name 以外の入力値が保持されている
    await expect(page.locator('input[name="player_mode"][value="3"]')).toBeChecked();
    await expect(page.locator('input[name="round_type"][value="tonpu"]')).toBeChecked();
    await expect(page.locator('input[name="starting_points"]')).toHaveValue('35000');
    await expect(page.locator('input[name="return_points"]')).toHaveValue('40000');
  });
});
