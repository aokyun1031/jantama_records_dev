<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();

// --- フラッシュメッセージ ---
$flash = consumeFlash();

// --- 大会種別フィルタ: GET パラメータを EventType で検証 ---
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
$isFiltered = !empty($selectedEventTypes);

// --- データ取得 ---
['data' => $allTournaments, 'error' => $error] = fetchData(fn() => Tournament::allWithDetails());
$totalCount = count($allTournaments ?? []);

// 各大会のメタ情報を一括取得
$tournamentIds = array_map(fn($t) => (int) $t['id'], $allTournaments ?? []);
$tournamentMetas = !empty($tournamentIds)
    ? TournamentMeta::allByTournamentIds($tournamentIds)
    : [];

// フィルタ適用
$tournaments = $allTournaments ?? [];
if ($isFiltered) {
    $tournaments = array_values(array_filter(
        $tournaments,
        fn($t) => in_array((string) ($t['event_type'] ?? ''), $selectedEventTypes, true)
    ));
}
$matchedCount = count($tournaments);

// --- テンプレート変数 ---
$pageTitle = '大会一覧 - ' . SITE_NAME;
$pageDescription = '麻雀トーナメントの大会一覧です。';
$pageCss = ['css/forms.css', 'css/tournaments.css'];
$pageScripts = ['js/tournaments.js'];

require __DIR__ . '/../templates/header.php';
?>

<div class="page-hero">
  <div class="page-hero-badge">TOURNAMENTS</div>
  <h1 class="page-hero-title">大会一覧</h1>
  <div class="tournaments-count"><?= $totalCount ?> 件の大会が登録されています</div>
</div>

<?php if ($flash): ?>
  <div class="edit-message success"><?= h($flash) ?></div>
<?php endif; ?>

<?php if (!$error && $totalCount > 0): ?>
<div class="event-filter-wrap" role="region" aria-label="大会種別で絞り込み">
  <form method="get" id="event-filter-form" class="event-filter-form<?= $isFiltered ? ' is-active' : '' ?>">
    <div class="event-filter-header">
      <svg class="event-filter-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
      </svg>
      <span class="event-filter-title">フィルタ</span>
      <span class="event-filter-sub">大会種別</span>
      <?php if ($isFiltered): ?>
        <span class="event-filter-badge" aria-label="<?= count($selectedEventTypes) ?>件選択中"><?= count($selectedEventTypes) ?></span>
      <?php endif; ?>
      <div class="event-filter-actions">
        <?php if ($isFiltered): ?>
          <a href="tournaments" class="event-filter-clear" aria-label="フィルタをクリア">
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
  </span>
  <span class="filter-context-count"><?= $matchedCount ?>件 / <?= $totalCount ?>件中</span>
</div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="tournaments-error">
    <div class="tournaments-error-label">データベース接続エラー</div>
    <div class="tournaments-error-detail">一時的にデータを取得できません。しばらくしてから再度お試しください。</div>
  </div>
<?php elseif ($totalCount === 0): ?>
  <div class="tournaments-empty">大会がまだ登録されていません。</div>
<?php elseif ($matchedCount === 0): ?>
  <div class="tournaments-empty">選択した大会種別に該当する大会はありません。「クリア」を押すと全ての大会が表示されます。</div>
<?php else: ?>
  <div class="tournaments-list">
    <?php foreach ($tournaments as $i => $t): ?>
      <div class="tournament-card" style="animation-delay: <?= $i * 0.05 ?>s">
        <div class="tournament-card-body">
          <?php $mRuleTags = buildRuleTags($tournamentMetas[(int) $t['id']] ?? []); ?>
          <div class="tournament-card-header">
            <span class="tournament-status <?= (TournamentStatus::tryFrom($t['status']))?->cssClass() ?? '' ?>"><?= h((TournamentStatus::tryFrom($t['status']))?->label() ?? $t['status']) ?></span>
            <span class="tournament-name"><?= h($t['name']) ?></span>
          </div>
          <div class="tournament-rules">
            <?php foreach ($mRuleTags as $tag): ?><span><?= h($tag) ?></span><?php endforeach; ?>
          </div>
          <div class="tournament-info">
            <?php if ($t['start_date'] && $t['end_date']): ?>
              <span class="tournament-info-item"><?= h(date('Y/m/d', strtotime($t['start_date']))) ?> ～ <?= h(date('Y/m/d', strtotime($t['end_date']))) ?></span>
            <?php elseif ($t['start_date']): ?>
              <span class="tournament-info-item"><?= h(date('Y/m/d', strtotime($t['start_date']))) ?></span>
            <?php endif; ?>
            <span class="tournament-info-item"><?= (int) $t['player_count'] ?>名参加</span>
            <?php if ($t['status'] === TournamentStatus::Completed->value && !empty($t['winner_name'])): ?>
              <span class="tournament-info-item tournament-winner">&#x1F451; <?= h($t['winner_name']) ?></span>
            <?php endif; ?>
          </div>
          <div class="tournament-links">
            <?php if ($t['status'] === TournamentStatus::Preparing->value): ?>
              <span class="tournament-link tournament-link--view disabled">&#x1F441; 閲覧ページ</span>
            <?php else: ?>
              <a href="tournament_view?id=<?= (int) $t['id'] ?>" class="tournament-link tournament-link--view">&#x1F441; 閲覧ページ</a>
            <?php endif; ?>
            <a href="tournament?id=<?= (int) $t['id'] ?>" class="tournament-link tournament-link--admin">&#x2699; 管理ページ</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="tournaments-actions">
  <a href="/" class="btn-cancel">&#x2190; トップページに戻る</a>
  <a href="tournament_new" class="btn-cancel" style="background:var(--btn-secondary-bg);color:var(--btn-text-color);border-color:transparent;box-shadow:0 4px 16px rgba(var(--mint-rgb),0.3);">+ 大会を作成</a>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
