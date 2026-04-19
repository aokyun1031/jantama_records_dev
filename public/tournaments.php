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
$pageCss = ['css/forms.css'];
$pageStyle = <<<'CSS'
.tournaments-count {
  font-size: 0.85rem;
  color: var(--text-sub);
  animation: fadeUp 1s ease 0.3s both;
}

.tournaments-list {
  max-width: 700px;
  margin: 0 auto 40px;
  padding: 0 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.tournament-card {
  background: var(--card);
  backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-sm);
  padding: 16px 20px;
  box-shadow: var(--shadow-sm);
  opacity: 0;
  transform: translateY(16px);
  animation: tournamentFadeIn 0.5s ease forwards;
}

.tournament-card-header {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-bottom: 6px;
}

.tournament-status {
  display: inline-block;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 4px;
  white-space: nowrap;
  flex-shrink: 0;
  letter-spacing: 0.5px;
}
.tournament-status.preparing { background: rgba(var(--gold-rgb), 0.15); color: var(--gold); }
.tournament-status.active { background: rgba(var(--mint-rgb), 0.15); color: var(--success); }
.tournament-status.completed { background: rgba(var(--accent-rgb), 0.1); color: var(--text-sub); }

.tournament-name {
  font-weight: 800;
  font-size: 1.05rem;
  color: var(--text);
}

.tournament-rules {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
  margin-bottom: 8px;
}
.tournament-rules span {
  white-space: nowrap;
  font-size: 0.7rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 4px;
  background: rgba(var(--accent-rgb), 0.05);
  border: 1px solid rgba(var(--accent-rgb), 0.1);
  color: var(--text-sub);
}

.tournament-info {
  font-size: 0.8rem;
  color: var(--text-sub);
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}
.tournament-info-item {
  display: flex;
  align-items: center;
  gap: 4px;
}
.tournament-winner {
  font-weight: 700;
  color: var(--gold);
}

.tournament-links {
  display: flex;
  gap: 8px;
  margin-top: 8px;
}
.tournament-link {
  font-size: 0.75rem;
  font-weight: 700;
  text-decoration: none;
  padding: 5px 12px;
  border-radius: 6px;
  transition: background 0.2s, border-color 0.2s, box-shadow 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.tournament-link--view {
  background: rgba(var(--accent-rgb), 0.1);
  color: var(--purple);
  border: 1px solid rgba(var(--accent-rgb), 0.25);
}
.tournament-link--view:hover {
  background: rgba(var(--accent-rgb), 0.18);
  border-color: rgba(var(--accent-rgb), 0.4);
  box-shadow: 0 2px 8px rgba(var(--accent-rgb), 0.15);
}
.tournament-link--admin {
  background: transparent;
  color: var(--text-sub);
  border: 1px solid var(--glass-border);
}
.tournament-link--admin:hover {
  background: rgba(var(--accent-rgb), 0.05);
  border-color: rgba(var(--accent-rgb), 0.2);
  color: var(--text);
}
.tournament-link.disabled {
  color: var(--text-light);
  background: transparent;
  border-color: var(--glass-border);
  opacity: 0.5;
  cursor: not-allowed;
  pointer-events: none;
}

.tournaments-actions {
  text-align: center;
  display: flex;
  justify-content: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 40px;
}

.tournaments-error {
  text-align: center;
  padding: 24px;
  max-width: 700px;
  margin: 0 auto 24px;
  background: linear-gradient(135deg, rgba(var(--coral-rgb),0.1), rgba(var(--coral-rgb),0.05));
  border: 1px solid rgba(var(--coral-rgb),0.3);
  border-radius: var(--radius);
  color: var(--text);
  font-size: 0.9rem;
}

.tournaments-error-label {
  font-weight: 700;
  color: var(--coral);
  margin-bottom: 8px;
}

.tournaments-error-detail {
  font-size: 0.75rem;
  color: var(--text-sub);
}

.tournaments-empty {
  text-align: center;
  padding: 40px 20px;
  color: var(--text-sub);
  font-size: 0.9rem;
}

.edit-message { max-width: 600px; margin-left: auto; margin-right: auto; }

@keyframes tournamentFadeIn {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
CSS;

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
