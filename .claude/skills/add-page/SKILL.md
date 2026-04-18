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
$pageStyle = <<<'CSS'
.my-section {
  background: var(--card);
  border: 1px solid rgba(var(--accent-rgb), 0.25);
  color: var(--text);
}
CSS;

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
| `$pageStyle` | 任意 | ページ固有インラインCSS（共通CSS に無いものだけ） |
| `$pageCss` | 任意 | 追加CSSファイル配列（フォームは `['css/forms.css']`） |
| `$pageOgp` | 任意 | OGP設定（`title`, `description`, `url`） |
| `$pageScripts` | 任意 | 追加JSファイル配列 |
| `$pageInlineScript` | 任意 | インラインJS（`json_encode` には `JSON_HEX_TAG` 必須） |
| `$pageTurnstile` | 任意 | `true` で footer.php が Turnstile を一括埋込 |

## 新規ページ作成後のチェックリスト

1. `declare(strict_types=1)` がある
2. `$pageTitle` と `$pageDescription` を設定
3. CSS にハードコードカラーを使っていない（→ `design` skill）
4. HTML 出力は全て `h()` でエスケープ
5. 画像に `width`, `height`, `alt` 属性（一覧なら `loading="lazy"`）
6. リンクURLに `.php` を付けていない
7. フォームがあれば CSRF + Turnstile + sanitizeInput（→ `security` skill）
8. POST 成功後に PRG リダイレクト + `regenerateCsrfToken()`
9. `README.md` のページ遷移セクションを更新
10. `tests/e2e/pages/` にE2Eテストを追加（→ `testing` skill）

## 関連スキル

- `security` — ヘルパー関数・CSRF・セッション
- `design` — CSS 変数・コンポーネントパターン
- `data-model` — モデル一覧・クエリパターン
- `testing` — E2E テスト追加方法
