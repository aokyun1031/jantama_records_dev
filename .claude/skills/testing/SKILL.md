---
name: testing
description: E2Eテスト（Playwright）の構成・規約・追加手順。ページやフィーチャ追加時に参照する
---

# E2E テスト（Playwright）

## 配置

- `tests/e2e/pages/` — ページ単位のテスト。1 ページ 1 ファイル（例: `player.spec.ts`）
- `tests/e2e/features/` — 横断機能のテスト（セキュリティ、クリーンURL、ナビゲーション、テーマ切替）
- `tests/e2e/helpers/` — 共通ユーティリティ
  - `fixtures.ts` — カスタム `test` fixture（外部リソース・画像ブロックで高速化）
  - `test-helpers.ts` — `createTestPlayer`, `createTestTournamentWithPlayers`, `createOptimizedPage`, `expectStatus`, `TEST_PREFIX`
- `tests/e2e/global-setup.ts` — Neon ウォームアップ（`/players` に事前アクセス）
- `tests/e2e/playwright.config.ts` — `testMatch: ['pages/**/*.spec.ts', 'features/**/*.spec.ts']`、`baseURL = E2E_BASE_URL || http://localhost:8080`

## 実行

```bash
# セットアップ（初回のみ）
cd tests/e2e && npm install && npx playwright install chromium --with-deps

# 全テスト
npx playwright test

# 特定ファイル
npx playwright test pages/players.spec.ts

# ブラウザ表示・UIモード
npx playwright test --headed
npx playwright test --ui
```

前提: `docker compose up -d` で web コンテナが起動していること。`git push` 時に `.claude/hooks/run-e2e.sh` 経由で自動実行され、失敗すると push がブロックされる。

## テストファイルの基本構造

### 読み取り専用ページ（参照元: `pages/players.spec.ts`）

```ts
import { test, expect } from '../helpers/fixtures';

test.describe('選手一覧ページ', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/players');
  });

  test('ページが正常に表示される', async ({ page }) => {
    await expect.soft(page).toHaveTitle(/選手一覧/);
    await expect.soft(page.locator('.players-badge')).toContainText('PLAYERS');
  });
});
```

### フォーム・CRUD ページ（参照元: `pages/player-edit.spec.ts`）

編集・削除など状態遷移を伴うテストは `serial` モード＋`beforeAll` でテストデータ作成。

```ts
import { test, expect } from '../helpers/fixtures';
import { TEST_PREFIX, createTestPlayer, createOptimizedPage } from '../helpers/test-helpers';

test.describe.configure({ mode: 'serial' });
test.describe('選手編集・削除', () => {
  let testPlayerId: number;
  const testName = `${TEST_PREFIX}edit_${Date.now()}`;

  test.beforeAll(async ({ browser }, testInfo) => {
    testInfo.setTimeout(60000);
    const page = await createOptimizedPage(browser);
    testPlayerId = await createTestPlayer(page, testName, '編集前');
    await page.close();
  });

  test('編集フォームが表示される', async ({ page }) => {
    await page.goto(`/player_edit?id=${testPlayerId}`);
    await expect(page.locator('input[name="nickname"]')).toHaveValue('編集前');
  });
});
```

## 規約

- `import { test, expect } from '../helpers/fixtures'` を使う（素の `@playwright/test` ではなく）。fixtures.ts が外部フォント・画像をブロック
- テストデータ名は `${TEST_PREFIX}xxx_${Date.now()}` で衝突回避。`TEST_PREFIX = '__E2E_TEST_'`
- ページ作成は `createTestPlayer` / `createTestTournamentWithPlayers` 経由（SQL を直接叩かない）
- `beforeAll` 用ページは `browser.newPage()` ではなく `createOptimizedPage(browser)` を使う（リソースブロックが効く）
- 404 検証は `expectStatus(page, url, 404)` または `response?.status()`
- 内部リンクは `.php` なし（`/players` であり `/players.php` ではない）
- HTML5 の `required` をバイパスしたい場合は `page.evaluate(() => element.removeAttribute('required'))` で外してから submit
- `confirm()` ダイアログは `page.once('dialog', d => d.accept())` で承認
- 破壊的テスト（削除）は serial モード＋単一 ID で他テストと干渉しないこと

## 新規ページ追加時の手順

1. `tests/e2e/pages/{page-name}.spec.ts` を作成（ハイフン区切り、例: `table_new.php` → `table-new.spec.ts`）
2. 最低限: タイトル表示・主要要素の存在・リンク先検証
3. フォームあり: CSRF トークン存在・成功時リダイレクト・バリデーションエラー・404
4. `npx playwright test pages/{page-name}.spec.ts` で単体実行確認
5. push 前に `npx playwright test` で全体実行

## 横断機能テスト

`features/` 配下。セキュリティ（CSP nonce・CSRF・ドットファイル遮断）、クリーンURL、ナビゲーション、テーマ切替など。特定ページに依存しない挙動はここに。

## デバッグ

- 失敗時: `test-results/` にスクリーンショットとトレースが保存される（`screenshot: 'only-on-failure'`, `trace: 'retain-on-failure'`）
- `npx playwright show-trace test-results/.../trace.zip` でトレース閲覧
- `npx playwright test --ui` で対話実行

## 関連スキル

- `add-page` — 新規ページ作成時のチェックリスト末尾に E2E テスト追加がある
- `security` — セキュリティヘッダー・CSRF 検証パターン
