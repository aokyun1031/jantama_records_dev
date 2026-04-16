---
description: public/ 配下のリファクタ。引数なしでヘルスチェック、引数ありで指定対象を修正
argument-hint: "[target-path]"
allowed-tools: Bash, Read, Edit, Grep, Glob
---

`refactor` スキル（`.claude/skills/refactor/SKILL.md`）を参照して作業する。
`conventions.md` も併読すること。

## 引数の扱い

ユーザーが渡した引数: `$ARGUMENTS`

### 引数が空のとき → **チェックモード**（読み取り専用）

1. 以下を実行して結果をユーザーに提示する:

```bash
bash .claude/skills/refactor/scripts/scan.sh
```

2. レポートを要約する（何件の CRITICAL / WARNING / INFO があるか、主要な違反パターン）

3. **ファイルを変更しない**。ユーザーに次のアクションを提案する:
   - CRITICAL がある → 「先に直しますか？対象ファイルを指定してください」
   - WARNING のみ → 「まとめて直す場合は `/refactor <対象>` で実行してください」
   - INFO のみ → 「今の段階では見送って問題ない規模です」
   - 何もない → 「ドリフトは検出されませんでした」

### 引数があるとき → **修正モード**

引数を対象として受け取る。例: `/refactor public/player.php` や `/refactor public/css/`

1. まず対象に限定して scan.sh を実行:

```bash
bash .claude/skills/refactor/scripts/scan.sh --target $ARGUMENTS
```

2. 検出された違反に対して、**修正計画をユーザーに提示**する:
   - 何件・どの種別を直すか
   - どのファイルを触るか
   - テーマを 1 つに絞っているか（混在させない）

3. **ユーザーの合意を得てから** Edit / MultiEdit で修正を実施する。

4. 修正後に再度 scan.sh を実行し、期待通り件数が減ったか確認する。

5. 意図しない差分が入っていないか `git diff` で確認する。

## 守るべき原則

- **1 PR = 1 テーマ**: 型キャスト修正とロジック変更を混ぜない
- **scan.sh が自動検出しない規約**は `conventions.md` を参照し、必要なら手動レビュー
- **E2E テスト**は `git push` 時の hook で自動実行される。push 前に確認は不要
- **CRITICAL** の修正はセキュリティ直結。見送らず即対応を提案する
- 修正対象ファイルの周辺パターン（既存の書き方）を壊さない

## 使ってよいツール

- `Bash`: scan.sh / git コマンド
- `Read` / `Grep` / `Glob`: 対象ファイルの文脈確認
- `Edit`: 1 箇所ずつの確実な修正（PSR-12 のスペース挿入等）
- `MultiEdit`: 同一ファイル内の複数箇所を一括で修正する場合

## 使わないツール

- `Write` で大規模書き換えはしない（部分置換に留める）
- `Agent` は原則不要（このコマンド内で完結できる規模を想定）
- どうしても大量のリファクタで文脈が逼迫する場合のみ、`refactoring-expert` subagent に委譲
