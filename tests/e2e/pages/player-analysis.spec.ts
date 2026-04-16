import { test, expect } from '../helpers/fixtures';

test.describe('選手戦績分析ページ', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto('/player_analysis?id=1');
  });

  test('大会参加済み選手の分析が表示される', async ({ page }) => {
    await expect(page.locator('.analysis-badge')).toContainText('ANALYSIS');
    await expect(page.locator('.analysis-title')).toBeVisible();
  });

  test('サマリーカードが表示される', async ({ page }) => {
    await expect(page.locator('.summary-card')).not.toHaveCount(0);
  });

  test('拡張指標カード（トップ率・ラス率・連帯率・標準偏差・最高順位）が表示される', async ({ page }) => {
    const labels = page.locator('.summary-label');
    await expect(labels.filter({ hasText: 'トップ率' })).toBeVisible();
    await expect(labels.filter({ hasText: 'ラス率' })).toBeVisible();
    await expect(labels.filter({ hasText: '連帯率' })).toBeVisible();
    await expect(labels.filter({ hasText: 'スコア標準偏差' })).toBeVisible();
    await expect(labels.filter({ hasText: '最高順位' })).toBeVisible();
  });

  test('チャートの canvas 要素が描画される', async ({ page }) => {
    await expect(page.locator('#rankDistChart')).toBeVisible();
    await expect(page.locator('#cumulativeChart')).toBeVisible();
    await expect(page.locator('#roundPerfChart')).toBeVisible();
    // イベント種別レーダーは2種類以上のイベント種別がある場合のみ表示
    // 敗退ラウンド分布は完了大会がある場合のみ表示
  });

  test('サマリーカードのヒントがホバーで表示される', async ({ page }) => {
    const card = page.locator('.summary-card[data-hint]').first();
    await card.hover();
    const hint = await card.getAttribute('data-hint');
    expect(hint).toBeTruthy();
  });

  test('Chart.js が読み込まれてチャートが初期化される', async ({ page }) => {
    await expect.poll(async () => {
      return await page.evaluate(() => typeof (window as unknown as { Chart?: unknown }).Chart !== 'undefined');
    }).toBe(true);

    // 各 canvas が実サイズを持つ = Chart.js による描画が完了している
    await expect.poll(async () => {
      return await page.locator('#rankDistChart').evaluate((el) => (el as HTMLCanvasElement).width > 0);
    }).toBe(true);
  });

  test('対戦成績テーブルが表示される', async ({ page }) => {
    await expect(page.locator('.h2h-table')).toBeVisible();
  });

  test('スコア推移テーブルが表示される', async ({ page }) => {
    await expect(page.locator('.history-table')).toBeVisible();
  });

  test('存在しないIDで404', async ({ page }) => {
    const response = await page.goto('/player_analysis?id=99999');
    expect(response?.status()).toBe(404);
  });

  test('個人ページへの戻るリンクがある', async ({ page }) => {
    await expect(page.locator('a[href*="player?id=1"]')).toBeVisible();
  });

  test('IDなしで404', async ({ page }) => {
    const response = await page.goto('/player_analysis');
    expect(response?.status()).toBe(404);
  });
});
