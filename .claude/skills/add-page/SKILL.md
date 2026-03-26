---
name: add-page
description: 新しいPHPページを既存のパターンに従って作成する
---

新しいページを作成する際の手順:

1. `public/players.php` を参考にする（DB接続ありのページの標準パターン）
2. `public/` 配下にファイルを作成

## 基本構造

```php
<?php
require __DIR__ . '/../config/database.php';

// --- データ取得 ---
['data' => $items, 'error' => $error] = fetchData(function (PDO $pdo) {
    return $pdo->query('SELECT ...')->fetchAll();
});

// パラメータ付きの場合
['data' => $item, 'error' => $error] = fetchData(function (PDO $pdo) use ($id) {
    $stmt = $pdo->prepare('SELECT ... WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
});

// --- テンプレート変数 ---
$pageTitle = 'ページタイトル - 最強位戦';
$pageStyle = <<<'CSS'
/* ページ固有のCSS */
CSS;

// --- 表示 ---
require __DIR__ . '/../templates/header.php';
?>

<!-- HTML -->

<?php require __DIR__ . '/../templates/footer.php'; ?>
```

## ヘルパー関数

- `h($value)` - HTMLエスケープ（`htmlspecialchars` のショートカット）
- `fetchData($callback)` - DB取得の共通ラッパー。`['data' => ..., 'error' => bool]` を返す
- `getDbConnection()` - PDOインスタンス取得（Neonスリープ対応のリトライ付き）

## テンプレート変数

- `$pageTitle` - ページタイトル（必須）
- `$pageStyle` - ページ固有のインラインCSS（任意）
- `$pageCss` - 追加CSSファイルの配列（任意。例: `['css/finals.css']`）
- `$pageOgp` - OGP設定の連想配列（任意。`title`, `description`, `url`）
- `$pageScripts` - 追加JSファイルの配列（任意。例: `['js/effects.js']`）

## URL

`.htaccess` の書き換えにより `/pagename` で `.php` なしアクセス可能。
