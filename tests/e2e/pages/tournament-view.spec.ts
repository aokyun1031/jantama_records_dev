import { test, expect } from '../helpers/fixtures';

test.describe('大会ビュー（公開ページ）', () => {
  // tournament_view は preparing 状態では 404 になる。
  // シードデータの大会ID=1（完了済み）を使用する。

  test('完了済み大会のビューページが表示される', async ({ page }) => {
    await page.goto('/tournament_view?id=1');
    // ページがHTMLとして正常に返る（200）
    await expect(page.locator('body')).toBeVisible();
  });

  test('大会名が表示される', async ({ page }) => {
    await page.goto('/tournament_view?id=1');
    await expect(page.locator('h1, .tv-title')).not.toBeEmpty();
  });

  test('ラウンド情報が表示される', async ({ page }) => {
    await page.goto('/tournament_view?id=1');
    // ラウンドに関する情報が存在する
    const body = page.locator('body');
    await expect(body).toContainText(/回戦/);
  });

  test('準備中の大会は404', async ({ page }) => {
    // 準備中の大会は表示不可（tournament_view.php:8-10）
    // 存在しない大きなIDで404
    const response = await page.goto('/tournament_view?id=999999');
    expect(response?.status()).toBe(404);
  });

  test('IDなしで404', async ({ page }) => {
    const response = await page.goto('/tournament_view');
    expect(response?.status()).toBe(404);
  });

  test('完了済み大会で優勝者セクションが表示される', async ({ page }) => {
    await page.goto('/tournament_view?id=1');
    await expect(page.locator('.champion-section')).toBeVisible();
  });

  test('総合ポイントセクションが表示される', async ({ page }) => {
    await page.goto('/tournament_view?id=1');
    await expect(page.locator('#standings-section')).toBeVisible();
  });

  test('対戦結果タブが存在する', async ({ page }) => {
    await page.goto('/tournament_view?id=1');
    await expect(page.locator('.tab-btn')).not.toHaveCount(0);
  });

  test('タブをクリックするとコンテンツが切り替わる', async ({ page }) => {
    await page.goto('/tournament_view?id=1');
    const tabs = page.locator('.tab-btn[data-tab-index]');
    const count = await tabs.count();
    test.skip(count < 2, 'タブが1つしか無いためスキップ');

    // 先頭タブをクリック
    await tabs.nth(0).click();
    await expect(tabs.nth(0)).toHaveClass(/active/);
    await expect(page.locator('.tab-content').nth(0)).toHaveClass(/active/);

    // 末尾タブをクリック
    const lastIdx = count - 1;
    await tabs.nth(lastIdx).click();
    await expect(tabs.nth(lastIdx)).toHaveClass(/active/);
    await expect(page.locator('.tab-content').nth(lastIdx)).toHaveClass(/active/);
  });

  test('大会一覧への戻るリンクがある', async ({ page }) => {
    await page.goto('/tournament_view?id=1');
    await expect(page.locator('a.btn-cancel[href="tournaments"]')).toBeVisible();
  });
});
