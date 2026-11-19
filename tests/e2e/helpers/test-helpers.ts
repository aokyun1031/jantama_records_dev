import { type Page, type Browser, type APIResponse, expect } from '@playwright/test';
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
 * テスト用大会をフォーム経由で作成し、ID を返す（選手は登録しない）。
 */
export async function createTestTournament(page: Page, name: string): Promise<number> {
  await page.goto('/tournament_new');
  await page.fill('input[name="name"]', name);
  await page.click('button.btn-save');
  await page.waitForURL(/\/tournament\?id=\d+/);
  return extractIdFromUrl(page.url());
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
  await page.waitForURL(/\/tournament\?id=\d+/, { timeout: 60000 });
  return extractIdFromUrl(page.url());
}

/**
 * 大会に登録済みの選手 ID 一覧を取得する。
 */
export async function getTournamentPlayerIds(page: Page, tournamentId: number): Promise<string[]> {
  await page.goto(`/tournament_players?id=${tournamentId}`);
  return page
    .locator('.player-select-option input[type="checkbox"]:checked')
    .evaluateAll((els) => els.map((el) => (el as HTMLInputElement).value));
}

/**
 * 卓作成フォームへ直接 POST する（フォームの JS を通さずサーバ側バリデーションを検証するため）。
 * 成功時は 302、バリデーションエラー時は 200 + エラーメッセージ入り HTML が返る。
 */
export async function postTableNew(
  page: Page,
  tournamentId: number,
  tables: { name: string; playerIds: string[] }[],
  settings: Record<string, string> = {}
): Promise<APIResponse> {
  await page.goto(`/table_new?tournament_id=${tournamentId}`);
  const csrfToken = await page.locator('#table-form input[name="csrf_token"]').inputValue();

  const body = new URLSearchParams();
  body.append('csrf_token', csrfToken);
  tables.forEach((t, i) => {
    body.append(`tables[${i}][name]`, t.name);
    t.playerIds.forEach((id) => body.append(`tables[${i}][player_ids][]`, id));
  });
  Object.entries({ game_count: '2', advance_count: '2', ...settings }).forEach(([k, v]) => body.append(k, v));

  return page.request.post(`/table_new?tournament_id=${tournamentId}`, {
    data: body.toString(),
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    maxRedirects: 0,
  });
}

/**
 * 残り選手が 1 卓に収まる大会で、決勝卓の作成から対局結果の入力までを行い「決勝完了」状態にする。
 * 大会を完了にできるのは決勝ラウンドの全卓が完了した後だけなので、完了系のテストの前提に使う。
 */
export async function playFinalToCompletion(page: Page, tournamentId: number): Promise<void> {
  // 決勝は全員が 1 卓に自動配置される
  await page.goto(`/table_new?tournament_id=${tournamentId}`);
  await expect(page.locator('.tn-table')).toHaveCount(1);
  await page.click('#btn-save');
  await page.waitForURL(/\/tournament\?id=\d+/, { timeout: 60000 });

  const href = await page.locator('.td-table-card').first().getAttribute('href');
  await page.goto(`/table?id=${extractIdFromUrl(href!)}`);

  const paifuInputs = page.locator('#table-form input.tb-paifu-input');
  const paifuCount = await paifuInputs.count();
  for (let i = 0; i < paifuCount; i++) {
    await paifuInputs.nth(i).fill(`https://example.com/paifu/final-${i + 1}`);
  }
  const scoreInputs = page.locator('#table-form input.tb-score-input');
  const scoreCount = await scoreInputs.count();
  const scorePool = [25.0, 10.0, -5.0, -30.0];
  for (let i = 0; i < scoreCount; i++) {
    await scoreInputs.nth(i).fill(scorePool[i % scorePool.length].toString());
  }

  const doneBtn = page.locator('#table-form button[value="game_data"]');
  await expect(doneBtn).toBeEnabled();
  page.once('dialog', (dialog) => dialog.accept());
  await doneBtn.click();
  await page.waitForURL(/\/tournament\?id=\d+/, { timeout: 60000 });
}
