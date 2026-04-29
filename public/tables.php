<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();

$flash = consumeFlash();

// --- 大会種別フィルタ ---
$rawEventTypes = filter_input(INPUT_GET, 'event_types', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
if (!is_array($rawEventTypes)) {
    $rawEventTypes = [];
}
$selectedEventTypes = [];
foreach ($rawEventTypes as $v) {
    if (is_string($v) && EventType::tryFrom($v) !== null && !in_array($v, $selectedEventTypes, true)) {
        $selectedEventTypes[] = $v;
    }
}

// --- 状態フィルタ ---
$statusOptions = ['all' => 'すべて', 'pending' => '未完了', 'done' => '完了'];
$status = filter_input(INPUT_GET, 'status') ?? 'all';
if (!isset($statusOptions[$status])) {
    $status = 'all';
}

// --- 検索キーワード ---
$keyword = trim((string) (filter_input(INPUT_GET, 'q') ?? ''));
if (mb_strlen($keyword) > 100) {
    $keyword = mb_substr($keyword, 0, 100);
}

$isFiltered = !empty($selectedEventTypes) || $status !== 'all' || $keyword !== '';

$filters = [
    'event_types' => $selectedEventTypes,
    'status'      => $status,
    'keyword'     => $keyword,
];

['data' => $totalCount, 'error' => $errorCount] = fetchData(
    fn() => TableInfo::searchAllCount($filters)
);
$totalCount = $totalCount ?? 0;

// --- ページネーション ---
$perPage = 10;
['page' => $page, 'totalPages' => $totalPages, 'offset' => $offset] = paginate($totalCount, $perPage);

['data' => $tables, 'error' => $error] = fetchData(
    fn() => TableInfo::searchAll($filters, $perPage, $offset)
);
$tables = $tables ?? [];
$error = $error ?? $errorCount;

// --- ページネーション URL ヘルパ ---
$baseQuery = [];
foreach ($selectedEventTypes as $v) {
    $baseQuery['event_types'][] = $v;
}
if ($status !== 'all') {
    $baseQuery['status'] = $status;
}
if ($keyword !== '') {
    $baseQuery['q'] = $keyword;
}
$pageUrl = function (int $p) use ($baseQuery): string {
    $q = $baseQuery;
    if ($p > 1) {
        $q['page'] = $p;
    }
    return 'tables' . (empty($q) ? '' : '?' . http_build_query($q));
};

// --- テンプレート変数 ---
$pageTitle = '卓一覧 - ' . SITE_NAME;
$pageDescription = '全大会の卓一覧。大会種別・状態・キーワードで絞り込めます。';
$pageCss = ['css/forms.css', 'css/filters.css', 'css/tables.css'];
$pageScripts = ['js/filter-form.js'];

require __DIR__ . '/../templates/header.php';
?>

<div class="page-hero">
  <div class="page-hero-badge">TABLES</div>
  <h1 class="page-hero-title">卓一覧</h1>
  <div class="tables-count"><?= (int) $totalCount ?> 卓<?= $isFiltered ? '（絞込中）' : '' ?></div>
</div>

<?php if ($flash): ?>
  <div class="edit-message success"><?= h($flash) ?></div>
<?php endif; ?>

<?php if (!$error): ?>
<div class="event-filter-wrap" role="region" aria-label="卓を絞り込み">
  <form method="get" id="event-filter-form" class="event-filter-form<?= $isFiltered ? ' is-active' : '' ?>" action="tables">
    <div class="event-filter-header">
      <svg class="event-filter-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
      </svg>
      <span class="event-filter-title">フィルタ</span>
      <span class="event-filter-sub">卓検索</span>
      <?php if ($isFiltered): ?>
        <span class="event-filter-badge" aria-label="絞込中">絞込中</span>
      <?php endif; ?>
      <div class="event-filter-actions">
        <?php if ($isFiltered): ?>
          <a href="tables" class="event-filter-clear" aria-label="フィルタをクリア">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
            クリア
          </a>
        <?php endif; ?>
        <button type="submit" class="event-filter-submit" data-pending-count="0" hidden>
          適用<span class="event-filter-submit-count" aria-hidden="true"></span>
        </button>
      </div>
    </div>

    <div class="tables-search-row">
      <label class="tn-visually-hidden" for="tables-search-input">キーワード検索</label>
      <input type="search" id="tables-search-input" name="q" class="tables-search-input"
             value="<?= h($keyword) ?>" placeholder="大会名・卓名・選手名で検索" maxlength="100" autocomplete="off">
      <select name="status" id="tables-status-select" class="tables-status-select" aria-label="状態">
        <?php foreach ($statusOptions as $val => $lab): ?>
          <option value="<?= h($val) ?>"<?= $status === $val ? ' selected' : '' ?>><?= h($lab) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="event-filter-chips" role="group" aria-label="大会種別">
      <?php foreach (EventType::cases() as $type):
        $checked = in_array($type->value, $selectedEventTypes, true);
      ?>
      <label class="event-chip<?= $checked ? ' is-selected' : '' ?>">
        <input type="checkbox" name="event_types[]" value="<?= h($type->value) ?>"<?= $checked ? ' checked' : '' ?>>
        <span class="event-chip-text"><?= h($type->label()) ?></span>
      </label>
      <?php endforeach; ?>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if ($isFiltered && !$error): ?>
<div class="filter-context" role="status" aria-live="polite">
  <span class="filter-context-label">絞込</span>
  <span class="filter-context-types">
    <?php foreach ($selectedEventTypes as $v): ?>
      <span class="filter-context-tag"><?= h(EventType::from($v)->label()) ?></span>
    <?php endforeach; ?>
    <?php if ($status !== 'all'): ?>
      <span class="filter-context-tag filter-context-tag--status"><?= h($statusOptions[$status]) ?></span>
    <?php endif; ?>
    <?php if ($keyword !== ''): ?>
      <span class="filter-context-tag filter-context-tag--keyword">&#x1F50D; <?= h($keyword) ?></span>
    <?php endif; ?>
  </span>
  <span class="filter-context-count"><?= (int) $totalCount ?>件</span>
</div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="list-error">
    <div class="list-error-label">データベース接続エラー</div>
    <div class="list-error-detail">一時的にデータを取得できません。しばらくしてから再度お試しください。</div>
  </div>
<?php elseif ($totalCount === 0): ?>
  <div class="list-empty"><?= $isFiltered ? '条件に該当する卓はありません。' : '卓がまだ登録されていません。' ?></div>
<?php else: ?>
  <div class="tables-list">
    <?php foreach ($tables as $i => $row): ?>
      <?php
        $eventTypeLabel = $row['event_type'] !== '' ? (EventType::tryFrom($row['event_type'])?->label() ?? '') : '';
        $schedText = '';
        if (!empty($row['played_date'])) {
            $d = new DateTime($row['played_date']);
            $schedText = (int) $d->format('Y') . '/' . (int) $d->format('n') . '/' . (int) $d->format('j');
            if (!empty($row['day_of_week'])) {
                $schedText .= '（' . mb_substr($row['day_of_week'], 0, 1) . '）';
            }
            if (!empty($row['played_time'])) {
                $schedText .= ' ' . substr($row['played_time'], 0, 5);
            }
        }
      ?>
      <?php
        $cardLabel = sprintf(
            '%s %d回戦 %s を開く',
            $row['tournament_name'],
            (int) $row['round_number'],
            $row['table_name']
        );
      ?>
      <div class="table-card<?= $row['done'] ? ' is-done' : '' ?>" style="animation-delay: <?= $i * 0.03 ?>s">
        <div class="table-card-header">
          <span class="table-card-status <?= $row['done'] ? 'done' : 'pending' ?>"><?= $row['done'] ? '完了' : '未完了' ?></span>
          <a href="tournament_view?id=<?= (int) $row['tournament_id'] ?>" class="table-card-tournament">
            <?php if ($eventTypeLabel !== ''): ?>
              <span class="table-card-event"><?= h($eventTypeLabel) ?></span>
            <?php endif; ?>
            <span class="table-card-tname"><?= h($row['tournament_name']) ?></span>
          </a>
        </div>

        <div class="table-card-meta">
          <span class="table-card-round"><?= (int) $row['round_number'] ?>回戦</span>
          <a href="table?id=<?= (int) $row['table_id'] ?>" class="table-card-name table-card-name--link" aria-label="<?= h($cardLabel) ?>"><?= h($row['table_name']) ?></a>
          <?php if ($schedText !== ''): ?>
            <span class="table-card-sched"><?= h($schedText) ?></span>
          <?php else: ?>
            <span class="table-card-sched table-card-sched--empty">日程未設定</span>
          <?php endif; ?>
        </div>

        <div class="table-card-players">
          <?php foreach ($row['players'] as $p): ?>
            <a href="player?id=<?= (int) $p['player_id'] ?>" class="table-card-player">
              <?= charaIcon($p['icon'], 28) ?>
              <span class="table-card-player-name"><?= h($p['name']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php require __DIR__ . '/../templates/partials/list-pagination.php'; ?>
<?php endif; ?>

<div class="list-actions">
  <a href="/" class="btn-cancel">&#x2190; トップページに戻る</a>
  <a href="tournaments" class="btn-cancel">大会一覧へ</a>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
