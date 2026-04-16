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
 * テスト用大会をフォーム経由で作成し、先頭 N 人の選手を登録してIDを返す。
 * 既定値 8 人は 4 人卓 × 2 分をまかない、かつ Neon への大量 INSERT を回避する設計。
 */
export async function createTestTournamentWithPlayers(
  page: Page,
  name: string,
  playerCount = 8
): Promise<number> {
  await page.goto('/tournament_new', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="name"]', name);
  const checkboxes = page.locator('.player-select-option input[type="checkbox"]');
  const total = await checkboxes.count();
  const take = Math.min(playerCount, total);
  for (let i = 0; i < take; i++) {
    await checkboxes.nth(i).check({ force: true });
  }
  await page.click('button.btn-save');
  await page.waitForURL(/\/tournaments(\?.*)?$/, { waitUntil: 'domcontentloaded', timeout: 60000 });

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
