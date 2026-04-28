import { test, expect } from '../helpers/fixtures';

test.describe('卓一覧ページ', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/tables');
  });

  test('ページが正しく表示される', async ({ page }) => {
    await expect.soft(page).toHaveTitle(/卓一覧/);
    await expect(page.locator('.page-hero-badge')).toContainText('TABLES');
    await expect(page.locator('.page-hero-title')).toContainText('卓一覧');
    await expect(page.locator('.tables-count')).toBeVisible();
  });

  test('卓カードが表示される', async ({ page }) => {
    const cards = page.locator('.table-card');
    expect(await cards.count()).toBeGreaterThan(0);

    const firstCard = cards.first();
    await expect(firstCard.locator('.table-card-tname')).not.toBeEmpty();
    await expect(firstCard.locator('.table-card-status')).toBeVisible();
    await expect(firstCard.locator('.table-card-round')).toContainText('回戦');
  });

  test('卓カードのリンクが正しい', async ({ page }) => {
    const href = await page.locator('.table-card a[href^="table?id="]').first().getAttribute('href');
    expect(href).toMatch(/^table\?id=\d+$/);
  });

  test('大会一覧へのリンクがある', async ({ page }) => {
    await expect(page.locator('.tournaments-actions a[href="tournaments"]')).toBeVisible();
  });

  test.describe('フィルタ', () => {
    test('フィルタフォーム・検索ボックス・ステータスセレクトが表示される', async ({ page }) => {
      await expect(page.locator('#event-filter-form')).toBeVisible();
      await expect(page.locator('.tables-search-input')).toBeVisible();
      await expect(page.locator('.tables-status-select')).toBeVisible();
    });

    test('EventTypeチップが5種別表示される', async ({ page }) => {
      await expect(page.locator('.event-chip')).toHaveCount(5);
    });

    test('デフォルトはフィルタ未適用', async ({ page }) => {
      await expect(page.locator('.event-filter-form')).not.toHaveClass(/is-active/);
      await expect(page.locator('.event-chip.is-selected')).toHaveCount(0);
      await expect(page.locator('.event-filter-clear')).toHaveCount(0);
      await expect(page.locator('.filter-context')).toHaveCount(0);
    });

    test('チップクリックで適用ボタンが現れ、URLは即変わらない', async ({ page }) => {
      const before = page.url();
      await page.locator('.event-chip').first().click();
      await expect(page.locator('.event-filter-form')).toHaveClass(/has-pending/);
      await expect(page.locator('.event-filter-submit')).toBeVisible();
      expect(page.url()).toBe(before);
    });

    test('検索ワードを入力すると pending 状態になる', async ({ page }) => {
      await page.locator('.tables-search-input').fill('test');
      await expect(page.locator('.event-filter-submit')).toBeVisible();
      await expect(page.locator('.event-filter-submit')).toHaveAttribute('data-pending-count', '1');
    });

    test('ステータス変更で pending 状態になる', async ({ page }) => {
      await page.locator('.tables-status-select').selectOption('done');
      await expect(page.locator('.event-filter-submit')).toBeVisible();
    });

    test('ステータス絞込が反映される（done）', async ({ page }) => {
      await page.goto('/tables?status=done');
      await expect(page.locator('.event-filter-form')).toHaveClass(/is-active/);
      await expect(page.locator('.filter-context')).toBeVisible();
      await expect(page.locator('.tables-status-select')).toHaveValue('done');
      const cards = page.locator('.table-card');
      const count = await cards.count();
      if (count > 0) {
        await expect(cards.first().locator('.table-card-status')).toContainText('完了');
      }
    });

    test('クリアリンクで全絞込が解除される', async ({ page }) => {
      await page.goto('/tables?status=done&q=test');
      await expect(page.locator('.event-filter-clear')).toBeVisible();
      await page.locator('.event-filter-clear').click();
      await page.waitForURL((url) => url.pathname.endsWith('/tables') && url.search === '');
      expect(page.url()).not.toContain('status');
      expect(page.url()).not.toContain('q=');
    });

    test('無効な status は無視されてデフォルト扱い', async ({ page }) => {
      await page.goto('/tables?status=invalid');
      await expect(page.locator('.tables-status-select')).toHaveValue('all');
    });
  });

  test.describe('ページネーション', () => {
    test('総件数が0でなければ件数表記が表示される', async ({ page }) => {
      const text = await page.locator('.tables-count').textContent();
      expect(text).toMatch(/\d+\s*卓/);
    });

    test('ページ送り UI は件数次第で表示される', async ({ page }) => {
      const pagination = page.locator('.tables-pagination');
      const exists = await pagination.count();
      if (exists > 0) {
        await expect(pagination.locator('.tables-page-info')).toContainText('ページ');
      }
    });
  });
});
