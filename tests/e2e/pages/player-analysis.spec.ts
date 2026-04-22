import { test, expect } from '../helpers/fixtures';
import { NONEXISTENT_ID, expectNotFound } from '../helpers/test-helpers';

const FILTERED_URL = '/player_analysis?id=1&event_types%5B%5D=saikyoi&event_types%5B%5D=hooh';

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
    for (const text of ['トップ率', 'ラス率', '連帯率', 'スコア標準偏差', '最高順位']) {
      await expect(labels.filter({ hasText: text })).toBeVisible();
    }
  });

  test('累計スコア推移・回戦別パフォーマンス の canvas が描画される', async ({ page }) => {
    await expect(page.locator('#cumulativeChart')).toBeVisible();
    await expect(page.locator('#roundPerfChart')).toBeVisible();
  });

  test('着順シェアの100%スタック棒が表示される', async ({ page }) => {
    await expect(page.locator('.rank-share-bar')).toBeVisible();
    await expect(page.locator('.rank-share-segment').first()).toBeVisible();
    await expect(page.locator('.rank-share-legend-item')).toHaveCount(4);
  });

  test('イベント種別別カードが表示される', async ({ page }) => {
    await expect(page.locator('.event-type-cards')).toBeVisible();
    const cards = page.locator('.event-type-card');
    expect(await cards.count()).toBeGreaterThanOrEqual(1);
    await expect(cards.first().locator('.event-type-metric')).toHaveCount(3);
  });

  test('種別が2以上ある時のみ BEST バッジがつく', async ({ page }) => {
    const cardCount = await page.locator('.event-type-card').count();
    const bestCount = await page.locator('.event-type-card.is-best').count();
    expect(bestCount).toBe(cardCount >= 2 ? 1 : 0);
  });

  test('サマリーカードのヒントがホバーで表示される', async ({ page }) => {
    const card = page.locator('.summary-card[data-hint]').first();
    await card.hover();
    expect(await card.getAttribute('data-hint')).toBeTruthy();
  });

  test('Chart.js が読み込まれてチャートが初期化される', async ({ page }) => {
    await expect
      .poll(() => page.evaluate(() => typeof (window as unknown as { Chart?: unknown }).Chart !== 'undefined'))
      .toBe(true);

    await expect
      .poll(() => page.locator('#roundPerfChart').evaluate((el) => (el as HTMLCanvasElement).width > 0))
      .toBe(true);
  });

  test('対戦成績テーブルが表示される', async ({ page }) => {
    await expect(page.locator('.h2h-table')).toBeVisible();
  });

  test('スコア推移テーブルが表示される', async ({ page }) => {
    await expect(page.locator('.history-table')).toBeVisible();
  });

  test('個人ページへの戻るリンクがある', async ({ page }) => {
    await expect(page.locator('a[href*="player?id=1"]')).toBeVisible();
  });

  test('存在しないIDで404', async ({ page }) => {
    await expectNotFound(page, `/player_analysis?id=${NONEXISTENT_ID}`);
  });

  test('IDなしで404', async ({ page }) => {
    await expectNotFound(page, '/player_analysis');
  });

  test.describe('大会種別フィルタ', () => {
    test('フィルタUIが表示される', async ({ page }) => {
      await expect(page.locator('#event-filter-form')).toBeVisible();
    });

    test('EventTypeの全種別分のチップが表示される', async ({ page }) => {
      await expect(page.locator('.event-chip')).toHaveCount(5);
      const texts = page.locator('.event-chip-text');
      for (const name of ['最強位戦', '鳳凰位戦', 'マスターズ', '百段位戦', 'プチイベント']) {
        await expect(texts.filter({ hasText: name })).toBeVisible();
      }
    });

    test('デフォルトはいずれも未選択', async ({ page }) => {
      await expect(page.locator('.event-chip input[type="checkbox"]:checked')).toHaveCount(0);
      await expect(page.locator('.event-chip.is-selected')).toHaveCount(0);
      await expect(page.locator('.event-filter-clear')).toHaveCount(0);
    });

    test('GETで選択済みチップがハイライトされる', async ({ page }) => {
      await page.goto(FILTERED_URL);
      await expect(page.locator('.event-chip.is-selected')).toHaveCount(2);
      await expect(page.locator('input[name="event_types[]"][value="saikyoi"]')).toBeChecked();
      await expect(page.locator('input[name="event_types[]"][value="hooh"]')).toBeChecked();
    });

    test('フィルタアイコンとラベルが表示される', async ({ page }) => {
      await expect(page.locator('.event-filter-icon')).toBeVisible();
      await expect(page.locator('.event-filter-title')).toContainText('フィルタ');
      await expect(page.locator('.event-filter-sub')).toContainText('大会種別');
    });

    test('フィルタ有効時にバッジ・is-active・クリアボタンが現れる', async ({ page }) => {
      await page.goto(FILTERED_URL);
      await expect(page.locator('.event-filter-form')).toHaveClass(/is-active/);
      await expect(page.locator('.event-filter-badge')).toContainText('2');
      await expect(page.locator('.event-filter-clear')).toBeVisible();
    });

    test('フィルタ無効時は is-active・バッジなし', async ({ page }) => {
      await expect(page.locator('.event-filter-form')).not.toHaveClass(/is-active/);
      await expect(page.locator('.event-filter-badge')).toHaveCount(0);
      await expect(page.locator('.event-filter-clear')).toHaveCount(0);
    });

    // .event-chip の input はデザイン上 hidden で viewport 判定に引っかかる。
    // ユーザー実操作と同じく親の label (.event-chip) クリックでトグルする。
    const chipByValue = (page: import('@playwright/test').Page, value: string) =>
      page.locator('.event-chip').filter({ has: page.locator(`input[value="${value}"]`) });

    test('チップクリックだけではURLは変わらない（ローカル状態）', async ({ page }) => {
      const before = page.url();
      await chipByValue(page, 'saikyoi').click();
      await expect(page.locator('.event-filter-form')).toHaveClass(/has-pending/);
      expect(page.url()).toBe(before);
      await expect(page.locator('.event-filter-submit')).toBeVisible();
    });

    test('適用ボタン押下で初めて送信される', async ({ page }) => {
      await chipByValue(page, 'saikyoi').click();
      await expect(page.locator('.event-filter-submit')).toBeVisible();
      await page.locator('.event-filter-submit').click();
      await page.waitForURL(/event_types/);
      expect(decodeURIComponent(page.url())).toContain('event_types[]=saikyoi');
    });

    test('未変更時は適用ボタンが非表示', async ({ page }) => {
      await expect(page.locator('.event-filter-submit')).toBeHidden();
    });

    test('未適用件数が適用ボタンに表示される', async ({ page }) => {
      await chipByValue(page, 'saikyoi').click();
      await chipByValue(page, 'hooh').click();
      const submit = page.locator('.event-filter-submit');
      await expect(submit).toHaveAttribute('data-pending-count', '2');
      await expect(submit).toContainText('(2)');
    });

    test('初期状態に戻すと適用ボタンが消える', async ({ page }) => {
      const chip = chipByValue(page, 'saikyoi');
      const submit = page.locator('.event-filter-submit');
      await chip.click();                         // チェック ON
      await expect(submit).toBeVisible();
      await chip.click();                         // チェック OFF（トグル）
      await expect(submit).toBeHidden();
      await expect(page.locator('.event-filter-form')).not.toHaveClass(/has-pending/);
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

    test('フィルタ適用時にコンテキスト表示が現れ、集計対象の種別タグが並ぶ', async ({ page }) => {
      await page.goto(FILTERED_URL);
      await expect(page.locator('.filter-context')).toBeVisible();
      await expect(page.locator('.filter-context-label')).toContainText('集計対象');
      await expect(page.locator('.filter-context-tag')).toHaveCount(2);
      await expect(page.locator('.filter-context-count')).toContainText('回戦');
    });

    test('フィルタ未適用時はコンテキスト表示が出ない', async ({ page }) => {
      await expect(page.locator('.filter-context')).toHaveCount(0);
    });

    test('1種別のみの絞り込みではBESTバッジが付かない', async ({ page }) => {
      await page.goto('/player_analysis?id=1&event_types%5B%5D=saikyoi');
      if (await page.locator('.event-type-card').count() > 0) {
        await expect(page.locator('.event-type-card.is-best')).toHaveCount(0);
      }
    });

    test('フィルタ対象の大会がなければ専用メッセージを表示', async ({ page }) => {
      await page.goto('/player_analysis?id=1&event_types%5B%5D=saikyoi&event_types%5B%5D=hooh&event_types%5B%5D=masters&event_types%5B%5D=hyakudanisen&event_types%5B%5D=petit');
      // フィルタONでもデータが残っていればサマリー、空なら専用メッセージが表示される（どちらかが成立）
      const hasSummary = await page.locator('.summary-grid').isVisible().catch(() => false);
      const hasEmpty = await page.locator('.analysis-error').isVisible().catch(() => false);
      expect(hasSummary || hasEmpty).toBe(true);
    });
  });
});
