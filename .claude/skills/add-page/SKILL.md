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
$pageTitle = 'ページタイトル - 最強位戦';
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
| `abort404()` | 404レスポンスを返して終了 |
| `requirePlayerId()` | GETのidを検証・取得。無効なら404 |
| `requirePlayer($id)` | プレイヤーをDB取得。見つからなければ404 |

## モデル

| モデル | 主なメソッド |
|---|---|
| `Character` | `all()`, `find($id)` |
| `Player` | `all()`, `find($id)`, `create($name, $nickname, $characterId)`, `existsByName($name)`, `update($id, $nickname, $characterId)`, `hasTournaments($id)`, `delete($id)`, `count()` |
| `Tournament` | `all()`, `find($id)`, `byPlayer($playerId)` |
| `Standing` | `all($tournamentId)`, `finalists($tournamentId)`, `findByPlayer($tournamentId, $playerId)` |
| `RoundResult` | `byRound($tournamentId, $roundNumber)`, `byPlayer($tournamentId, $playerId)` |
| `TableInfo` | `byRound($tournamentId, $roundNumber)`, `byPlayerAndTournament($tournamentId, $playerId)` |
| `TournamentMeta` | `all($tournamentId)`, `get($tournamentId, $key, $default)` |
| `PlayerAnalysis` | `summary($playerId)`, `avgTableRank($playerId)`, `headToHead($playerId)`, `scoreHistory($playerId)` |

## テンプレート変数

| 変数 | 必須 | 用途 |
|---|---|---|
| `$pageTitle` | 必須 | `<title>` タグ |
| `$pageDescription` | 推奨 | `<meta name="description">` |
| `$pageStyle` | 任意 | ページ固有のインラインCSS |
| `$pageCss` | 任意 | 追加CSSファイルの配列 |
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
