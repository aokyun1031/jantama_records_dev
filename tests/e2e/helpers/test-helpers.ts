import { type Page, type Browser, expect } from '@playwright/test';
import { blockHeavyResources } from './fixtures';

export { TEST_PREFIX, NONEXISTENT_ID } from './constants';

/**
 * beforeAll 用のページを作成する。
 * browser.newPage() は config の use 設定が効かないため、
 * fixture と同じリソースブロックを手動適用する。
 */
export async function createOptimizedPage(browser: Browser): Promise<Page> {
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(30000);
  await blockHeavyResources(page);
  return page;
}

/**
 * URL の `id=123` 形式から数値 ID を取り出す。
 */
export function extractIdFromUrl(url: string, param = 'id'): number {
  const match = url.match(new RegExp(`${param}=(\\d+)`));
  if (!match) throw new Error(`URL から ${param} を抽出できません: ${url}`);
  return parseInt(match[1], 10);
}

/**
 * HTML5 の required 属性を一括で外す。
 * サーバーサイドバリデーションを検証したい時に使う。
 */
export async function bypassRequired(page: Page): Promise<void> {
  await page.evaluate(() => {
    document.querySelectorAll('input[required], select[required], textarea[required]')
      .forEach((el) => el.removeAttribute('required'));
  });
}

/**
 * 指定 URL にアクセスしたときの HTTP ステータスを検証する。
 */
export async function expectStatus(page: Page, url: string, status: number): Promise<void> {
  const response = await page.goto(url);
  expect(response?.status()).toBe(status);
}

/**
 * 指定 URL が 404 を返すことを検証するショートハンド。
 */
export async function expectNotFound(page: Page, url: string): Promise<void> {
  await expectStatus(page, url, 404);
}

/**
 * テスト用プレイヤーをフォーム経由で作成し、ID を返す。
 */
export async function createTestPlayer(
  page: Page,
  name: string,
  nickname: string
): Promise<number> {
  await page.goto('/player_new');
  await page.fill('input[name="name"]', name);
  await page.fill('input[name="nickname"]', nickname);
  await page.locator('.chara-option').first().click();
  await page.click('button.btn-save');
  await page.waitForURL(/\/player\?id=\d+/);
  return extractIdFromUrl(page.url());
}

/**
 * 大会一覧ページから名前でカードを探し、管理ページリンクから ID を抽出する。
 */
async function findTournamentIdByName(page: Page, name: string): Promise<number> {
  await page.goto('/tournaments');
  const link = page
    .locator('.tournament-card', { hasText: name })
    .locator('a.tournament-link', { hasText: '管理ページ' });
  const href = await link.getAttribute('href');
  if (!href) throw new Error(`大会 "${name}" の管理ページリンクが見つかりません`);
  return extractIdFromUrl(href);
}

/**
 * テスト用大会をフォーム経由で作成し、ID を返す（選手は登録しない）。
 */
export async function createTestTournament(page: Page, name: string): Promise<number> {
  await page.goto('/tournament_new');
  await page.fill('input[name="name"]', name);
  await page.click('button.btn-save');
  await page.waitForURL(/\/tournaments(\?.*)?$/);
  return findTournamentIdByName(page, name);
}

/**
 * テスト用大会をフォーム経由で作成し、先頭 N 人の選手を登録して ID を返す。
 * 既定値 8 人は 4 人卓 × 2 分をまかない、かつ Neon への大量 INSERT を回避する設計。
 */
export async function createTestTournamentWithPlayers(
  page: Page,
  name: string,
  playerCount = 8
): Promise<number> {
  await page.goto('/tournament_new');
  await page.fill('input[name="name"]', name);
  const checkboxes = page.locator('.player-select-option input[type="checkbox"]');
  const take = Math.min(playerCount, await checkboxes.count());
  for (let i = 0; i < take; i++) {
    await checkboxes.nth(i).check({ force: true });
  }
  await page.click('button.btn-save');
  await page.waitForURL(/\/tournaments(\?.*)?$/, { timeout: 60000 });
  return findTournamentIdByName(page, name);
}
