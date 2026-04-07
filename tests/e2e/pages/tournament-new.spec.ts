import { test, expect } from '../helpers/fixtures';
import { TEST_PREFIX, deleteTestTournament } from '../helpers/test-helpers';

test.describe('大会新規作成', () => {
  const createdIds: number[] = [];

  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage();
    // 作成された大会IDを収集
    await page.goto('/tournaments');
    const cards = page.locator('.tournament-card');
    const count = await cards.count();
    for (let i = 0; i < count; i++) {
      const name = await cards.nth(i).locator('.tournament-name').textContent();
      if (name?.startsWith(TEST_PREFIX)) {
        const link = cards.nth(i).locator('a.tournament-link', { hasText: '管理ページ' });
        const href = await link.getAttribute('href');
        const match = href?.match(/id=(\d+)/);
        if (match) createdIds.push(parseInt(match[1], 10));
      }
    }
    for (const id of createdIds) {
      await deleteTestTournament(page, id);
    }
    await page.close();
  });

  test('フォームが正しく表示される', async ({ page }) => {
    await page.goto('/tournament_new');
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
    await page.goto('/tournament_new');
    await page.evaluate(() => {
      document.querySelectorAll('input[required], select[required]').forEach(el => el.removeAttribute('required'));
    });
    await page.fill('input[name="name"]', '');
    await page.click('button.btn-save');
    await expect(page.locator('.edit-message.error')).toContainText('大会名を入力');
  });

  test('全選択・全解除ボタンが動作する', async ({ page }) => {
    await page.goto('/tournament_new');
    await page.click('#btn-select-all');
    const total = await page.locator('.player-select-option input[type="checkbox"]').count();
    const checked = await page.locator('.player-select-option input[type="checkbox"]:checked').count();
    expect(checked).toBe(total);
    await expect(page.locator('#selected-count')).toContainText(`${total}人選択中`);

    await page.click('#btn-deselect-all');
    const checkedAfter = await page.locator('.player-select-option input[type="checkbox"]:checked').count();
    expect(checkedAfter).toBe(0);
    await expect(page.locator('#selected-count')).toContainText('0人選択中');
  });

  test('選手未選択でも大会を作成できる', async ({ page }) => {
    const tournamentName = `${TEST_PREFIX}大会_${Date.now()}`;
    await page.goto('/tournament_new');

    await page.fill('input[name="name"]', tournamentName);

    await page.click('button.btn-save');
    // 作成後 tournaments にリダイレクト
    await page.waitForURL(/\/tournaments(\?.*)?$/, { waitUntil: 'domcontentloaded' });
  });

  test('選手を選択して大会を作成できる', async ({ page }) => {
    const tournamentName = `${TEST_PREFIX}大会_${Date.now()}`;
    await page.goto('/tournament_new');

    await page.fill('input[name="name"]', tournamentName);

    // 最初の選手をチェック（hidden inputのためforce使用）
    const firstCheckbox = page.locator('.player-select-option input[type="checkbox"]').first();
    await firstCheckbox.check({ force: true });

    await page.click('button.btn-save');
    // 作成後 tournaments にリダイレクト
    await page.waitForURL(/\/tournaments(\?.*)?$/, { waitUntil: 'domcontentloaded' });
  });

  test('バリデーションエラー後に入力値が保持される', async ({ page }) => {
    await page.goto('/tournament_new');
    await page.evaluate(() => {
      document.querySelectorAll('input[required], select[required]').forEach(el => el.removeAttribute('required'));
    });

    const name = `${TEST_PREFIX}persist_test`;
    await page.fill('input[name="name"]', name);
    await page.locator('input[name="player_mode"][value="3"]').check({ force: true });
    await page.locator('input[name="round_type"][value="tonpu"]').check({ force: true });
    await page.fill('input[name="starting_points"]', '35000');
    await page.fill('input[name="return_points"]', '40000');
    // 大会名を空にしてサーバーサイドバリデーションを発火
    await page.fill('input[name="name"]', '');
    await page.click('button.btn-save');

    await expect(page.locator('.edit-message.error')).toBeVisible();
    // name以外の入力値が保持されている
    await expect(page.locator('input[name="player_mode"][value="3"]')).toBeChecked();
    await expect(page.locator('input[name="round_type"][value="tonpu"]')).toBeChecked();
    await expect(page.locator('input[name="starting_points"]')).toHaveValue('35000');
    await expect(page.locator('input[name="return_points"]')).toHaveValue('40000');
  });
});
