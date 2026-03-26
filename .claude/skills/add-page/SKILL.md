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

// --- データ取得（モデル経由） ---
['data' => $items, 'error' => $error] = fetchData(fn() => ModelName::all());

// パラメータ付きの場合
['data' => $item, 'error' => $error] = fetchData(fn() => ModelName::find($id));

// --- テンプレート変数 ---
$pageTitle = 'ページタイトル - 最強位戦';
$pageStyle = <<<'CSS'
/* ページ固有のCSS */
CSS;

// --- 表示 ---
require __DIR__ . '/../templates/header.php';
?>

<!-- HTML（h() でエスケープ） -->
<?= h($item['name']) ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
```

## モデル

`models/` にクラスとして配置。composer autoloadで自動読み込み。

| モデル | 主なメソッド |
|--------|-------------|
| `Player` | `all()`, `find($id)`, `count()` |
| `Standing` | `all()`, `findByPlayer($playerId)` |
| `RoundResult` | `byRound($roundNumber)`, `byPlayer($playerId)` |
| `TableInfo` | `byRound($roundNumber)` |
| `TournamentMeta` | `all()`, `get($key, $default)` |

新しいモデルを追加したら `composer dump-autoload` を実行する。

## ヘルパー関数

- `h($value)` - HTMLエスケープのショートカット
- `fetchData($callback)` - DB取得ラッパー。`['data' => ..., 'error' => bool]` を返す
- `getDbConnection()` - PDOインスタンス取得（Neonスリープ対応リトライ付き）

## テンプレート変数

- `$pageTitle` - ページタイトル（必須）
- `$pageStyle` - ページ固有のインラインCSS（任意）
- `$pageCss` - 追加CSSファイルの配列（任意。例: `['css/finals.css']`）
- `$pageOgp` - OGP設定の連想配列（任意。`title`, `description`, `url`）
- `$pageScripts` - 追加JSファイルの配列（任意。例: `['js/effects.js']`）
