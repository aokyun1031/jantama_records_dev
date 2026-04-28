<?php

declare(strict_types=1);

/**
 * HTMLエスケープのショートカット。
 */
function h(string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * 静的ファイルのパスにキャッシュバスティング用のクエリパラメータを付与する。
 */
function asset(string $path): string
{
    $file = __DIR__ . '/../public/' . $path;
    $v = file_exists($file) ? filemtime($file) : 0;
    return htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '?v=' . $v;
}

/**
 * キャラクターアイコンのHTMLを返す。アイコンがない場合は「NO IMG」プレースホルダーを返す。
 *
 * @param int $size ピクセルサイズ（width/height）
 */
function charaIcon(?string $icon, int $size = 28, string $class = ''): string
{
    if ($icon) {
        return '<img src="img/chara_deformed/' . h($icon) . '" alt="" width="' . $size . '" height="' . $size . '"'
            . ' class="chara-icon' . ($class !== '' ? ' ' . h($class) : '') . '"'
            . ' loading="lazy">';
    }
    $fs = max(0.4, round($size * 0.016, 2));
    return '<span class="chara-icon-none' . ($class !== '' ? ' ' . h($class) : '') . '"'
        . ' style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . $fs . 'rem"'
        . '>NO<br>IMG</span>';
}

/**
 * 大会メタ情報からルールタグ配列を生成する。
 */
function buildRuleTags(array $meta): array
{
    $tags = [];
    $eventType = EventType::tryFrom($meta['event_type'] ?? '');
    if ($eventType) {
        $tags[] = $eventType->label();
    }
    $tags[] = (PlayerMode::tryFrom($meta['player_mode'] ?? '4'))?->label() ?? '';
    $tags[] = (RoundType::tryFrom($meta['round_type'] ?? 'hanchan'))?->label() ?? '';
    if (!empty($meta['thinking_time'])) {
        $tags[] = $meta['thinking_time'] . '秒';
    }
    if (!empty($meta['starting_points'])) {
        $tags[] = '原点' . $meta['starting_points'];
    }
    if (!empty($meta['return_points'])) {
        $tags[] = '返し' . $meta['return_points'];
    }
    if (isset($meta['red_dora'])) {
        $tags[] = '赤' . $meta['red_dora'];
    }
    $tags[] = (ToggleRule::tryFrom($meta['open_tanyao'] ?? '1'))?->label('食い断') ?? '';
    $hanLabel = (HanRestriction::tryFrom($meta['han_restriction'] ?? ''))?->label();
    if ($hanLabel) {
        $tags[] = $hanLabel;
    }
    $tags[] = (ToggleRule::tryFrom($meta['bust'] ?? '0'))?->label('飛び') ?? '';
    return array_filter($tags, fn($t) => $t !== '');
}

/**
 * 入力文字列からURLを抽出する。
 * 「雀魂牌譜: https://...」のようなプレフィックス付きテキストからURLだけを取り出す。
 */
function extractUrl(string $input): string
{
    if (preg_match('/(https?:\/\/\S+)/', $input, $m)) {
        return $m[1];
    }
    return $input;
}

/**
 * ページネーションのページ番号を GET から取得し、件数に応じてクランプする。
 *
 * 一覧ページ用ヘルパ。GET の `page` を整数として検証し、
 * 1 未満は 1 に、`totalPages` を超えるものは `totalPages` にクランプする。
 *
 * @return array{page:int, totalPages:int, offset:int}
 */
function paginate(int $totalCount, int $perPage): array
{
    // 0 や負数が来た場合の防御。本来は呼び出し側のバグだが 500 は避ける。
    $perPage = max(1, $perPage);
    $page = (int) (filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
    if ($page < 1) {
        $page = 1;
    }
    $totalPages = $totalCount > 0 ? (int) ceil($totalCount / $perPage) : 1;
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    return [
        'page'       => $page,
        'totalPages' => $totalPages,
        'offset'     => ($page - 1) * $perPage,
    ];
}
