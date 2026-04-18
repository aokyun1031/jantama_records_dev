#!/usr/bin/env bash
# PreToolUse hook: git push 前に Playwright E2E を実行
# Docker (web) が起動していて node_modules がある場合のみ実行。どちらか欠けたらスキップ。
# 成功/スキップ時は additionalContext を stdout に返してモデルに伝える。

set -u

PROJ=$(git rev-parse --show-toplevel 2>/dev/null)
E2E="$PROJ/tests/e2e"

emit() {
  local msg="$1"
  printf '{"hookSpecificOutput":{"hookEventName":"PreToolUse","additionalContext":"%s"}}\n' "$msg"
}

# 前提チェック
if [ -z "$PROJ" ] || [ ! -d "$E2E/node_modules" ]; then
  emit "E2E tests skipped (environment not ready)"
  exit 0
fi

if ! docker compose ps --status running 2>/dev/null | grep -q web; then
  emit "E2E tests skipped (environment not ready)"
  exit 0
fi

# 実行
cd "$E2E" || { emit "E2E tests skipped (environment not ready)"; exit 0; }

if npx playwright test --reporter=line 2>&1; then
  emit "E2E tests all passed"
  exit 0
else
  # テスト失敗時は非ゼロ終了で push を止める
  emit "E2E tests failed - fix before pushing"
  exit 2
fi
