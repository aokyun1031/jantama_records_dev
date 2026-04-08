import { type Page, type Browser, expect } from '@playwright/test';

export const TEST_PREFIX = '__E2E_TEST_';

/**
 * beforeAll/afterAll 用のページを作成する。
 * browser.newPage() だと config の use 設定が適用されないため、
 * リソースブロック・waitUntil 等を手動で設定する。
 */
export async function createOptimizedPage(browser: Browser): Promise<Page> {
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(30000);
  await page.route(/fonts\.(googleapis|gstatic)\.com/, (route) => route.abort());
  await page.route(/\.(png|jpg|jpeg|webp|gif|svg|ico)(\?.*)?$/, (route) => route.abort());
  return page;
}

/**
 * テスト用プレイヤーをフォーム経由で作成し、IDを返す。
 */
export async function createTestPlayer(
  page: Page,
  name: string,
  nickname: string
): Promise<number> {
  await page.goto('/player_new', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="name"]', name);
  await page.fill('input[name="nickname"]', nickname);
  await page.locator('.chara-option').first().click();
  await page.click('button.btn-save');
  await page.waitForURL(/\/player\?id=\d+/, { waitUntil: 'domcontentloaded' });
  const url = new URL(page.url());
  return parseInt(url.searchParams.get('id')!, 10);
}

/**
 * テスト用プレイヤーを削除する。大会参加済みの場合はスキップ。
 */
async function deleteTestPlayer(page: Page, id: number): Promise<void> {
  await page.goto(`/player_edit?id=${id}`, { waitUntil: 'domcontentloaded' });
  const deleteBtn = page.locator('button.btn-delete');
  if (await deleteBtn.isVisible()) {
    page.once('dialog', (dialog) => dialog.accept());
    await deleteBtn.click();
    await page.waitForURL(/\/players/, { waitUntil: 'domcontentloaded' });
  }
}

/**
 * テスト用プレイヤーを一括クリーンアップする。
 */
export async function cleanupTestPlayers(page: Page): Promise<void> {
  await page.goto('/players', { waitUntil: 'domcontentloaded' });
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
 * テスト用大会をフォーム経由で作成し、選手全員を登録してIDを返す。
 */
export async function createTestTournamentWithPlayers(
  page: Page,
  name: string
): Promise<number> {
  await page.goto('/tournament_new', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="name"]', name);
  await page.click('#btn-select-all');
  await page.click('button.btn-save');
  await page.waitForURL(/\/tournaments(\?.*)?$/, { waitUntil: 'domcontentloaded' });

  // IDを取得
  await page.goto('/tournaments', { waitUntil: 'domcontentloaded' });
  const card = page.locator('.tournament-card', { hasText: name });
  const link = card.locator('a.tournament-link', { hasText: '管理ページ' });
  const href = await link.getAttribute('href');
  return parseInt(href!.match(/id=(\d+)/)![1], 10);
}

/**
 * 指定IDの大会を削除する。エラーは無視する。
 */
export async function deleteTestTournament(page: Page, id: number): Promise<void> {
  try {
    await page.goto(`/tournament?id=${id}`, { waitUntil: 'domcontentloaded' });
    const deleteBtn = page.locator('.td-btn-delete');
    if (await deleteBtn.isVisible({ timeout: 3000 })) {
      page.once('dialog', (dialog) => dialog.accept());
      await deleteBtn.click();
      await page.waitForURL(/\/tournaments/, { waitUntil: 'domcontentloaded', timeout: 10000 });
    }
  } catch {
    // 完了済みや既に削除済みの場合はスキップ
  }
}

/**
 * ページのHTTPステータスコードを検証する。
 */
export async function expectStatus(page: Page, url: string, status: number): Promise<void> {
  const response = await page.goto(url);
  expect(response?.status()).toBe(status);
}
