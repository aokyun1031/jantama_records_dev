#!/usr/bin/env bash
# PostToolUse hook: Edit/Write した .php ファイルを `php -l` で構文チェック。
# 構文エラーがあれば decision=block で差し戻す。

set -u

# tool_input.file_path または tool_response.filePath を取得
FILE=$(jq -r '.tool_response.filePath // .tool_input.file_path // empty')

# PHP ファイル以外は無視
if [ -z "$FILE" ] || [[ "$FILE" != *.php ]]; then
  exit 0
fi

if [ ! -f "$FILE" ]; then
  exit 0
fi

# Docker 経由（web コンテナ）か ローカル php を優先順位で試す
if command -v php >/dev/null 2>&1; then
  OUT=$(php -l "$FILE" 2>&1)
elif docker compose ps --status running 2>/dev/null | grep -q web; then
  OUT=$(docker compose exec -T web php -l "$FILE" 2>&1)
else
  exit 0
fi

if echo "$OUT" | grep -q "No syntax errors"; then
  exit 0
fi

# 構文エラー: block + reason で差し戻す
jq -nc --arg reason "PHP syntax error in $FILE:"$'\n'"$OUT" \
  '{decision: "block", reason: $reason}'
exit 0
