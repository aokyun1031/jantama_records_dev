import { test, expect } from '@playwright/test';

test.describe('テーマ切替', () => {
  test('テーマトグルが表示される', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('#theme-toggle')).toBeVisible();
  });

  test('トグルでテーマが切り替わる', async ({ page }) => {
    await page.goto('/');

    // 初期状態を確認
    const darkSheet = page.locator('#theme-dark');
    const initialDisabled = await darkSheet.evaluate((el: HTMLLinkElement) => el.disabled);

    // トグルクリック
    await page.click('#theme-toggle');
    const afterClick = await darkSheet.evaluate((el: HTMLLinkElement) => el.disabled);
    expect(afterClick).not.toBe(initialDisabled);

    // もう一度クリックで元に戻る
    await page.click('#theme-toggle');
    const afterSecondClick = await darkSheet.evaluate((el: HTMLLinkElement) => el.disabled);
    expect(afterSecondClick).toBe(initialDisabled);
  });

  test('テーマ設定がlocalStorageに保存される', async ({ page }) => {
    await page.goto('/');
    await page.click('#theme-toggle');

    const saved = await page.evaluate(() => localStorage.getItem('saikyo-theme'));
    expect(saved).toBeTruthy();
    expect(['dark', 'light']).toContain(saved);
  });

  test('ページリロード後もテーマが維持される', async ({ page }) => {
    await page.goto('/');

    // ダークに切替
    await page.evaluate(() => localStorage.setItem('saikyo-theme', 'dark'));
    await page.reload();

    const disabled = await page.locator('#theme-dark').evaluate((el: HTMLLinkElement) => el.disabled);
    expect(disabled).toBe(false); // dark theme enabled
  });
});
