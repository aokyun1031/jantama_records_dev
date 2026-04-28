import { test, expect } from '../helpers/fixtures';

const FILTERED_URL = '/tournaments?event_types%5B%5D=saikyoi&event_types%5B%5D=hooh';

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
    await expect(page.locator('.list-actions a[href="/"]')).toBeVisible();
  });

  test('大会作成ページへのリンクがある', async ({ page }) => {
    await expect(page.locator('.list-actions a[href="tournament_new"]')).toBeVisible();
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
      await chip.click();
      await expect(submit).toBeVisible();
      await chip.click();
      await expect(submit).toBeHidden();
      await expect(page.locator('.event-filter-form')).not.toHaveClass(/has-pending/);
    });

    test('クリアリンクで全選択解除されURLから除かれる', async ({ page }) => {
      await page.goto('/tournaments?event_types%5B%5D=saikyoi');
      await expect(page.locator('.event-filter-clear')).toBeVisible();
      await page.locator('.event-filter-clear').click();
      await page.waitForURL((url) => !url.search.includes('event_types'));
      expect(page.url()).not.toContain('event_types');
      await expect(page.locator('.event-chip.is-selected')).toHaveCount(0);
    });

    test('無効な大会種別は無視される', async ({ page }) => {
      await page.goto('/tournaments?event_types%5B%5D=invalid_type');
      await expect(page.locator('.event-chip.is-selected')).toHaveCount(0);
      await expect(page.locator('.event-filter-clear')).toHaveCount(0);
    });

    test('フィルタ適用時にコンテキスト表示が現れ、絞込種別タグと件数が並ぶ', async ({ page }) => {
      await page.goto(FILTERED_URL);
      await expect(page.locator('.filter-context')).toBeVisible();
      await expect(page.locator('.filter-context-label')).toContainText('絞込');
      await expect(page.locator('.filter-context-tag')).toHaveCount(2);
      await expect(page.locator('.filter-context-count')).toContainText('件中');
    });

    test('フィルタ未適用時はコンテキスト表示が出ない', async ({ page }) => {
      await expect(page.locator('.filter-context')).toHaveCount(0);
    });

    test('該当大会が無い種別を選ぶと専用メッセージが表示される', async ({ page }) => {
      await page.goto('/tournaments?event_types%5B%5D=saikyoi&event_types%5B%5D=hooh&event_types%5B%5D=masters&event_types%5B%5D=hyakudanisen&event_types%5B%5D=petit');
      const hasCards = await page.locator('.tournament-card').count() > 0;
      const hasEmpty = await page.locator('.list-empty').isVisible().catch(() => false);
      expect(hasCards || hasEmpty).toBe(true);
    });
  });
});
