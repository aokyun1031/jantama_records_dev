---
name: deploy
description: 本番デプロイの確認とトラブルシュート
disable-model-invocation: true
---

本番デプロイの状況を確認し、問題があれば対処する。

1. `git status` で未コミットの変更を確認
2. `git log --oneline -5` で最近のコミットを確認
3. Render は main ブランチへの push で自動デプロイされる
4. デプロイ時に `start.sh` が実行され、Phinxマイグレーションが自動適用される
5. 問題がある場合は Render のデプロイログを確認するよう案内する

注意事項:
- 本番DBはNeon production ブランチ
- `start.sh` で `phinx.php` が無い場合は `phinx.php.example` から自動コピーされる
- マイグレーションが失敗するとApacheが起動しない（`set -e`）
