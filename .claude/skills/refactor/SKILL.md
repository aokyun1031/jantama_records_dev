---
name: refactor
description: public/ 配下の PHP/JS/CSS に対するリファクタリング。CLAUDE.md 規約からのドリフトを検出・修正する際に参照する
---

# リファクタリング・スキル

Claude 主導で開発を進めると、会話ごとに微妙な不整合が積み上がる（= ドリフト）。
このスキルは `public/` 配下の PHP / JS / CSS に対して、`CLAUDE.md` 規約からのドリフトを
**検出** → **レビュー** → **修正** する流れを統一する。

## 起動条件

以下のいずれかでこのスキルを参照する:

- ユーザーが「リファクタ」「整理」「規約違反チェック」「ドリフト検出」を依頼した
- `/refactor` スラッシュコマンドから呼び出された
- 新規ページ作成後、既存ページとの一貫性を確認する時

## 2 つのモード

### 1. チェックモード（デフォルト・安全）

**ファイルを変更しない。**現状のドリフトをレポートするだけ。

```bash
bash .claude/skills/refactor/scripts/scan.sh                    # 全体
bash .claude/skills/refactor/scripts/scan.sh --target <path>    # 特定ファイル/ディレクトリ
bash .claude/skills/refactor/scripts/scan.sh --summary          # 件数のみ
```

出力は 3 段階の深刻度:
- **CRITICAL**: セキュリティ / 規約の根幹。即修正すべき
- **WARNING**: スタイル / 一貫性。まとめて修正推奨
- **INFO**: 検討余地。状況を見て判断

### 2. 修正モード

ユーザーが対象を明示した場合のみ実行する。`/refactor <path>` 経由で呼ばれることを想定。

## チェック基準

grep で自動検出できるルールと、手動レビューが必要なルールは [conventions.md](conventions.md) を参照。
網羅的な規約は `CLAUDE.md` が正本。

## 修正時の手順

1. **対象ファイルを決める** — ユーザーが指定しない限り、一度に触るのは最大 1 〜 3 ファイル
2. **事前に scan.sh を実行** — 修正対象に何件のドリフトがあるか把握
3. **1 PR = 1 テーマ を守る** — 型キャスト修正とロジック変更を混ぜない
4. **修正後に再度 scan.sh で確認** — 意図した件数だけ減っているか
5. **E2E テストは手動実行** — push 前に `cd tests/e2e && npx playwright test` を各自で回す
6. **UI が変わる可能性のある変更は `docker compose up -d` でブラウザ確認推奨**

## 大きな変更を伴うリファクタ

500 行超のページ分割、共通コンポーネント抽出など、構造を変える場合:

1. まず提案する（どのファイルをどう分割するか）
2. ユーザーの合意を得てから実行
3. 既存 E2E テストが落ちないことを最優先
4. 新規モデル追加時は `composer dump-autoload` を忘れない

## 使用禁止パターン（必ず守る）

`public/` 配下のリファクタでは以下を**導入しない**:

- ❌ `new PDO(...)` 直接生成（`getDbConnection()` を使う）
- ❌ `session_start()` 直接呼び出し（`startSecureSession()` を使う）
- ❌ SQL を `public/*.php` に書く（`models/` へ移す）
- ❌ `$_POST[$key]` 直接アクセス（`sanitizeInput($key)` を使う）
- ❌ `<script>` タグに nonce 無し（`$pageInlineScript` を使うか `cspNonce()` を付ける）
- ❌ `json_encode` に `JSON_HEX_TAG` 無し
- ❌ ハードコードカラー（CSS 変数を使う）
- ❌ 内部リンクに `.php` 拡張子
- ❌ インラインイベント属性 `onclick=` 等

## 関連スキル

- `security` — セキュリティ関連の詳細ルール
- `design` — CSS 変数とコンポーネントパターン
- `add-page` — 新規ページの基本構造
- `data-model` — モデル層のクエリパターン
