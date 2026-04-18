---
description: public/ 配下の PHP ファイルを php-reviewer agent に委譲してセキュリティ・規約違反を検出
argument-hint: "[target-path]"
allowed-tools: Agent, Bash, Read, Grep, Glob
---

`php-reviewer` subagent（`.claude/agents/php-reviewer.md`）に委譲してレビューする。**検出のみ、自動修正はしない。**

## 引数

ユーザーが渡した引数: `$ARGUMENTS`

### 引数が空のとき

`public/` 配下の全 PHP ファイルが対象。ファイル数が多い場合は `public/*.php`（ページ層）のみに絞ってよい。

### 引数があるとき

指定されたファイル／ディレクトリが対象。例: `/security-check public/player_edit.php`、`/security-check public/tournament*.php`

## 実行手順

1. Agent tool で `subagent_type: "php-reviewer"` を指定して起動
2. prompt には対象ファイル一覧と「`.claude/agents/php-reviewer.md` の検査項目に従ってレビュー」を明示
3. subagent の出力をそのままユーザーに提示する（要約しすぎない。行番号と違反内容は残す）
4. 🔴 必須違反が検出された場合、**修正計画をユーザーに提示して合意を得てから**修正を行う
5. 修正後に再度 `/security-check` を実行して違反ゼロを確認

## 守るべき原則

- subagent の指摘を鵜呑みにしない。行番号の内容を Read で確認してから修正する
- 指摘の誤検出（既存の正しいパターンを違反と誤認）があれば、修正せずユーザーに報告
- 1 PR = 1 テーマ。セキュリティ修正とロジック変更を混ぜない
- マージ前・新規ページ追加後・フォーム改修後に定期実行を推奨
