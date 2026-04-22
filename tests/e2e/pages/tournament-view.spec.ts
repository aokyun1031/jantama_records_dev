import { test, expect } from '../helpers/fixtures';
import { NONEXISTENT_ID, expectNotFound } from '../helpers/test-helpers';

// tournament_view は preparing 状態では 404 になる。
// シードデータの大会ID=1（完了済み）を使用する。
const COMPLETED_TOURNAMENT_URL = '/tournament_view?id=1';

test.describe('大会ビュー（公開ページ）', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(COMPLETED_TOURNAMENT_URL);
  });

  test('完了済み大会のビューページが表示される', async ({ page }) => {
    await expect(page.locator('body')).toBeVisible();
  });

  test('大会名が表示される', async ({ page }) => {
    await expect(page.locator('h1, .tv-title')).not.toBeEmpty();
  });

  test('ラウンド情報が表示される', async ({ page }) => {
    await expect(page.locator('body')).toContainText(/回戦/);
  });

  test('完了済み大会で優勝者セクションが表示される', async ({ page }) => {
    await expect(page.locator('.champion-section')).toBeVisible();
  });

  test('総合ポイントセクションが表示される', async ({ page }) => {
    await expect(page.locator('#standings-section')).toBeVisible();
  });

  test('対戦結果タブが存在する', async ({ page }) => {
    await expect(page.locator('.tab-btn')).not.toHaveCount(0);
  });

  test('タブをクリックするとコンテンツが切り替わる', async ({ page }) => {
    const tabs = page.locator('.tab-btn[data-tab-index]');
    const count = await tabs.count();
    test.skip(count < 2, 'タブが1つしか無いためスキップ');

    await tabs.first().click();
    await expect(tabs.first()).toHaveClass(/active/);
    await expect(page.locator('.tab-content').first()).toHaveClass(/active/);

    const lastIdx = count - 1;
    await tabs.nth(lastIdx).click();
    await expect(tabs.nth(lastIdx)).toHaveClass(/active/);
    await expect(page.locator('.tab-content').nth(lastIdx)).toHaveClass(/active/);
  });

  test('大会一覧への戻るリンクがある', async ({ page }) => {
    await expect(page.locator('a.btn-cancel[href="tournaments"]')).toBeVisible();
  });

  test('準備中の大会は404', async ({ page }) => {
    // 準備中の大会は表示不可（tournament_view.php:8-10）。存在しない大きな ID でも同じく 404。
    await expectNotFound(page, `/tournament_view?id=${NONEXISTENT_ID}`);
  });

  test('IDなしで404', async ({ page }) => {
    await expectNotFound(page, '/tournament_view');
  });

  test.describe('トーナメントレコード', () => {
    test('大会最高得点カードが表示される', async ({ page }) => {
      const card = page.locator('.record-card').filter({ hasText: '大会最高得点' }).first();
      await expect(card).toBeVisible();
      await expect(card.locator('.record-card-num')).toBeVisible();
    });

    test('大会最高ポイントカードが表示される', async ({ page }) => {
      const card = page.locator('.record-card').filter({ hasText: '大会最高ポイント' }).first();
      await expect(card).toBeVisible();
      await expect(card.locator('.record-card-num')).toBeVisible();
    });

    test('最多トップカードが表示される', async ({ page }) => {
      const card = page.locator('.record-card').filter({ hasText: '最多トップ' });
      await expect(card).toBeVisible();
      await expect(card.locator('.record-card-unit')).toContainText('回');
    });

    test('各レコードカードに選手名が付随する', async ({ page }) => {
      expect(await page.locator('.record-card .record-card-player').count()).toBeGreaterThanOrEqual(1);
    });
  });
});
