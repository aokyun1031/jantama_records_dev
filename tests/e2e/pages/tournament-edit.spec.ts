import { test, expect } from '../helpers/fixtures';
import { TEST_PREFIX, createOptimizedPage } from '../helpers/test-helpers';

test.describe.configure({ mode: 'serial' });
test.describe('大会情報編集', () => {
  let tournamentId: number;

  test.beforeAll(async ({ browser }) => {
    const page = await createOptimizedPage(browser);
    const name = `${TEST_PREFIX}edit_tournament_${Date.now()}`;
    await page.goto('/tournament_new');
    await page.fill('input[name="name"]', name);
    await page.click('button.btn-save');
    await page.waitForURL(/\/tournaments(\?.*)?$/, { waitUntil: 'domcontentloaded' });

    // 作成した大会のIDを取得
    await page.goto('/tournaments');
    const card = page.locator('.tournament-card', { hasText: name });
    const link = card.locator('a.tournament-link', { hasText: '管理ページ' });
    const href = await link.getAttribute('href');
    tournamentId = parseInt(href!.match(/id=(\d+)/)![1], 10);
    await page.close();
  });

  test('編集フォームが既存データで表示される', async ({ page }) => {
    await page.goto(`/tournament_edit?id=${tournamentId}`);
    await expect(page.locator('.edit-badge')).toContainText('EDIT TOURNAMENT');
    await expect(page.locator('input[name="name"]')).not.toHaveValue('');
    await expect(page.locator('input[name="event_type"]')).toHaveCount(5);
    await expect(page.locator('input[name="player_mode"]')).toHaveCount(2);
    await expect(page.locator('input[name="round_type"]')).toHaveCount(3);
  });

  test('大会名を更新できる', async ({ page }) => {
    const newName = `${TEST_PREFIX}edited_${Date.now()}`;
    await page.goto(`/tournament_edit?id=${tournamentId}`);
    await page.fill('input[name="name"]', newName);
    await page.click('button.btn-save');
    await page.waitForURL(/tournament_edit\?id=\d+&saved=1/, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.edit-message.success')).toContainText('保存しました');
    await expect(page.locator('input[name="name"]')).toHaveValue(newName);
  });

  test('大会名未入力でバリデーションエラー', async ({ page }) => {
    await page.goto(`/tournament_edit?id=${tournamentId}`);
    await page.evaluate(() => {
      document.querySelectorAll('input[required]').forEach(el => el.removeAttribute('required'));
    });
    await page.fill('input[name="name"]', '');
    await page.click('button.btn-save');
    await expect(page.locator('.edit-message.error')).toContainText('大会名を入力');
  });

  test('大会詳細ページに削除セクションが表示される', async ({ page }) => {
    await page.goto(`/tournament?id=${tournamentId}`);
    await expect(page.locator('.td-delete')).toBeVisible();
    await expect(page.locator('.td-btn-delete')).toBeVisible();
  });

  test('存在しない大会IDで404', async ({ page }) => {
    const response = await page.goto('/tournament_edit?id=999999');
    expect(response?.status()).toBe(404);
  });

  test('IDなしで404', async ({ page }) => {
    const response = await page.goto('/tournament_edit');
    expect(response?.status()).toBe(404);
  });

  test('完了済み大会は削除ボタンが非表示', async ({ page }) => {
    // 完了済み大会（id=1）の詳細ページで削除セクションが表示されないことを確認
    await page.goto('/tournament?id=1');
    await expect(page.locator('.td-delete')).not.toBeVisible();
  });
});
