import { type Page, expect } from '@playwright/test';

export const TEST_PREFIX = '__E2E_TEST_';

/**
 * テスト用プレイヤーをフォーム経由で作成し、IDを返す。
 */
export async function createTestPlayer(
  page: Page,
  name: string,
  nickname: string
): Promise<number> {
  await page.goto('/player_new');
  await page.fill('input[name="name"]', name);
  await page.fill('input[name="nickname"]', nickname);
  // 最初のキャラクターを選択（label要素をクリック）
  await page.locator('.chara-option').first().click();
  await page.click('button.btn-save');
  // リダイレクト先 /player?id=N からIDを取得
  await page.waitForURL(/\/player\?id=\d+/);
  const url = new URL(page.url());
  return parseInt(url.searchParams.get('id')!, 10);
}

/**
 * テスト用プレイヤーを削除する。大会参加済みの場合はスキップ。
 */
export async function deleteTestPlayer(page: Page, id: number): Promise<void> {
  await page.goto(`/player_edit?id=${id}`);
  const deleteBtn = page.locator('button.btn-delete');
  if (await deleteBtn.isVisible()) {
    page.on('dialog', (dialog) => dialog.accept());
    await deleteBtn.click();
    await page.waitForURL(/\/players/);
  }
}

/**
 * テスト用プレイヤーを一括クリーンアップする。
 */
export async function cleanupTestPlayers(page: Page): Promise<void> {
  await page.goto('/players');
  const cards = page.locator('.player-card');
  const count = await cards.count();
  const idsToDelete: number[] = [];

  for (let i = 0; i < count; i++) {
    const name = await cards.nth(i).locator('.player-name').textContent();
    if (name?.startsWith(TEST_PREFIX)) {
      const href = await cards.nth(i).getAttribute('href');
      const match = href?.match(/id=(\d+)/);
      if (match) idsToDelete.push(parseInt(match[1], 10));
    }
  }

  for (const id of idsToDelete) {
    await deleteTestPlayer(page, id);
  }
}

/**
 * ページのHTTPステータスコードを検証する。
 */
export async function expectStatus(page: Page, url: string, status: number): Promise<void> {
  const response = await page.goto(url);
  expect(response?.status()).toBe(status);
}
