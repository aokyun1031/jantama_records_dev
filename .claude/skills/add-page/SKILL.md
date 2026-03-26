---
name: add-page
description: 新しいPHPページを既存のパターンに従って作成する
---

新しいページを作成する際の手順:

1. `public/players.php` を参考にする（DB接続ありのページの標準パターン）
2. `public/` 配下にファイルを作成
3. 先頭で `require __DIR__ . '/../config/database.php';` を読み込む
4. テンプレートを使用:
   - `require __DIR__ . '/../templates/header.php';`（`$pageTitle` と `$pageStyle` を事前に設定）
   - `require __DIR__ . '/../templates/footer.php';`
5. 出力は `htmlspecialchars()` でエスケープ
6. `.htaccess` の URL書き換えにより `/pagename` で `.php` なしアクセス可能
