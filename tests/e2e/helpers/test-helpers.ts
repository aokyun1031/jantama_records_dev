import { type Page, type Browser, expect } from '@playwright/test';

export const TEST_PREFIX = '__E2E_TEST_';

/**
 * beforeAll 用のページを作成する。
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
 * ページのHTTPステータスコードを検証する。
 */
export async function expectStatus(page: Page, url: string, status: number): Promise<void> {
  const response = await page.goto(url);
  expect(response?.status()).toBe(status);
}
