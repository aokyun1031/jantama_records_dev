---
name: security
description: セキュリティチェックリスト。コードレビューや新機能追加時に参照する
---

# セキュリティガイドライン

## ヘルパー関数（config/database.php に定義済み）

新しいコードでは以下の関数を必ず使用する。直接的な実装は避ける。

| 関数 | 用途 |
|---|---|
| `startSecureSession()` | httponly/secure/samesite/strict_mode 付きセッション開始 |
| `ensureCsrfToken()` | CSRFトークン生成（セッションに保存） |
| `validateCsrfToken()` | POSTのCSRFトークンを `hash_equals` で検証 |
| `regenerateCsrfToken()` | トークン再生成 + `session_regenerate_id(true)` |
| `sanitizeInput($key)` | POST入力の制御文字除去 + trim |
| `abort404()` | 404レスポンスを返して処理終了 |
| `requirePlayerId()` | GETの`id`を `FILTER_VALIDATE_INT` で検証（≤0も拒否） |
| `requirePlayer($id)` | DB取得 + 見つからなければ404 |
| `h($value)` | HTMLエスケープ（string/int/float対応） |

## 出力エスケープ

- HTML出力: `h($value)` を使う
- JavaScript内にPHP変数を埋め込む: `json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)`
- `JSON_HEX_TAG` を忘れると `</script>` によるXSSが成立する

## SQLインジェクション防止

- ユーザー入力を含むクエリは必ずプリペアドステートメントを使う
- 固定クエリ（ユーザー入力なし）のみ `$pdo->query()` を使用可
- `PDO::ATTR_EMULATE_PREPARES => false` でネイティブプリペアド強制済み

## CSRF保護

POSTフォームには必ず実装する。`player_edit.php` のパターンを参照。

```php
// ページ冒頭
startSecureSession();
ensureCsrfToken();

// POST処理
if (!validateCsrfToken()) {
    http_response_code(403);
    $validationError = '不正なリクエストです。';
}

// POST成功後
regenerateCsrfToken();  // トークン + セッションID を再生成
header('Location: ...');
exit;
```

```html
<form method="post" action="page_name?id=<?= $id ?>">
  <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
</form>
```

## PRGパターン

POST成功後は必ずリダイレクトする（二重送信防止）。フラッシュメッセージはセッションに格納。

```php
$_SESSION['flash'] = '保存しました。';
regenerateCsrfToken();
header('Location: page?id=' . $id);
exit;
```

## セッション設定

`startSecureSession()` が以下を自動設定する。直接 `session_start()` を呼ばない。

- `cookie_httponly => true`
- `cookie_secure` — HTTPS またはリバースプロキシ（X-Forwarded-Proto）対応
- `cookie_samesite => 'Lax'`
- `use_strict_mode => true`

## 入力サニタイズ

`sanitizeInput($key)` が以下を行う。直接 `$_POST` を使わない。

- `$_POST[$key]` の取得（未設定なら空文字）
- 制御文字（`\x00-\x1F`, `\x7F`）の除去
- 前後の空白トリム

## パラメータ検証

- `requirePlayerId()` — GETの`id`を検証。`false`/`null`/`≤0` なら404
- `requirePlayer($id)` — DB取得。見つからなければ404
- 整数パラメータは `filter_input(INPUT_GET, 'key', FILTER_VALIDATE_INT)` を使う

## URL

- 内部リンクに `.php` を付けない（`.htaccess` の301リダイレクトでPOSTがGETに変換される）
- リダイレクト先は固定パス + 検証済み整数のみ（オープンリダイレクト防止）

## HTTPセキュリティヘッダー（.htaccess で設定済み）

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Strict-Transport-Security: max-age=31536000; includeSubDomains`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- `Content-Security-Policy` で外部リソースを制限

## エラーハンドリング

- DB操作は `fetchData()` で囲む（例外キャッチ + ログ記録）
- 本番は `display_errors = Off`（Dockerfileで設定済み）
- ユーザーには汎用メッセージのみ表示

## 新機能追加時のチェックリスト

1. `declare(strict_types=1)` があるか
2. ユーザー入力はプリペアドステートメントで処理しているか
3. GETパラメータは `requirePlayerId()` や `filter_input()` で検証しているか
4. POST入力は `sanitizeInput()` で取得しているか
5. HTML出力は全て `h()` でエスケープしているか
6. `json_encode` に `JSON_HEX_TAG` フラグを付けているか
7. POSTフォームに CSRF トークン（`ensureCsrfToken` + `validateCsrfToken`）を実装しているか
8. POST成功後に PRG リダイレクト + `regenerateCsrfToken()` しているか
9. セッションは `startSecureSession()` で開始しているか
10. URLに `.php` を付けていないか
11. エラー時にスタックトレースや接続情報が漏れないか
12. E2E テストにセキュリティテストを追加したか
