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
| `requireTournamentId()` | GETの`id`を検証（≤0も拒否） |
| `requireTournamentWithMeta($id)` | 大会をメタ付きで取得 + 見つからなければ404 |
| `consumeFlash()` | フラッシュメッセージを取得して削除 |
| `buildRuleTags($meta)` | 大会ルールタグ配列を生成 |
| `h($value)` | HTMLエスケープ（string/int/float対応） |

## 出力エスケープ

- HTML出力: `h($value)` を使う
- JavaScript内にPHP変数を埋め込む: `json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)`
- `JSON_HEX_TAG` を忘れると `</script>` によるXSSが成立する

## SQLインジェクション防止

- ユーザー入力を含むクエリは必ずプリペアドステートメントを使う
- 固定クエリ（ユーザー入力なし）のみ `$pdo->query()` を使用可
- `PDO::ATTR_EMULATE_PREPARES => false` でネイティブプリペアド強制済み

### LIKE / ILIKE 検索のワイルドカードエスケープ

ユーザー入力を `LIKE` / `ILIKE` で検索するとき、`%` `_` `\` をリテラル化しないと意図しない検索になる。
**`ESCAPE '\\'`（バックスラッシュ）を使うと PDO の `?` プレースホルダパーサが文字列リテラル内のバックスラッシュで誤動作（SQLSTATE[HY093]）する**。`|` などバックスラッシュ以外をエスケープ文字に指定する。

```php
// '|' をエスケープ文字として | % _ をリテラル化
$like = '%' . strtr($keyword, ['|' => '||', '%' => '|%', '_' => '|_']) . '%';
$stmt = $pdo->prepare("SELECT ... WHERE name ILIKE ? ESCAPE '|'");
$stmt->execute([$like]);
```

参考実装: `models/TableInfo.php::buildSearchWhere()`

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
// POST成功時
$_SESSION['flash'] = '保存しました。';
regenerateCsrfToken();
header('Location: page?id=' . $id);
exit;

// リダイレクト先での読み取り
$flash = consumeFlash(); // 取得して即削除
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

## fetch で POST する JSON エンドポイント

`dispatch_dm.php` / `dispatch_schedule_dm.php` のように JS から fetch で叩くエンドポイントも、フォームと同じ `validatePost()`（CSRF + Turnstile）で検証する。`validateCsrfToken()` だけにしない（Turnstile を素通りする書き込み口になる）。

- JS 側は `window.appTurnstile`（`templates/footer.php` が本番のみ定義）からトークンを取得し、`cf-turnstile-response` として送る。`dispatch-dm.js` の `withTurnstileToken()` を参照
- Turnstile トークンは 1 回の検証で消費されるため、送信が失敗したら `window.appTurnstile.reset()` で取り直す
- ページ表示直後に裏で自動実行されるエンドポイントでは `regenerateCsrfToken()` を呼ばない（セッション ID とトークンが差し替わり、同じセッションで開いている他ページのフォームが 403 になる）

## Discord 送信（config/discord.php）

大会名・卓名・選手名・牌譜 URL は誰でも編集できる値。Discord へ送る前に必ず次を通す。

| 関数 | 用途 |
|---|---|
| `discordAllowedMentions($userIds)` | 送信ボディの `allowed_mentions`。未指定だと本文中の `@everyone` / `<@id>` がすべて通知になるため、全送信で必ず付ける。通知するのは連携済み選手の ID だけ |
| `discordMentionIds($rows)` | 行の配列から `discord_user_id` を取り出す（上の引数用） |
| `discordEscape($text)` | メッセージ本文・Embed の description に埋め込むユーザー入力の Markdown / メンション記法を無害化 |
| `discordLinkUrl($url)` | マスクリンク `[text](url)` に入れる URL。http(s) 以外は空文字（リンクにしない） |

- チャンネル投稿は `discordPostChannelMessage($channelId, $content, $mentionUserIds)` の第 3 引数で通知対象を渡す
- 新しい送信関数・メッセージ組み立て関数を足すときも同じ 4 つを使う

## サーバ側での状態・範囲の検証

画面の選択肢やボタンの出し分けは検証にならない（直接 POST できる）。取り消せない操作と、後続ページの負荷を決める値はサーバ側で確認する。

- 大会の完了: `Tournament::isFinalRoundDone()` が true の開催中の大会だけ（`interview_edit.php`）
- 卓作成: 卓内の重複選手・卓人数の上限・対局数（`MAX_GAME_COUNT`）・勝ち抜け人数を検証し、前ラウンド完了と同一ラウンドの二重作成は `TableInfo::createBatch()` が大会行をロックして確認する（違反時は `DomainException`）

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
7. POSTフォーム・fetch の POST エンドポイントとも `ensureCsrfToken` + `validatePost()`（CSRF + Turnstile）で検証しているか
8. POST成功後に PRG リダイレクト + `regenerateCsrfToken()` しているか
9. セッションは `startSecureSession()` で開始しているか
10. URLに `.php` を付けていないか
11. エラー時にスタックトレースや接続情報が漏れないか
12. E2E テストにセキュリティテストを追加したか
13. Discord へ送る文字列は `discordEscape()`、送信ボディは `discordAllowedMentions()` を通しているか
14. 取り消せない操作・ループ回数を決める値は、画面の出し分けに頼らずサーバ側で状態と範囲を検証しているか
