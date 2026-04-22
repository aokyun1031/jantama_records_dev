import { test, expect } from '../helpers/fixtures';

test.describe('大会一覧', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/tournaments');
  });

  test('ページが正しく表示される', async ({ page }) => {
    await expect(page.locator('.page-hero-badge')).toContainText('TOURNAMENTS');
    await expect(page.locator('.page-hero-title')).toContainText('大会一覧');
    await expect(page.locator('.tournaments-count')).toBeVisible();
  });

  test('大会カードが表示される', async ({ page }) => {
    const cards = page.locator('.tournament-card');
    expect(await cards.count()).toBeGreaterThan(0);

    const firstCard = cards.first();
    await expect(firstCard.locator('.tournament-name')).not.toBeEmpty();
    await expect(firstCard.locator('.tournament-status')).toBeVisible();
  });

  test('トップページへのリンクがある', async ({ page }) => {
    await expect(page.locator('.tournaments-actions a[href="/"]')).toBeVisible();
  });

  test('大会作成ページへのリンクがある', async ({ page }) => {
    await expect(page.locator('.tournaments-actions a[href="tournament_new"]')).toBeVisible();
  });
});
