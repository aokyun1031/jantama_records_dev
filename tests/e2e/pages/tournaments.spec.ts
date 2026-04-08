import { test, expect } from '../helpers/fixtures';

test.describe('大会一覧', () => {

  test('ページが正しく表示される', async ({ page }) => {
    await page.goto('/tournaments');
    await expect(page.locator('.tournaments-badge')).toContainText('TOURNAMENTS');
    await expect(page.locator('.tournaments-title')).toContainText('大会一覧');
    await expect(page.locator('.tournaments-count')).toBeVisible();
  });

  test('大会カードが表示される', async ({ page }) => {
    await page.goto('/tournaments');
    const cards = page.locator('.tournament-card');
    const count = await cards.count();
    expect(count).toBeGreaterThan(0);

    // 最初のカードに名前・ステータスが表示されている
    const firstCard = cards.first();
    await expect(firstCard.locator('.tournament-name')).not.toBeEmpty();
    await expect(firstCard.locator('.tournament-status')).toBeVisible();
  });

  test('トップページへのリンクがある', async ({ page }) => {
    await page.goto('/tournaments');
    await expect(page.locator('.tournaments-actions a[href="/"]')).toBeVisible();
  });

  test('大会作成ページへのリンクがある', async ({ page }) => {
    await page.goto('/tournaments');
    await expect(page.locator('.tournaments-actions a[href="tournament_new"]')).toBeVisible();
  });
});
