#!/usr/bin/env bash
# scan.sh - CLAUDE.md 規約ドリフトを public/ に対して検出する
# 読み取り専用。副作用なし。終了コードは常に 0（これはレポートツール）。
#
# 使い方:
#   bash scan.sh                  # 人が読めるレポート
#   bash scan.sh --summary        # 件数サマリーのみ
#   bash scan.sh --target <path>  # 特定ファイル/ディレクトリに限定

set -uo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || {
  echo "Error: not inside a git repository" >&2
  exit 1
}
cd "$REPO_ROOT"

TARGET="public"
MODE="full"
while [ $# -gt 0 ]; do
  case "$1" in
    --summary) MODE="summary"; shift ;;
    --target)  TARGET="${2:-public}"; shift 2 ;;
    *) echo "Unknown option: $1" >&2; exit 2 ;;
  esac
done

if [ ! -e "$TARGET" ]; then
  echo "Error: target not found: $TARGET" >&2
  exit 1
fi

PHP_FILES=$(find "$TARGET" -type f -name '*.php' 2>/dev/null | sort)
JS_FILES=$(find "$TARGET" -type f -name '*.js' 2>/dev/null | sort)
CSS_FILES=$(find "$TARGET" -type f -name '*.css' 2>/dev/null | sort)

# 対象外ファイル（参照無しの旧ページ等。詳細は conventions.md を参照）
EXCLUDE_FILES=(
  "public/index_legacy.php"
)
for excl in "${EXCLUDE_FILES[@]}"; do
  PHP_FILES=$(printf "%s\n" "$PHP_FILES" | grep -vxF "$excl" || true)
  JS_FILES=$(printf "%s\n" "$JS_FILES" | grep -vxF "$excl" || true)
  CSS_FILES=$(printf "%s\n" "$CSS_FILES" | grep -vxF "$excl" || true)
done

# Critical CSS マーカーを含むファイルは I1/I2/I7 のハードコード/外部化チェックから除外する
# （Loader 等で「外部 CSS 到着前に描画必要」な意図的な例外。CLAUDE.md 参照）
PHP_FILES_NON_CRITICAL=()
for f in $PHP_FILES; do
  if ! grep -q "Critical CSS" "$f" 2>/dev/null; then
    PHP_FILES_NON_CRITICAL+=("$f")
  fi
done

[ -z "$PHP_FILES" ] && [ -z "$JS_FILES" ] && [ -z "$CSS_FILES" ] && {
  echo "No PHP/JS/CSS files found in $TARGET"
  exit 0
}

CRITICAL=0
WARNING=0
INFO=0
CRITICAL_LINES=""
WARNING_LINES=""
INFO_LINES=""

if [ -t 1 ]; then
  red=$'\e[31m'; yel=$'\e[33m'; cyn=$'\e[36m'; grn=$'\e[32m'; gry=$'\e[90m'; bld=$'\e[1m'; rst=$'\e[0m'
else
  red=""; yel=""; cyn=""; grn=""; gry=""; bld=""; rst=""
fi

hit() {
  local sev="$1" file="$2" line="$3" msg="$4"
  case "$sev" in
    critical) CRITICAL=$((CRITICAL+1))
      CRITICAL_LINES="$CRITICAL_LINES
${red}[CRIT]${rst} $file:$line  $msg" ;;
    warning)  WARNING=$((WARNING+1))
      WARNING_LINES="$WARNING_LINES
${yel}[WARN]${rst} $file:$line  $msg" ;;
    info)     INFO=$((INFO+1))
      INFO_LINES="$INFO_LINES
${cyn}[INFO]${rst} $file:$line  $msg" ;;
  esac
}

# Helper: entry を file, line, content に分解
parse_entry() {
  _pe_file="${1%%:*}"
  local rest="${1#*:}"
  _pe_line="${rest%%:*}"
  _pe_content="${rest#*:}"
}

# =============================================================
# PHP: CRITICAL（セキュリティ / 規約の根幹）
# =============================================================

# C1: declare(strict_types=1) が先頭にない
for f in $PHP_FILES; do
  if ! head -n 5 "$f" | grep -qE '^declare\(strict_types=1\);'; then
    hit critical "$f" 1 "declare(strict_types=1) が先頭 5 行以内に無い"
  fi
done

# C2: new PDO( 直接生成
while IFS=: read -r file line _; do
  [ -z "$file" ] && continue
  hit critical "$file" "$line" "new PDO 直接生成は禁止。getDbConnection() を使う"
done < <(grep -HnE 'new\s+PDO\s*\(' $PHP_FILES 2>/dev/null)

# C3: session_start( 直接呼び出し
while IFS=: read -r file line _; do
  [ -z "$file" ] && continue
  hit critical "$file" "$line" "session_start() 直接呼び出し禁止。startSecureSession() を使う"
done < <(grep -HnE 'session_start\s*\(' $PHP_FILES 2>/dev/null)

# C4: SQL を public/ 内で実行している
while IFS=: read -r file line _; do
  [ -z "$file" ] && continue
  hit critical "$file" "$line" "public/ 内に DB 操作。models/ に移す"
done < <(grep -HnE '->\s*(prepare|query|exec)\s*\(' $PHP_FILES 2>/dev/null)

# C5: json_encode の呼び出しごとに JSON_HEX_TAG を確認
while IFS=: read -r file ln; do
  [ -z "$file" ] && continue
  hit critical "$file" "$ln" "json_encode に JSON_HEX_TAG フラグが無い（XSS 対策）"
done < <(
  for f in $PHP_FILES; do
    awk -v file="$f" '
      /json_encode\s*\(/ {
        buf = $0; line = NR
        if (buf ~ /JSON_HEX_TAG/) next
        while ((getline nextline) > 0) {
          buf = buf nextline
          if (nextline ~ /JSON_HEX_TAG/) { buf = ""; break }
          if (nextline ~ /\);/ || nextline ~ /;/) break
        }
        if (buf != "" && buf !~ /JSON_HEX_TAG/) print file ":" line
      }
    ' "$f" 2>/dev/null
  done
)

# C6: <script> タグが public/*.php に直接存在
while IFS=: read -r file line _; do
  [ -z "$file" ] && continue
  hit critical "$file" "$line" "<script> 直書き禁止。\$pageInlineScript を使う"
done < <(grep -HnE '<script[\s>]' $PHP_FILES 2>/dev/null)

# =============================================================
# PHP: WARNING（スタイル / 一貫性）
# =============================================================

# W1: 型キャスト後にスペースが無い  (int)$x  →  (int) $x
while IFS=: read -r file line _; do
  [ -z "$file" ] && continue
  hit warning "$file" "$line" "型キャスト後にスペース必須。(int) \$var に修正 (PSR-12)"
done < <(grep -HnE '\([a-z]+\)\$[a-zA-Z_]' $PHP_FILES 2>/dev/null)

# W2: 内部リンクに .php 拡張子
while IFS= read -r entry; do
  [ -z "$entry" ] && continue
  parse_entry "$entry"
  echo "$_pe_content" | grep -qE 'https?://' && continue
  hit warning "$_pe_file" "$_pe_line" "内部リンクに .php 付き。.htaccess で POST が消える"
done < <(grep -HnE '(href|action)="[^"]*\.php' $PHP_FILES 2>/dev/null)

# W3: <img> タグで width / height / alt のいずれかが欠落
while IFS=: read -r file ln; do
  [ -z "$file" ] && continue
  hit warning "$file" "$ln" "<img> に width/height/alt のいずれかが欠落"
done < <(
  for f in $PHP_FILES; do
    awk -v file="$f" '
      BEGIN { in_img=0; buf=""; line=0 }
      /<img[[:space:]]/ { in_img=1; buf=""; line=NR }
      in_img {
        buf = buf $0
        if ($0 ~ />/) {
          if (buf !~ /width=/ || buf !~ /height=/ || buf !~ /alt=/) {
            print file ":" line
          }
          in_img=0; buf=""
        }
      }
    ' "$f" 2>/dev/null
  done
)

# W4: novalidate を form に付けている
while IFS=: read -r file line _; do
  [ -z "$file" ] && continue
  hit warning "$file" "$line" "<form novalidate> は禁止。HTML5 バリデーションを残す"
done < <(grep -HnE '<form[^>]*novalidate' $PHP_FILES 2>/dev/null)

# W5: インラインイベントハンドラ属性
while IFS=: read -r file line _; do
  [ -z "$file" ] && continue
  hit warning "$file" "$line" "インラインイベント属性禁止。addEventListener / data-confirm を使う"
done < <(grep -HnE '\s(onclick|onsubmit|onchange|oninput|onload|onkeyup|onkeydown|onblur|onfocus)=' $PHP_FILES 2>/dev/null)

# W6: POST 処理ページに validatePost() が無い
for f in $PHP_FILES; do
  if grep -qE "REQUEST_METHOD.*===.*'POST'" "$f" 2>/dev/null; then
    if ! grep -qE 'validatePost\s*\(' "$f" 2>/dev/null; then
      ln=$(grep -nE "REQUEST_METHOD.*===.*'POST'" "$f" | head -1 | cut -d: -f1)
      hit warning "$f" "${ln:-1}" "POST 処理に validatePost() が無い（CSRF + Turnstile 検証漏れ）"
    fi
  fi
done

# W7: <form method="post"> があるのに $pageTurnstile = true が無い
for f in $PHP_FILES; do
  if grep -qiE '<form[^>]*method=.post' "$f" 2>/dev/null; then
    if ! grep -qE '\$pageTurnstile\s*=' "$f" 2>/dev/null; then
      ln=$(grep -niE '<form[^>]*method=.post' "$f" | head -1 | cut -d: -f1)
      hit warning "$f" "${ln:-1}" "\$pageTurnstile = true が未設定（bot 対策漏れ）"
    fi
  fi
done

# W8: $_POST['key'] 直接アクセス（sanitizeInput / 安全パターンを除く）
while IFS= read -r entry; do
  [ -z "$entry" ] && continue
  parse_entry "$entry"
  # 安全パターンを除外
  echo "$_pe_content" | grep -qE 'isset\s*\(\s*\$_POST' && continue
  echo "$_pe_content" | grep -qE 'array_map\s*\(' && continue
  echo "$_pe_content" | grep -qE '\$_POST\[[^]]+\]\s*\?\?\s*\[' && continue
  echo "$_pe_content" | grep -qE 'preg_replace\s*\(' && continue
  echo "$_pe_content" | grep -qE 'sanitizeInput\s*\(' && continue
  hit warning "$_pe_file" "$_pe_line" "\$_POST 直接アクセス。sanitizeInput() を使う（配列は preg_replace で処理）"
done < <(grep -HnE '\$_POST\[' $PHP_FILES 2>/dev/null)

# =============================================================
# CSS: ハードコードカラー
# =============================================================

# I1: .php 内でハードコード hex カラー
while IFS=: read -r file line _; do
  [ -z "$file" ] && continue
  hit info "$file" "$line" "ハードコード hex カラー。CSS 変数に置換検討"
done < <(grep -HnE '#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b' "${PHP_FILES_NON_CRITICAL[@]}" 2>/dev/null \
    | grep -vE '(<\?=|href=|src=|id=|name=|<script|<!--|&#x)' \
    | grep -vE "['\"]#[0-9a-fA-F]+['\"]" )

# I2: .php 内で rgba(数値, ...) 形式
while IFS= read -r entry; do
  [ -z "$entry" ] && continue
  parse_entry "$entry"
  echo "$_pe_content" | grep -qE 'rgba\(\s*0\s*,\s*0\s*,\s*0\s*,' && continue
  echo "$_pe_content" | grep -qE 'rgba\(\s*255\s*,\s*255\s*,\s*255\s*,' && continue
  hit info "$_pe_file" "$_pe_line" "rgba(数値...) をハードコード。rgba(var(--X-rgb), α) を使う"
done < <(grep -HnE 'rgba\(\s*[0-9]+\s*,' "${PHP_FILES_NON_CRITICAL[@]}" 2>/dev/null \
    | grep -vE "['\"]rgba\(")

# =============================================================
# 未使用ファイル (dead code)
# =============================================================

# I3: public/js/ や public/css/ 内のファイルが参照されていない
for f in $JS_FILES $CSS_FILES; do
  base=$(basename "$f")
  refs=$(grep -rlE "[\"'/]${base//./\\.}([\"'? ]|$)" . \
    --include='*.php' --include='*.js' --include='*.css' --include='*.html' \
    --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git \
    --exclude-dir=tests --exclude-dir=cloudflare-worker --exclude-dir=db \
    2>/dev/null | grep -vF "./$f" || true)
  if [ -z "$refs" ]; then
    hit info "$f" 1 "どこからも参照されていない可能性（dead code 候補）"
  fi
done

# =============================================================
# 大きすぎるページ
# =============================================================

# I4: 800 行超の .php（CSS/JS は外部化済みの前提。判断フローは conventions.md 参照）
for f in $PHP_FILES; do
  lines=$(wc -l < "$f" 2>/dev/null || echo 0)
  if [ "$lines" -gt 800 ]; then
    hit info "$f" "$lines" "800 行超 ($lines 行)。まずロジックを models/ や config/ helper に切り出し、次に templates/partials/ 化を検討"
  fi
done

# I7: $pageStyle = <<<'CSS' ... CSS; の行数が多いページ（30 行超）
#     Loader 等 Critical CSS を除き、ページ固有 CSS は public/css/{page}.css に外出しする
for f in "${PHP_FILES_NON_CRITICAL[@]}"; do
  awk_result=$(awk '
    /\$pageStyle[ \t]*=[ \t]*<<<.?CSS.?/ { start=NR; inblock=1; next }
    inblock && /^CSS;/ { print start":"NR; inblock=0 }
  ' "$f" 2>/dev/null)
  [ -z "$awk_result" ] && continue
  while IFS= read -r range; do
    start_ln="${range%:*}"
    end_ln="${range#*:}"
    block_lines=$((end_ln - start_ln - 1))
    if [ "$block_lines" -gt 30 ]; then
      hit info "$f" "$start_ln" "\$pageStyle が ${block_lines} 行。public/css/$(basename "$f" .php).css に外部化検討"
    fi
  done <<< "$awk_result"
done

# =============================================================
# エスケープ漏れ候補
# =============================================================

# I5: <?= $array['key'] ?> で h() なし（XSS候補）
#     パイプラインで除外: h() / キャスト / ヘルパー関数 / 整数キー / 変数インデックス
while IFS= read -r entry; do
  [ -z "$entry" ] && continue
  parse_entry "$entry"
  hit info "$_pe_file" "$_pe_line" "<?= \$array['key'] ?> に h() 無し。文字列ならエスケープ必須"
done < <(
  grep -HnE '<\?=\s*\$[a-zA-Z_]+\[' $PHP_FILES 2>/dev/null \
  | grep -vE 'h\s*\(' \
  | grep -vE '\(int\)|\(float\)|\(bool\)' \
  | grep -vE 'cspNonce|json_encode|asset\(|number_format|htmlspecialchars|charaIcon|fmtScore|scoreCls' \
  | grep -vE "\['(id|[a-z_]*_id|count|total|rank|round|score|done|status|game_number|eliminated_round|player_count|player_mode|table_id|player_id|games|tops|avg_rank|top_rate)'\]" \
  | grep -vE '\$[a-zA-Z_]+\[\$[a-zA-Z_]' \
  | grep -vE '\?\?'
)

# I6: <?= $var ?> (単純変数) で h() なし
#     パイプラインで除外: h() / キャスト / 整数変数名 / サフィックスパターン / value属性
while IFS= read -r entry; do
  [ -z "$entry" ] && continue
  parse_entry "$entry"
  hit info "$_pe_file" "$_pe_line" "<?= \$var ?> に h() 無し。ユーザー由来文字列ならエスケープ必須"
done < <(
  grep -HnE '<\?=\s*\$[a-zA-Z_]+\s*\?>' $PHP_FILES 2>/dev/null \
  | grep -vE 'h\s*\(' \
  | grep -vE '\(int\)|\(float\)' \
  | grep -vE '<\?=\s*\$(i|j|k|g|n|m|idx|index|count|total|lines|len|num|rank|cnt|pct|cnt_[a-z]+)\s*\?>' \
  | grep -vE '<\?=\s*\$(id|pid|tid|tournamentId|playerId|tableId|round|nextRound|prevRound)\s*\?>' \
  | grep -vE '<\?=\s*\$(tournamentCount|playerCount|tableCount)\s*\?>' \
  | grep -vE '<\?=\s*\$[a-zA-Z_]*(Rounds?|Counts?|Scores?|Ranks?|Nums?|Idx|Ids?|Rates?|Width|Height|Totals?|Advances?|Done|Types?|Bar|Flags?|Number|Games?|Pct|Pass(es)?|Steps?|Sizes?)\s*\?>' \
  | grep -vE '<\?=\s*\$[a-zA-Z_]*(Html|Cls|Class|Css|Style|Icon|Labels?|Mode)\s*\?>' \
  | grep -vE '<\?=\s*\$[a-zA-Z_]*(Url|Href|Path|Src|href|url|path)\s*\?>' \
  | grep -vE 'value="<\?=\s*\$' \
  | grep -vE '<\?=\s*\$(elim|abs|barW|offset|limit|page)\s*\?>'
)

# =============================================================
# レポート出力
# =============================================================

if [ "$MODE" = "summary" ]; then
  echo "CRITICAL=$CRITICAL"
  echo "WARNING=$WARNING"
  echo "INFO=$INFO"
  exit 0
fi

echo
echo "${bld}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${rst}"
echo "${bld}  リファクタ・ドリフト検査レポート (target: $TARGET)${rst}"
echo "${bld}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${rst}"

if [ "$CRITICAL" -gt 0 ]; then
  echo
  echo "${red}${bld}🔴 CRITICAL ($CRITICAL 件): セキュリティ / 規約の根幹に関わる${rst}"
  echo "$CRITICAL_LINES"
fi

if [ "$WARNING" -gt 0 ]; then
  echo
  echo "${yel}${bld}🟡 WARNING ($WARNING 件): スタイル / 一貫性のドリフト${rst}"
  echo "$WARNING_LINES"
fi

if [ "$INFO" -gt 0 ]; then
  echo
  echo "${cyn}${bld}🔵 INFO ($INFO 件): 検討余地あり${rst}"
  echo "$INFO_LINES"
fi

echo
echo "${bld}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${rst}"
if [ "$CRITICAL" -eq 0 ] && [ "$WARNING" -eq 0 ] && [ "$INFO" -eq 0 ]; then
  echo "${grn}${bld}  ✅ ドリフトは検出されませんでした${rst}"
else
  echo "  合計: ${red}CRITICAL $CRITICAL${rst} / ${yel}WARNING $WARNING${rst} / ${cyn}INFO $INFO${rst}"
fi
echo "${bld}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${rst}"

exit 0
