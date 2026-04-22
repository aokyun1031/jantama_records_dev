import { test, expect } from '../helpers/fixtures';
import { expectStatus } from '../helpers/test-helpers';

test.describe('クリーンURL', () => {
  test('.php なしでアクセスできる', async ({ page }) => {
    await expectStatus(page, '/players', 200);
    await expect(page).toHaveTitle(/選手一覧/);
  });

  test('.php 付きでアクセスすると拡張子なしにリダイレクトされる', async ({ page }) => {
    await page.goto('/players.php');
    expect(page.url()).not.toContain('.php');
    expect(page.url()).toContain('/players');
  });

  test('クエリパラメータ付きでも動作する', async ({ page }) => {
    await expectStatus(page, '/player?id=1', 200);
  });
});
