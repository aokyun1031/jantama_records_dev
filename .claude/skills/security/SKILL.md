---
name: security
description: セキュリティチェックリスト。コードレビューや新機能追加時に参照する
---

# セキュリティガイドライン

## 出力エスケープ

- HTML出力: `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')` を必ず使う
- HTMLテンプレート内の短縮: `<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>`
- JavaScript内にPHP変数を埋め込む場合: `json_encode()` を使う

## SQLインジェクション防止

- ユーザー入力を含むクエリは必ずプリペアドステートメントを使う
- テーブル名・カラム名を動的にする場合はホワイトリスト方式で検証する
- 固定クエリ（ユーザー入力なし）のみ `$pdo->query()` を使用可

```php
// OK
$stmt = $pdo->prepare('SELECT * FROM players WHERE id = ?');
$stmt->execute([$id]);

// NG - SQLインジェクションの危険
$pdo->query("SELECT * FROM players WHERE id = $id");
```

## 環境変数・機密情報

- `.env` は `.gitignore` と `.dockerignore` に含める
- `phinx.php` も `.gitignore` に含める（接続情報を含むため）
- 環境変数の読み込みは `Dotenv\Dotenv::createImmutable()` の `safeLoad()` を使う
- ソースコードにパスワードやAPIキーをハードコードしない

## ディレクトリ構成による防御

- `public/` のみがWebサーバーから公開される
- `config/`, `templates/`, `db/`, `vendor/` はDocumentRoot外
- `public/.htaccess` でドットファイルへのアクセスを禁止済み

## HTTPセキュリティヘッダー（public/.htaccess で設定済み）

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- `Content-Security-Policy` で外部リソースを制限

## エラーハンドリング

- 本番でエラー詳細をユーザーに表示しない
- `error_log()` でサーバーログに記録する
- DBエラーは catch して汎用メッセージを表示（`players.php` のパターンを参照）

## パラメータ検証

GETパラメータは `filter_input()` で型を検証する。不正な値は404を返す。

```php
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
```

## 新機能追加時のチェックリスト

1. ユーザー入力はプリペアドステートメントで処理しているか
2. GETパラメータは `filter_input()` で検証しているか
3. HTML出力はエスケープしているか
4. 機密情報がソースコードに含まれていないか
5. 新しいファイルは `public/` 内に適切に配置されているか
6. エラー時にスタックトレースや接続情報が漏れないか
