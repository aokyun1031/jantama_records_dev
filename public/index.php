<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

$isTopPage = true;

// --- データ取得 ---
['data' => $tournaments] = fetchData(fn() => Tournament::allWithDetails());
['data' => $allPlayers] = fetchData(fn() => Player::all());

$playerCount = count($allPlayers ?? []);
$tournamentCount = count($tournaments ?? []);
$completedTournaments = array_filter($tournaments ?? [], fn($t) => $t['status'] === TournamentStatus::Completed->value);
$activeTournaments = array_filter($tournaments ?? [], fn($t) => $t['status'] === TournamentStatus::InProgress->value);

// 最新の完了大会
$latestCompleted = !empty($completedTournaments) ? reset($completedTournaments) : null;
$latestChampion = $latestCompleted ? Standing::champion((int) $latestCompleted['id']) : null;

// --- テンプレート変数 ---
$pageTitle = SITE_NAME . ' - 麻雀トーナメント戦績サイト';
$pageDescription = '雀魂で開催する麻雀トーナメントの戦績・対局結果・選手情報を掲載しています。';
$pageOgp = [
    'title' => SITE_NAME . ' - 麻雀トーナメント戦績サイト',
    'description' => '雀魂で開催する麻雀トーナメントの戦績・対局結果・選手情報を掲載しています。',
    'url' => 'https://jantama-records.onrender.com/',
];
$pageStyle = <<<'CSS'
/* Hero */
.lp-hero {
  text-align: center;
  padding: 80px 20px 48px;
  position: relative;
  overflow: hidden;
}
.lp-hero-badge {
  display: inline-block;
  background: var(--badge-bg);
  color: var(--badge-color);
  font-size: 0.7rem;
  font-weight: 700;
  padding: 4px 16px;
  border-radius: 20px;
  margin-bottom: 20px;
  letter-spacing: 3px;
  box-shadow: 0 2px 12px rgba(var(--accent-rgb), 0.3);
  animation: fadeDown 0.8s ease both;
}
.lp-hero-title {
  font-family: 'Noto Sans JP', sans-serif;
  font-size: clamp(2rem, 7vw, 3.2rem);
  font-weight: 900;
  background: var(--title-gradient);
  background-size: 300% 300%;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: titleGrad 6s ease infinite, fadeUp 1s ease both;
  margin-bottom: 12px;
}
.lp-hero-sub {
  font-size: 1rem;
  color: var(--text-sub);
  max-width: 480px;
  margin: 0 auto 32px;
  line-height: 1.8;
  animation: fadeUp 1s ease 0.2s both;
}
.lp-hero-actions {
  display: flex;
  justify-content: center;
  gap: 12px;
  flex-wrap: wrap;
  animation: fadeUp 1s ease 0.4s both;
}
.lp-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 14px 28px;
  border-radius: 14px;
  font-weight: 700;
  font-size: 0.9rem;
  font-family: 'Noto Sans JP', sans-serif;
  text-decoration: none;
  transition: transform 0.3s, box-shadow 0.3s;
}
.lp-btn:hover { transform: translateY(-2px); }
.lp-btn-primary {
  background: var(--btn-primary-bg);
  color: var(--btn-text-color);
  box-shadow: 0 4px 20px rgba(var(--accent-rgb), 0.3);
}
.lp-btn-primary:hover { box-shadow: 0 6px 28px rgba(var(--accent-rgb), 0.4); }
.lp-btn-secondary {
  background: var(--card);
  color: var(--text);
  border: 1px solid var(--glass-border);
  box-shadow: var(--shadow-sm);
}
.lp-btn-secondary:hover { box-shadow: var(--shadow); }

/* Stats */
.lp-stats {
  display: flex;
  justify-content: center;
  gap: 40px;
  flex-wrap: wrap;
  padding: 40px 20px;
  max-width: 600px;
  margin: 0 auto;
}
.lp-stat {
  text-align: center;
}
.lp-stat-num {
  font-family: 'Inter', sans-serif;
  font-size: 2.2rem;
  font-weight: 900;
  color: var(--purple);
  line-height: 1;
}
.lp-stat-label {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text-sub);
  margin-top: 4px;
}

/* Section */
.lp-section {
  max-width: 900px;
  margin: 0 auto 48px;
  padding: 0 16px;
}
.lp-section-title {
  font-family: 'Noto Sans JP', sans-serif;
  font-size: 1.3rem;
  font-weight: 900;
  color: var(--text);
  text-align: center;
  margin-bottom: 24px;
}

/* Champion card */
.lp-champion {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 24px;
  background: linear-gradient(135deg, rgba(var(--gold-rgb), 0.08), rgba(var(--accent-rgb), 0.04));
  border: 1px solid rgba(var(--gold-rgb), 0.25);
  border-radius: var(--radius);
  box-shadow: var(--shadow-sm);
  margin-bottom: 24px;
}
.lp-champion-icon {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid rgba(var(--gold-rgb), 0.3);
  flex-shrink: 0;
}
.lp-champion-info { flex: 1; }
.lp-champion-label {
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--gold);
  letter-spacing: 1px;
  margin-bottom: 4px;
}
.lp-champion-name {
  font-size: 1.2rem;
  font-weight: 900;
  color: var(--text);
  margin-bottom: 2px;
}
.lp-champion-tournament {
  font-size: 0.8rem;
  color: var(--text-sub);
}
.lp-champion-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}
.lp-champion-tag {
  font-size: 0.7rem;
  font-weight: 700;
  padding: 2px 10px;
  border-radius: 10px;
  background: rgba(var(--accent-rgb), 0.08);
  border: 1px solid rgba(var(--accent-rgb), 0.25);
  color: var(--text-sub);
}
.lp-champion-tag.point {
  background: rgba(var(--gold-rgb), 0.12);
  border-color: rgba(var(--gold-rgb), 0.35);
  color: var(--gold);
}
.lp-champion-link {
  flex-shrink: 0;
}
@media (max-width: 560px) {
  .lp-champion { flex-direction: column; text-align: center; }
  .lp-champion-link { width: 100%; }
  .lp-champion-link .lp-btn { width: 100%; justify-content: center; }
}

/* Tournament list */
.lp-tournaments {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.lp-tournament-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 20px;
  background: var(--card);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-sm);
  box-shadow: var(--shadow-sm);
  text-decoration: none;
  color: inherit;
  transition: transform 0.2s, box-shadow 0.2s;
}
.lp-tournament-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow);
}
.lp-tournament-body { flex: 1; min-width: 0; }
.lp-tournament-name {
  font-weight: 800;
  font-size: 0.95rem;
  color: var(--text);
  margin-bottom: 4px;
}
.lp-tournament-meta {
  font-size: 0.75rem;
  color: var(--text-sub);
}
.lp-tournament-status {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 10px;
  white-space: nowrap;
  flex-shrink: 0;
}
.lp-tournament-status.preparing { background: rgba(var(--gold-rgb), 0.15); color: var(--gold); }
.lp-tournament-status.active { background: rgba(var(--mint-rgb), 0.15); color: var(--success); }
.lp-tournament-status.completed { background: rgba(var(--accent-rgb), 0.1); color: var(--text-sub); }
.lp-tournament-chevron {
  color: var(--text-light);
  font-size: 1.2rem;
  flex-shrink: 0;
}

/* Features */
.lp-features {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}
.lp-feature {
  padding: 24px;
  background: var(--card);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius);
  box-shadow: var(--shadow-sm);
  text-align: center;
}
.lp-feature-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 12px;
  font-size: 1.2rem;
  background: rgba(var(--accent-rgb), 0.08);
  color: var(--purple);
}
.lp-feature-title {
  font-weight: 800;
  font-size: 0.9rem;
  color: var(--text);
  margin-bottom: 8px;
}
.lp-feature-desc {
  font-size: 0.8rem;
  color: var(--text-sub);
  line-height: 1.6;
}

/* CTA */
.lp-cta {
  text-align: center;
  padding: 48px 20px;
}
.lp-cta-title {
  font-family: 'Noto Sans JP', sans-serif;
  font-size: 1.2rem;
  font-weight: 900;
  color: var(--text);
  margin-bottom: 8px;
}
.lp-cta-desc {
  font-size: 0.85rem;
  color: var(--text-sub);
  margin-bottom: 20px;
}
CSS;

require __DIR__ . '/../templates/header.php';
?>

<!-- Hero -->
<section class="lp-hero">
  <div class="lp-hero-badge">MAHJONG TOURNAMENT</div>
  <h1 class="lp-hero-title">雀魂部屋主催</h1>
  <p class="lp-hero-sub">雀魂で開催する麻雀トーナメントの<br>対局結果・戦績・選手情報を掲載しています。</p>
  <div class="lp-hero-actions">
    <a href="tournaments" class="lp-btn lp-btn-primary">大会一覧を見る</a>
    <a href="players" class="lp-btn lp-btn-secondary">選手一覧を見る</a>
  </div>
</section>

<!-- Stats -->
<div class="lp-stats">
  <div class="lp-stat">
    <div class="lp-stat-num"><?= $tournamentCount ?></div>
    <div class="lp-stat-label">大会</div>
  </div>
  <div class="lp-stat">
    <div class="lp-stat-num"><?= $playerCount ?></div>
    <div class="lp-stat-label">登録選手</div>
  </div>
  <div class="lp-stat">
    <div class="lp-stat-num"><?= count($completedTournaments) ?></div>
    <div class="lp-stat-label">完了大会</div>
  </div>
</div>

<?php if ($latestChampion && $latestCompleted): ?>
<!-- Latest Champion -->
<section class="lp-section">
  <h2 class="lp-section-title">最新の優勝者</h2>
  <div class="lp-champion">
    <?php if (!empty($latestChampion['character_icon'])): ?>
      <img src="img/chara_deformed/<?= h($latestChampion['character_icon']) ?>" alt="" class="lp-champion-icon" width="72" height="72" loading="lazy">
    <?php endif; ?>
    <div class="lp-champion-info">
      <div class="lp-champion-label">CHAMPION</div>
      <div class="lp-champion-name"><?= h($latestChampion['nickname'] ?? $latestChampion['name']) ?></div>
      <div class="lp-champion-tournament"><?= h($latestCompleted['name']) ?></div>
      <?php $championEventType = EventType::tryFrom($latestCompleted['event_type'] ?? ''); ?>
      <div class="lp-champion-meta">
        <?php if ($championEventType): ?>
          <span class="lp-champion-tag"><?= h($championEventType->label()) ?></span>
        <?php endif; ?>
        <span class="lp-champion-tag point"><?= number_format((float) $latestChampion['total'], 1) ?>pt</span>
      </div>
    </div>
    <div class="lp-champion-link">
      <a href="tournament_view?id=<?= (int) $latestCompleted['id'] ?>" class="lp-btn lp-btn-secondary">大会結果を見る &#x203A;</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Tournaments -->
<?php if (!empty($tournaments)): ?>
<section class="lp-section">
  <h2 class="lp-section-title">大会一覧</h2>
  <div class="lp-tournaments">
    <?php foreach (array_slice($tournaments, 0, 5) as $t):
      $tsEnum = TournamentStatus::tryFrom($t['status']);
      $isViewable = $t['status'] !== TournamentStatus::Preparing->value;
      $href = $isViewable ? "tournament_view?id=" . (int) $t['id'] : "tournament?id=" . (int) $t['id'];
    ?>
      <a href="<?= $href ?>" class="lp-tournament-card">
        <div class="lp-tournament-body">
          <div class="lp-tournament-name"><?= h($t['name']) ?></div>
          <div class="lp-tournament-meta"><?= (int) $t['player_count'] ?>名参加<?= $t['status'] === TournamentStatus::Completed->value && !empty($t['winner_name']) ? ' ・ 優勝: ' . h($t['winner_name']) : '' ?></div>
        </div>
        <span class="lp-tournament-status <?= $tsEnum?->cssClass() ?? '' ?>"><?= h($tsEnum?->label() ?? '') ?></span>
        <span class="lp-tournament-chevron">&#x203A;</span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if ($tournamentCount > 5): ?>
    <div style="text-align: center; margin-top: 16px;">
      <a href="tournaments" class="lp-btn lp-btn-secondary">すべての大会を見る</a>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<!-- Features -->
<section class="lp-section">
  <h2 class="lp-section-title">機能紹介</h2>
  <div class="lp-features">
    <div class="lp-feature">
      <div class="lp-feature-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></div>
      <div class="lp-feature-title">卓の自動振り分け</div>
      <div class="lp-feature-desc">ランダム・スイスドロー・ポット分けから選べる卓組み。同卓回避オプション付き。</div>
    </div>
    <div class="lp-feature">
      <div class="lp-feature-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
      <div class="lp-feature-title">リアルタイム戦績</div>
      <div class="lp-feature-desc">ラウンドごとのスコア・勝ち抜き状況をリアルタイムに追跡。総合ポイント自動集計。</div>
    </div>
    <div class="lp-feature">
      <div class="lp-feature-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
      <div class="lp-feature-title">選手プロフィール</div>
      <div class="lp-feature-desc">選手ごとの通算成績・対戦履歴・スコア推移を詳細に分析。</div>
    </div>
    <div class="lp-feature">
      <div class="lp-feature-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></div>
      <div class="lp-feature-title">優勝インタビュー</div>
      <div class="lp-feature-desc">大会優勝者への自由形式のインタビューを掲載。Q&Aの数や内容は毎回カスタマイズ可能。</div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="lp-cta">
  <div class="lp-cta-title">大会の詳細を見てみよう</div>
  <div class="lp-cta-desc">過去の対局結果や選手情報をチェックできます。</div>
  <div class="lp-hero-actions">
    <a href="tournaments" class="lp-btn lp-btn-primary">大会一覧へ</a>
    <a href="players" class="lp-btn lp-btn-secondary">選手一覧へ</a>
  </div>
</section>

<?php require __DIR__ . '/../templates/footer.php'; ?>
