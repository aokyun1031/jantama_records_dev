import { test, expect } from '../helpers/fixtures';

test.describe('トップページ', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('ページが正常に表示される', async ({ page }) => {
    await expect(page).toHaveTitle(/雀魂部屋主催/);
  });

  test('ヒーローセクションが表示される', async ({ page }) => {
    await expect(page.locator('.lp3-hero')).toBeVisible();
    await expect(page.locator('.lp3-hero-title')).toBeVisible();
    await expect(page.locator('.lp3-hero-actions .lp3-btn')).not.toHaveCount(0);
  });

  test('チャンピオンセクションが表示される', async ({ page }) => {
    await expect.soft(page.locator('.lp3-champion-name').first()).toBeVisible();
    await expect.soft(page.locator('.lp3-champion-tournament').first()).toBeVisible();
  });

  test('統計情報が表示される', async ({ page }) => {
    // lp3-stat は開催/終了大会・登録選手・消化卓数などを複数タイル表示する
    expect(await page.locator('.lp3-stat').count()).toBeGreaterThan(0);
  });

  test('シリーズタイルが5つ表示される', async ({ page }) => {
    await expect(page.locator('.lp3-series-tile')).toHaveCount(5);
  });

  test('シリーズタイルは大会一覧の種別フィルタ付きへ遷移する', async ({ page }) => {
    const tiles = page.locator('.lp3-series-tile');
    for (let i = 0; i < await tiles.count(); i++) {
      const href = await tiles.nth(i).getAttribute('href');
      expect(href).toMatch(/^tournaments\?event_types(%5B%5D|\[\])=/);
    }
    await tiles.first().click();
    await expect(page).toHaveURL(/\/tournaments\?event_types/);
    await expect(page.locator('.event-chip.is-selected')).toHaveCount(1);
  });

  test('大会アーカイブが存在する', async ({ page }) => {
    await expect(page.locator('.lp3-archive-row').first()).toBeVisible();
  });

  test('選手一覧へのリンクが機能する', async ({ page }) => {
    await page.locator('a[href="players"]').first().click();
    await expect(page).toHaveURL(/\/players/);
  });

  test('大会一覧へのリンクが存在する', async ({ page }) => {
    await expect(page.locator('a[href="tournaments"]')).not.toHaveCount(0);
  });

  test('フッターに著作権表記がある', async ({ page }) => {
    const copyright = page.locator('.footer-copyright');
    await expect.soft(copyright).toContainText('Soul Games');
    await expect.soft(copyright).toContainText('Yostar');
  });

  test('スマホ幅でインタビューボタンが内容サイズ（幅100%にならない）', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/');
    const btn = page.locator('.lp3-spotlight .lp3-btn-primary').first();
    if (await btn.count() === 0) test.skip();
    const grid = page.locator('.lp3-spotlight-grid').first();
    const btnBox = await btn.boundingBox();
    const gridBox = await grid.boundingBox();
    expect(btnBox).not.toBeNull();
    expect(gridBox).not.toBeNull();
    expect(btnBox!.width).toBeLessThan(gridBox!.width * 0.85);
  });
});
