---
name: add-page
description: 新しいPHPページを既存のパターンに従って作成する
---

新しいページを作成する際の手順:

1. 読み取り専用ページは `public/players.php` を参考にする
2. フォーム付きページは `public/player_edit.php` を参考にする
3. `public/` 配下にファイルを作成

## 読み取り専用ページの基本構造

```php
<?php
declare(strict_types=1);
require __DIR__ . '/../config/database.php';

// --- バリデーション（パラメータがある場合） ---
$playerId = requirePlayerId();          // GETのidを検証、無効なら404
$player = requirePlayer($playerId);     // DBから取得、見つからなければ404

// --- データ取得（モデル経由） ---
['data' => $items] = fetchData(fn() => ModelName::all($tournamentId));

// --- テンプレート変数 ---
$pageTitle = 'ページタイトル - ' . SITE_NAME;
$pageDescription = 'ページの説明文';
$pageStyle = <<<'CSS'
/* CSS変数を使う。ハードコードカラー禁止 */
.my-section {
  background: var(--card);
  border: 1px solid rgba(var(--accent-rgb), 0.25);
  color: var(--text);
}
CSS;

require __DIR__ . '/../templates/header.php';
?>

<!-- HTML（h() でエスケープ） -->
<?= h($item['name']) ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
```

## フォーム付きページの基本構造

```php
<?php
declare(strict_types=1);
require __DIR__ . '/../config/database.php';

startSecureSession();
ensureCsrfToken();

$playerId = requirePlayerId();
$player = requirePlayer($playerId);

// POST処理
$validationError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        http_response_code(403);
        $validationError = '不正なリクエストです。';
    } else {
        $input = sanitizeInput('field_name');
        // バリデーション → DB更新 → regenerateCsrfToken() → リダイレクト
    }
}

// テンプレート変数・表示...
```

```html
<form method="post" action="page_name?id=<?= $id ?>">
  <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
  <!-- フォーム要素 -->
  <button type="submit" class="btn-save">保存</button>
</form>
```

## フォームバリデーション

二層バリデーションで実装する。`novalidate` は付けない。

### 1. HTML5バリデーション（ブラウザ側・第一防御線）

入力フィールドに `required`, `maxlength`, `min`, `max` 等の属性を付ける。
ブラウザがネイティブのエラーメッセージ（ツールチップ）を表示し、不正な送信をブロックする。

```html
<input type="text" name="name" class="edit-input" required maxlength="100">
<input type="number" name="points" class="edit-input" required min="100" max="200000" step="100">
```

- テキスト入力・数値入力には `required` を付ける
- ラジオボタン・セレクトはデフォルト値が選択済みなので `required` 不要
- チェックボックスグループ（選手選択など）はHTML5だけでは検証できないのでPHP側で検証

### 2. PHPバリデーション（サーバー側・フォールバック）

HTML5バリデーションを迂回された場合のフォールバック。`$validationError` にメッセージを格納し、
`.edit-message.error` で表示する。

```php
$validationError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        http_response_code(403);
        $validationError = '不正なリクエストです。ページを再読み込みしてください。';
    } else {
        $input = sanitizeInput('field_name');
        if ($input === '') {
            $validationError = '○○を入力してください。';
        }
        // ... 追加のバリデーション
    }
}
```

```html
<?php if ($validationError): ?>
  <div class="edit-message error"><?= h($validationError) ?></div>
<?php endif; ?>
```

### メッセージの書き方

| 種類 | パターン | 例 |
|---|---|---|
| 必須 | `○○を入力してください。` | `大会名を入力してください。` |
| 文字数 | `○○はN文字以内で入力してください。` | `呼称は50文字以内で入力してください。` |
| 範囲 | `○○はN〜Mの範囲で入力してください。` | `配給原点は100〜200,000の範囲で入力してください。` |
| 選択必須 | `○○を選択してください。` / `○○を1人以上選択してください。` | `キャラクターを選択してください。` |
| 不正値 | `不正な○○です。` / `不正な○○設定です。` | `不正な赤ドラ設定です。` |
| DB失敗 | `○○に失敗しました。` | `登録に失敗しました。` |

## ヘルパー関数（config/database.php）

| 関数 | 用途 |
|---|---|
| `h($value)` | HTMLエスケープ（string/int/float対応） |
| `asset($path)` | 静的ファイルにキャッシュバスティング付きパスを返す |
| `fetchData($callback)` | DB取得ラッパー。`['data' => ..., 'error' => bool]` を返す |
| `startSecureSession()` | セキュアなセッション開始（httponly/secure/samesite/strict_mode） |
| `ensureCsrfToken()` | CSRFトークン生成（未生成の場合のみ） |
| `validateCsrfToken()` | POSTのCSRFトークンを検証（bool） |
| `regenerateCsrfToken()` | トークン再生成 + セッションID再生成。POST成功後に呼ぶ |
| `sanitizeInput($key)` | POST入力の制御文字除去 + trim |
| `consumeFlash()` | フラッシュメッセージを取得して消費する |
| `buildRuleTags($meta)` | 大会メタ情報からルールタグ配列を生成 |
| `abort404()` | 404レスポンスを返して終了 |
| `requirePlayerId()` | GETのidを検証・取得。無効なら404 |
| `requirePlayer($id)` | プレイヤーをDB取得。見つからなければ404 |
| `requireTournamentId()` | GETのidを検証・取得。無効なら404 |
| `requireTournamentWithMeta($id)` | 大会をメタ付きで取得。見つからなければ404 |

## モデル

詳細は `data-model` スキルを参照。主要メソッドのみ:

| モデル | 主なメソッド |
|---|---|
| `Character` | `all()`, `find($id)` |
| `Player` | `all()`, `find($id)`, `create()`, `update()`, `delete()`, `existsByName()`, `hasTournaments()`, `count()` |
| `Tournament` | `all()`, `allWithDetails()`, `find($id)`, `findWithMeta($id)`, `createWithDetails()`, `updateDetails()`, `playerIds()`, `playedPlayerIds()`, `updatePlayers()`, `start()`, `complete()`, `processRoundCompletion()`, `delete()`, `byPlayer()` |
| `Standing` | `all($tid)`, `finalists($tid)`, `champion($tid)`, `findByPlayer()`, `activePlayerIds()`, `totalMap()`, `updateTotals()`, `processRoundAdvancement()` |
| `RoundResult` | `byRound($tid, $round)`, `byPlayer($tid, $pid)`, `byRoundGrouped($tid, $round)`, `saveScores($tid, $round, $scores, $gameNumber)` |
| `TableInfo` | `find($id)`, `findWithPlayers($id)`, `byTournament($tid)`, `byRound()`, `byPlayerAndTournament()`, `playerGroupsByRound()`, `create()`, `createBatch()`, `updateSchedule()`, `markDone()`, `delete()` |
| `TablePaifuUrl` | `byTable($tableId)`, `saveAll($tableId, $urls)` |
| `TournamentMeta` | `all($tid)`, `get($tid, $key, $default)`, `set($tid, $key, $value)` |
| `Interview` | `byTournament($tid)`, `save($tid, $items)` |
| `PlayerAnalysis` | `summary($pid)`, `avgTableRank($pid)`, `headToHead($pid)`, `scoreHistory($pid)` |

## 共通CSS

フォーム付きページでは `css/forms.css` を読み込む。edit-*、btn-save、player-select-* 等の共通クラスが定義済み。

```php
$pageCss = ['css/forms.css'];
$pageStyle = ''; // ページ固有CSSが無ければ空文字列
```

`$pageStyle` には forms.css / components.css に無いページ固有スタイルのみ書く。戻るボタンは `btn-cancel` クラス（components.css で定義済み）を使う。

## テンプレート変数

| 変数 | 必須 | 用途 |
|---|---|---|
| `$pageTitle` | 必須 | `<title>` タグ |
| `$pageDescription` | 推奨 | `<meta name="description">` |
| `$pageStyle` | 任意 | ページ固有のインラインCSS（共通CSSに無いものだけ） |
| `$pageCss` | 任意 | 追加CSSファイルの配列（フォームページは `['css/forms.css']`） |
| `$pageOgp` | 任意 | OGP設定（`title`, `description`, `url`） |
| `$pageScripts` | 任意 | 追加JSファイルの配列 |
| `$pageInlineScript` | 任意 | インラインJS（`json_encode` には `JSON_HEX_TAG` 必須） |

## ダークテーマ対応

ページ固有CSSでは必ずCSS変数を使う。ハードコードカラー禁止。

```css
/* OK */
background: var(--card);
color: var(--text);
border: 1px solid rgba(var(--accent-rgb), 0.25);
box-shadow: var(--btn-primary-shadow);

/* NG */
background: rgba(155, 140, 232, 0.25);
color: #2d2b55;
```

主なCSS変数:
- 色: `--text`, `--text-sub`, `--text-light`, `--purple`, `--pink`, `--mint`, `--gold`
- RGB: `--accent-rgb`, `--gold-rgb`, `--mint-rgb`, `--coral-rgb`, `--danger-rgb`
- 背景: `--card`, `--card-hover`
- ボーダー: `--glass-border`
- 影: `--shadow`, `--shadow-sm`
- ボタン: `--btn-primary-bg`, `--btn-secondary-bg`, `--btn-text-color`, `--btn-primary-shadow`
- バッジ: `--badge-bg`, `--badge-color`
- タイトル: `--title-gradient`, `--title-filter`
- セマンティック: `--danger`, `--success`

## 画像の扱い

```html
<!-- width/height必須、alt必須、lazy推奨 -->
<img src="img/chara_deformed/<?= h($icon) ?>" alt="<?= h($name) ?>" width="88" height="88" loading="lazy">
```

## フォームのリンク

URL に `.php` を付けない（.htaccess の 301 リダイレクトで POST データが消えるため）。

```html
<!-- OK -->
<a href="player_edit?id=<?= $id ?>">編集</a>
<form action="player_new" method="post">

<!-- NG -->
<a href="player_edit.php?id=<?= $id ?>">編集</a>
```

## 新規ページ作成後のチェックリスト

1. `declare(strict_types=1)` があるか
2. `$pageTitle` と `$pageDescription` を設定したか
3. CSS でハードコードカラーを使っていないか（CSS変数を使う）
4. HTML出力は全て `h()` でエスケープしているか
5. 画像に `width`, `height`, `alt` 属性があるか
6. リンクURLに `.php` を付けていないか
7. フォームがあれば CSRF + セッション + sanitizeInput を使っているか
8. POST成功後に PRG リダイレクト + `regenerateCsrfToken()` しているか
9. `README.md` のファイル構成を更新したか
10. E2E テストを `tests/e2e/pages/` に追加したか
