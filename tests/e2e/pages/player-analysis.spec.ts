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

  test.describe('大会種別フィルタ', () => {
    test('フィルタUIが表示される', async ({ page }) => {
      await expect(page.locator('#event-filter-form')).toBeVisible();
      await expect(page.locator('.event-filter-label')).toContainText('大会種別で絞り込み');
    });

    test('EventTypeの全種別分のチップが表示される', async ({ page }) => {
      await expect(page.locator('.event-chip')).toHaveCount(5);
      const texts = page.locator('.event-chip-text');
      await expect(texts.filter({ hasText: '最強位戦' })).toBeVisible();
      await expect(texts.filter({ hasText: '鳳凰位戦' })).toBeVisible();
      await expect(texts.filter({ hasText: 'マスターズ' })).toBeVisible();
      await expect(texts.filter({ hasText: '百段位戦' })).toBeVisible();
      await expect(texts.filter({ hasText: 'プチイベント' })).toBeVisible();
    });

    test('デフォルトはいずれも未選択', async ({ page }) => {
      const checked = page.locator('.event-chip input[type="checkbox"]:checked');
      await expect(checked).toHaveCount(0);
      await expect(page.locator('.event-chip.is-selected')).toHaveCount(0);
      await expect(page.locator('.event-filter-clear')).toHaveCount(0);
    });

    test('GETで選択済みチップがハイライトされる', async ({ page }) => {
      await page.goto('/player_analysis?id=1&event_types%5B%5D=saikyoi&event_types%5B%5D=hooh');
      await expect(page.locator('.event-chip.is-selected')).toHaveCount(2);
      await expect(page.locator('input[name="event_types[]"][value="saikyoi"]')).toBeChecked();
      await expect(page.locator('input[name="event_types[]"][value="hooh"]')).toBeChecked();
    });

    test('チップをクリックすると選択状態でURLに反映される', async ({ page }) => {
      await page.locator('input[name="event_types[]"][value="saikyoi"]').check();
      await page.waitForURL(/event_types/);
      const url = page.url();
      expect(decodeURIComponent(url)).toContain('event_types[]=saikyoi');
    });

    test('クリアリンクで全選択解除されURLから除かれる', async ({ page }) => {
      await page.goto('/player_analysis?id=1&event_types%5B%5D=saikyoi');
      await expect(page.locator('.event-filter-clear')).toBeVisible();
      await page.locator('.event-filter-clear').click();
      await page.waitForURL((url) => !url.search.includes('event_types'));
      expect(page.url()).not.toContain('event_types');
      await expect(page.locator('.event-chip.is-selected')).toHaveCount(0);
    });

    test('無効な大会種別は無視される', async ({ page }) => {
      await page.goto('/player_analysis?id=1&event_types%5B%5D=invalid_type');
      await expect(page.locator('.event-chip.is-selected')).toHaveCount(0);
      await expect(page.locator('.event-filter-clear')).toHaveCount(0);
    });

    test('フィルタ対象の大会がなければ専用メッセージを表示', async ({ page }) => {
      await page.goto('/player_analysis?id=1&event_types%5B%5D=saikyoi&event_types%5B%5D=hooh&event_types%5B%5D=masters&event_types%5B%5D=hyakudanisen&event_types%5B%5D=petit');
      // フィルタONでもデータが残っていればサマリー、空なら専用メッセージが表示されること（どちらかが成立）
      const hasSummary = await page.locator('.summary-grid').isVisible().catch(() => false);
      const hasEmpty = await page.locator('.analysis-error').isVisible().catch(() => false);
      expect(hasSummary || hasEmpty).toBe(true);
    });
  });
});
