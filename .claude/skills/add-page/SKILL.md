---
name: add-page
description: 新しいPHPページを既存のパターンに従って作成する
---

# 新規ページ作成

## 参照元ページ

- 読み取り専用ページ: `public/players.php`
- フォーム付きページ: `public/player_edit.php`

## 読み取り専用ページの基本構造

```php
<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

// バリデーション（パラメータがある場合）
$playerId = requirePlayerId();          // GET の id を検証、無効なら404
$player = requirePlayer($playerId);     // DB から取得、見つからなければ404

// データ取得（モデル経由）
['data' => $items] = fetchData(fn() => ModelName::all($tournamentId));

// テンプレート変数
$pageTitle = 'ページタイトル - ' . SITE_NAME;
$pageDescription = 'ページの説明文';
// ページ固有 CSS は外部ファイル化して $pageCss で読込む。
// まず components.css / base.css / forms.css に既存スタイルが無いか確認し、
// それでも必要なときだけ public/css/{page}.css を新規作成する。
$pageCss = ['css/{page}.css'];

require __DIR__ . '/../templates/header.php';
?>

<?= h($item['name']) ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
```

## フォーム付きページの基本構造

```php
<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

$pageTurnstile = true;  // footer.php で Turnstile を一括埋込
startSecureSession();
ensureCsrfToken();

$playerId = requirePlayerId();
$player = requirePlayer($playerId);

// POST 処理
$validationError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validatePost()) {         // CSRF + Turnstile 一括検証
        http_response_code(403);
        $validationError = '不正なリクエストです。';
    } else {
        $input = sanitizeInput('field_name');
        // バリデーション → DB更新
        try {
            Player::update($id, ...);
            $_SESSION['flash'] = '保存しました。';
            regenerateCsrfToken();
            header('Location: player?id=' . $id);
            exit;
        } catch (PDOException $e) {
            error_log('[DB] ' . $e->getMessage());
            $validationError = '保存に失敗しました。';
        }
    }
}

$pageCss = ['css/forms.css'];
$pageTitle = '編集 - ' . SITE_NAME;
require __DIR__ . '/../templates/header.php';
?>

<?php if ($validationError): ?>
  <div class="edit-message error"><?= h($validationError) ?></div>
<?php endif; ?>

<form method="post" action="page_name?id=<?= $id ?>">
  <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
  <!-- フォーム要素 -->
  <button type="submit" class="btn-save">保存</button>
</form>
```

## フォームバリデーション（二層）

`novalidate` は付けない。

### 1. HTML5（ブラウザ側・第一防御）

```html
<input type="text" name="name" class="edit-input" required maxlength="100">
<input type="number" name="points" class="edit-input" required min="100" max="200000" step="100">
```

- テキスト・数値には `required`
- ラジオ・セレクトはデフォルト値選択済みなので `required` 不要
- チェックボックスグループは HTML5 で検証不可→PHP側で検証

### 2. PHP（サーバー側・フォールバック）

```php
if ($input === '') {
    $validationError = '○○を入力してください。';
}
```

### メッセージ規約

| 種類 | パターン | 例 |
|---|---|---|
| 必須 | `○○を入力してください。` | `大会名を入力してください。` |
| 文字数 | `○○はN文字以内で入力してください。` | `呼称は50文字以内で入力してください。` |
| 範囲 | `○○はN〜Mの範囲で入力してください。` | `配給原点は100〜200,000の範囲で入力してください。` |
| 選択必須 | `○○を選択してください。` / `○○を1人以上選択してください。` | `キャラクターを選択してください。` |
| 不正値 | `不正な○○です。` | `不正な赤ドラ設定です。` |
| DB失敗 | `○○に失敗しました。` | `登録に失敗しました。` |

## テンプレート変数

| 変数 | 必須 | 用途 |
|---|---|---|
| `$pageTitle` | ✓ | `<title>` タグ |
| `$pageDescription` | 推奨 | `<meta name="description">` |
| `$pageCss` | 推奨 | ページ固有・追加CSSファイル配列。**ページ固有CSSは `css/{page}.css` に外部化してここで読込む**（フォームは `['css/forms.css']`） |
| `$pageStyle` | 非推奨 | インライン CSS。原則使わない。例外は Loader 等の Critical CSS（外部 CSS 到着前に描画必須のもの）のみ |
| `$pageOgp` | 任意 | OGP設定（`title`, `description`, `url`） |
| `$pageScripts` | 任意 | 追加JSファイル配列 |
| `$pageInlineScript` | 任意 | インラインJS（`json_encode` には `JSON_HEX_TAG` 必須） |
| `$pageTurnstile` | 任意 | `true` で footer.php が Turnstile を一括埋込 |

## 新規ページ作成後のチェックリスト

1. `declare(strict_types=1)` がある
2. `$pageTitle` と `$pageDescription` を設定
3. CSS にハードコードカラーを使っていない（→ `design` skill）
4. **ページ固有 CSS は `public/css/{page}.css` に外部化し `$pageCss` で読込んでいる**（`$pageStyle` は Critical CSS 例外時のみ）
5. HTML 出力は全て `h()` でエスケープ
6. 画像に `width`, `height`, `alt` 属性（一覧なら `loading="lazy"`）
7. リンクURLに `.php` を付けていない
8. フォームがあれば CSRF + Turnstile + sanitizeInput（→ `security` skill）
9. POST 成功後に PRG リダイレクト + `regenerateCsrfToken()`
10. `README.md` のページ遷移セクションを更新
11. `tests/e2e/pages/` にE2Eテストを追加（→ `testing` skill）

## 一覧ページのテンプレート

新規一覧ページ（players / tournaments / tables 系）を作る場合は以下の骨組みに従う:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

startSecureSession();
$flash = consumeFlash();

// --- フィルタ取得 ---
// (filter_input + Enum::tryFrom などで GET を検証)

// --- データ取得 + 件数取得 ---
['data' => $totalCount] = fetchData(fn() => Model::searchAllCount($filters));
$totalCount = $totalCount ?? 0;

// --- ページネーション ---
['page' => $page, 'totalPages' => $totalPages, 'offset' => $offset] = paginate($totalCount, 10);
['data' => $items] = fetchData(fn() => Model::searchAll($filters, 10, $offset));

// --- ページ送り URL ヘルパ（フィルタを保持しつつ page だけ書き換え） ---
$pageBaseQuery = [/* event_types, q, status など */];
$pageUrl = function (int $p) use ($pageBaseQuery): string {
    $q = $pageBaseQuery;
    if ($p > 1) { $q['page'] = $p; }
    return '{page}' . (empty($q) ? '' : '?' . http_build_query($q));
};

$pageCss = ['css/forms.css', 'css/filters.css', 'css/{page}.css'];
$pageScripts = ['js/filter-form.js'];
require __DIR__ . '/../templates/header.php';
?>
<!-- 件数表示 / フィルタ UI / カードリスト -->
<?php if (!$totalCount): ?>
  <div class="list-empty">該当なし</div>
<?php endif; ?>

<!-- ページネーション（partial に共通化済み） -->
<?php require __DIR__ . '/../templates/partials/list-pagination.php'; ?>

<div class="list-actions">
  <a href="/" class="btn-cancel">&larr; トップへ</a>
  <a href="{new_page}" class="btn-cancel btn-cancel--primary">+ 新規追加</a>
</div>
```

参考実装: `public/tournaments.php`, `public/tables.php`

## partial を作成・利用するときの規約

`templates/partials/{name}.php` を新規作成する／既存 partial を `require` する場合:

- partial 内で親スコープから受け取る変数は **冒頭で `isset` + 型チェック + 早期 return ガード** する。include 順を間違えても安全に no-op するように
- 期待する変数と型を PHPDoc 風コメントで明記する
- 親スコープの変数を上書きしないよう、partial 専用の変数名は `_` 始まり等で区別すると安全

参考: `templates/partials/list-pagination.php`

## 関連スキル

- `security` — ヘルパー関数・CSRF・セッション
- `design` — CSS 変数・コンポーネントパターン・list-* 共通骨組み
- `data-model` — モデル一覧・クエリパターン
- `testing` — E2E テスト追加方法
