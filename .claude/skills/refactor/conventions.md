# リファクタ時のチェック基準（CLAUDE.md 抜粋）

このファイルは `CLAUDE.md` と既存スキル（security / design / add-page / data-model）から
**リファクタ判断に使える規約のみ**を抽出したもの。網羅的なガイドは元ファイルを参照。

scan.sh はここに挙げたルールのうち grep で検出可能なものを自動チェックする。
自動化できないルール（例：責務分割、命名）は Claude が人間のレビュー代わりに判断する。

---

## 🔴 CRITICAL（セキュリティ・規約の根幹）

| # | ルール | 検出方法 |
|---|---|---|
| C1 | 全 PHP ファイル先頭に `declare(strict_types=1);` | scan.sh 自動 |
| C2 | `new PDO(...)` 直接生成禁止（`getDbConnection()` を使う） | scan.sh 自動 |
| C3 | `session_start()` 直接禁止（`startSecureSession()` を使う） | scan.sh 自動 |
| C4 | `public/` 配下に SQL を書かない（`->prepare/query/exec`）。`models/` に集約 | scan.sh 自動 |
| C5 | `json_encode` には必ず `JSON_UNESCAPED_UNICODE \| JSON_HEX_TAG`（呼び出し単位で検査） | scan.sh 自動 |
| C6 | `public/*.php` に `<script>` 直書き禁止（`$pageInlineScript` を使う） | scan.sh 自動 |
| — | ユーザー入力を含む SQL はプリペアドステートメント必須 | 手動レビュー |
| — | `<script>` タグに `nonce="<?= cspNonce() ?>"` 必須 | 手動レビュー |
| — | CSRF: `ensureCsrfToken()` + `validateCsrfToken()` を POST フォームで使う | 手動レビュー |
| — | POST 成功後 `regenerateCsrfToken()` + PRG リダイレクト | 手動レビュー |

## 🟡 WARNING（スタイル・一貫性）

| # | ルール | 検出方法 |
|---|---|---|
| W1 | PSR-12: 型キャスト後にスペース `(int) $var` | scan.sh 自動 |
| W2 | 内部リンクに `.php` 付けない（.htaccess で POST が消える） | scan.sh 自動 |
| W3 | `<img>` に `width` / `height` / `alt` 必須、一覧は `loading="lazy"` | scan.sh 自動 |
| W4 | `<form novalidate>` 禁止。HTML5 バリデーションは残す | scan.sh 自動 |
| W5 | インラインイベント属性 `onclick=` 等禁止。`addEventListener` / `data-confirm` | scan.sh 自動 |
| W6 | POST 処理ページに `validatePost()` が無い（CSRF + Turnstile 検証漏れ） | scan.sh 自動 |
| W7 | `<form method="post">` があるのに `$pageTurnstile = true` が未設定 | scan.sh 自動 |
| W8 | `$_POST['key']` 直接アクセス（`sanitizeInput()` を使う。配列は `preg_replace`） | scan.sh 自動 |
| W9 | HTML インライン `style="..."` 属性禁止。modifier クラスを `components.css` に追加。例外: `style="animation-delay:<?= ... ?>s"` のような PHP ループ index 由来の動的値のみ | scan.sh 自動 |
| W10 | 一覧ページの `.tournaments-actions` / `.tournaments-error*` / `.tournaments-empty` / `.tables-pagination` 等のページ固有借用クラスは廃止。共通 `.list-actions` / `.list-error` / `.list-empty` / `.list-pagination` を使う | scan.sh 自動 |
| — | GET は `requirePlayerId()` / `requireTournamentId()` / `filter_input()` で検証 | 手動レビュー |
| — | `fetchData(fn() => ModelName::method())` を使う | 手動レビュー |
| — | HTML 出力は `h()` でエスケープ | 手動レビュー |
| — | フォームバリデーションは HTML5（第一防御）+ PHP（フォールバック）の二層 | 手動レビュー |

## 🔵 INFO（検討余地）

| # | ルール | 検出方法 |
|---|---|---|
| I1 | CSS ハードコードカラー（hex #xxx）は `var(--X)` に置換検討 | scan.sh 自動 |
| I2 | `rgba(数値, ...)` は `rgba(var(--X-rgb), α)` に置換検討（純粋な黒・白は除外） | scan.sh 自動 |
| I3 | `public/js/` `public/css/` の未参照ファイル（dead code 候補） | scan.sh 自動 |
| I4 | 800 行超の `.php` は責務分割を検討（CSS/JS 外部化前提の閾値） | scan.sh 自動 |
| I5 | `<?= $array['key'] ?>` に `h()` 無し（文字列出力なら XSS 候補） | scan.sh 自動 |
| I6 | `<?= $var ?>` に `h()` 無し（整数・CSS クラス等の安全パターンは自動除外） | scan.sh 自動 |
| — | `--glass-border` や `--card` を input 系に使わない（背景と同化する） | 手動レビュー |
| — | ページ固有 CSS は外部ファイル `public/css/{page}.css` に分離、`$pageCss` で読込む。`$pageStyle`（インラインCSS）は Critical CSS 例外時のみ | 手動レビュー |
| I7 | `$pageStyle = <<<'CSS'` ... `CSS;` の行数が多い（30 行超は外出し推奨） | scan.sh 自動 |
| — | enum に寄せるべきハードコード文字列が無いか | 手動レビュー |
| — | 同じパターンの HTML が複数ページに散らばっていないか（共通化余地） | 手動レビュー |

---

## 対象外ファイル（scan.sh で除外）

以下のファイルは `EXCLUDE_FILES` でスキャン対象から除外されている。リファクタ対象としても扱わない。

- `public/index_legacy.php` — 参照ゼロの旧ランディングページ。アーカイブ目的で残置。

新たに対象外を増やす場合は `scripts/scan.sh` の `EXCLUDE_FILES` に追記し、ここにも理由を書く。

## 800 行超ファイルの対処フロー (I4)

機械的に分割するのではなく、上から順に検討する。CSS/JS が外部化済みであることが前提（規約が満たされていれば多くは 800 行未満に収まる）。

1. **ロジック肥大化を疑う** — ファイル先頭の PHP ロジック（`require __DIR__ . '/../templates/header.php';` より前）が 200 行を超えていたら、まず `models/` や `config/helpers.php` 系に切り出す。`fetchData()` 呼び出しが 5 個以上、複雑な整形ロジックがある場合に該当しやすい
2. **JS/CSS が外部化済みか確認** — 未外部化なら先に `public/js/{page}.js` / `public/css/{page}.css` に分離する
3. **同パターン HTML の繰り返しを partial 化** — 同じ構造のカードが 3 種類以上、長い `foreach` 展開などが繰り返されているなら `templates/partials/{page}/{section}.php` 化を検討
4. **責務混在ならページ分割** — 1 URL に複数機能が同居しているなら URL を分ける（例: `/admin/users` と `/admin/users/edit`）
5. **それでも超えるなら例外として許容** — ランディングページのようにセクションが本来多いケース。ファイル先頭のコメントで例外であることと理由を明記する。`I7` の Critical CSS マーカーと同じ「明示的な例外」扱い

「800 行超 = 即分割」ではない。閾値はあくまで考え直すきっかけ。

---

## Critical CSS マーカーによる部分除外

ファイル内に文字列 `Critical CSS` を含むコメントがある場合、scan.sh はそのファイルの I1（ハードコード hex）/ I2（rgba 数値）/ I7（`$pageStyle` 行数）を自動的に除外する。
Loader 等「外部 CSS 到着前に描画必要」な意図的例外のための慣用マーカー。

該当例: `public/index.php` の Loader CSS（外部 CSS 未ロード段階で描画するため CSS 変数が解決できず、ハードコード色を意図的に使用している）。

新規ページで Critical CSS を使う場合は、コードコメントに必ず `Critical CSS` の文字列を含めること。

---

## リファクタ判断の優先順位

1. **CRITICAL を先に直す** — セキュリティ・規約に直結。放置は禁物
2. **WARNING はまとめて直す** — PSR-12 等は機械的に修正可能。1 PR にまとめて良い
3. **INFO は「今やるか」を判断** — 大会運営中なら見送る、落ち着いている時期にまとめて検討

## リファクタ後の検証

- `git diff` で意図通りの差分だけか確認
- E2E テストは手動実行（push 前に `cd tests/e2e && npx playwright test`）
- UI が変わる可能性がある変更は `docker compose up -d` でブラウザ確認推奨

## スコープの原則

**1 PR = 1 テーマ**。次のような混在は避ける:
- ❌ 型キャスト修正 + ロジック変更
- ❌ CSS 変数化 + HTML 構造変更
- ✅ 型キャストだけ / dead code 削除だけ / 500 行超ページの分割だけ

理由: 退行（regression）が起きた時の切り分けが困難になる。
