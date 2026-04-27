<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();

// --- フラッシュメッセージ ---
$flash = consumeFlash();

// --- データ取得 ---
['data' => $tournaments, 'error' => $error] = fetchData(fn() => Tournament::allWithDetails());

// 各大会のメタ情報を一括取得
$tournamentIds = array_map(fn($t) => (int) $t['id'], $tournaments ?? []);
$tournamentMetas = !empty($tournamentIds)
    ? TournamentMeta::allByTournamentIds($tournamentIds)
    : [];

// ステータスラベル

// --- テンプレート変数 ---
$pageTitle = '大会一覧 - ' . SITE_NAME;
$pageDescription = '麻雀トーナメントの大会一覧です。';
$pageCss = ['css/forms.css', 'css/tournaments.css'];

require __DIR__ . '/../templates/header.php';
?>

<div class="page-hero">
  <div class="page-hero-badge">TOURNAMENTS</div>
  <h1 class="page-hero-title">大会一覧</h1>
  <div class="tournaments-count"><?= count($tournaments ?? []) ?> 件の大会が登録されています</div>
</div>

<?php if ($flash): ?>
  <div class="edit-message success"><?= h($flash) ?></div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="tournaments-error">
    <div class="tournaments-error-label">データベース接続エラー</div>
    <div class="tournaments-error-detail">一時的にデータを取得できません。しばらくしてから再度お試しください。</div>
  </div>
<?php elseif (empty($tournaments)): ?>
  <div class="tournaments-empty">大会がまだ登録されていません。</div>
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
