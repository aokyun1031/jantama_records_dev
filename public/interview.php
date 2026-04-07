<?php
declare(strict_types=1);
require __DIR__ . '/../config/database.php';

// ID指定があればその大会、なければid=1にフォールバック
$tournamentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 1;
['data' => $tournamentData] = fetchData(fn() => Tournament::find($tournamentId));
$tournamentName = $tournamentData['name'] ?? '最強位戦';

['data' => $finalists] = fetchData(fn() => Standing::finalists($tournamentId));
['data' => $interviews] = fetchData(fn() => Interview::byTournament($tournamentId));
$champion = Standing::champion($tournamentId);

$pageTitle = '優勝インタビュー - ' . h($tournamentName);
$pageDescription = h($tournamentName) . ' 優勝者への優勝インタビューを掲載しています。';
$pageOgp = [
    'title' => '優勝インタビュー - ' . $tournamentName,
    'description' => $tournamentName . ' 優勝者への優勝インタビューを掲載しています。',
    'url' => 'https://jantama-records.onrender.com/interview?id=' . $tournamentId,
];
$pageCss = ['css/champion.css'];
$pageStyle = <<<'CSS'
/* Interview Page Styles */
.interview-section {
  max-width: 680px;
  margin: 0 auto;
  padding: 0 16px;
}

.interview-hero {
  text-align: center;
  padding: 48px 20px 32px;
  position: relative;
}

.interview-badge {
  display: inline-block;
  background: var(--badge-bg);
  color: var(--badge-color);
  font-size: 0.7rem;
  font-weight: 700;
  padding: 4px 14px;
  border-radius: 20px;
  margin-bottom: 16px;
  letter-spacing: 2px;
  box-shadow: 0 2px 12px rgba(var(--accent-rgb),0.3);
  animation: fadeDown 0.8s ease both;
}

.interview-title {
  font-family: 'Noto Sans JP', sans-serif;
  font-size: clamp(1.8rem, 6vw, 2.5rem);
  font-weight: 900;
  background: var(--title-gradient);
  background-size: 300% 300%;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: titleGrad 6s ease infinite, fadeUp 1s ease both;
  margin-bottom: 8px;
}

.interview-subtitle {
  font-size: 0.85rem;
  color: var(--text-sub);
  animation: fadeUp 1s ease 0.3s both;
}

.interview-profile {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 20px;
  margin: 32px 0;
  padding: 24px;
  background: var(--card);
  backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius);
  box-shadow: var(--shadow-sm);
  animation: fadeUp 1s ease 0.4s both;
}

.interview-avatar {
  position: relative;
  flex-shrink: 0;
}

.interview-avatar img {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid var(--gold);
  box-shadow: 0 0 20px rgba(var(--gold-rgb), 0.2);
}

.interview-avatar .crown {
  position: absolute;
  top: -12px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 1.5rem;
}

.interview-profile-info {
  text-align: left;
}

.interview-profile-name {
  font-size: 1.5rem;
  font-weight: 900;
  color: var(--text);
  font-family: 'Noto Sans JP', sans-serif;
}

.interview-profile-label {
  font-size: 0.75rem;
  color: var(--gold);
  font-weight: 700;
}

.interview-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 40px;
}

.interview-item {
  background: var(--card);
  backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius);
  padding: 20px 24px;
  box-shadow: var(--shadow-sm);
  transition: transform 0.3s, box-shadow 0.3s;
  opacity: 0;
  transform: translateY(16px);
  animation: interviewFadeIn 0.6s ease forwards;
}

.interview-item:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow);
}

.interview-question {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--gold);
  margin-bottom: 12px;
  padding-bottom: 10px;
  border-bottom: 2px solid rgba(var(--gold-rgb), 0.15);
  display: flex;
  align-items: flex-start;
  gap: 8px;
  line-height: 1.6;
}

.interview-q-label {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 28px;
  height: 28px;
  background: linear-gradient(135deg, var(--gold), rgba(var(--gold-rgb), 0.8));
  color: var(--btn-text-color);
  font-size: 0.7rem;
  font-weight: 800;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 2px;
}

.interview-answer {
  font-size: 1rem;
  color: var(--text);
  line-height: 1.8;
  padding-left: 36px;
}

.interview-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: var(--btn-primary-bg);
  color: var(--btn-text-color);
  text-decoration: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.85rem;
  transition: transform 0.3s, box-shadow 0.3s;
  box-shadow: 0 4px 16px rgba(var(--accent-rgb), 0.3);
  margin-bottom: 40px;
}

.interview-back:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 24px rgba(var(--accent-rgb), 0.4);
}

@keyframes interviewFadeIn {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Responsive */
@media (max-width: 480px) {
  .interview-profile {
    flex-direction: column;
    text-align: center;
    gap: 12px;
  }
  .interview-profile-info {
    text-align: center;
  }
  .interview-item {
    padding: 16px 18px;
  }
  .interview-answer {
    padding-left: 0;
  }
}
CSS;

require __DIR__ . '/../templates/header.php';
?>

<!-- Hero -->
<div class="interview-hero">
  <div class="interview-badge">CHAMPION INTERVIEW</div>
  <h1 class="interview-title">優勝インタビュー</h1>
  <div class="interview-subtitle"><?= h($tournamentName) ?> チャンピオンに聞く</div>
</div>

<!-- Profile Card -->
<section class="interview-section">
  <div class="interview-profile">
    <div class="interview-avatar">
      <img src="img/chara_deformed/<?= $champion && $champion['character_icon'] ? h($champion['character_icon']) : '' ?>" alt="<?= $champion ? h($champion['name']) : '' ?>" width="80" height="80">
      <span class="crown">👑</span>
    </div>
    <div class="interview-profile-info">
      <div class="interview-profile-label">&#x1F3C6; <?= h($tournamentName) ?> 優勝</div>
      <div class="interview-profile-name"><?= $champion ? h($champion['nickname'] ?? $champion['name']) : '' ?></div>
    </div>
  </div>

  <!-- Interview Q&A -->
  <div class="interview-list">
    <?php foreach ($interviews ?? [] as $i => $item): ?>
      <div class="interview-item" style="animation-delay: <?= $i * 0.1 ?>s">
        <div class="interview-question">
          <span class="interview-q-label">Q<?= $i + 1 ?></span>
          <span><?= h($item['question']) ?></span>
        </div>
        <div class="interview-answer"><?= nl2br(h($item['answer'])) ?></div>
      </div>
    <?php endforeach; ?>
    <?php if (empty($interviews)): ?>
      <div style="text-align:center; padding: 24px; color: var(--text-sub);">インタビューはまだ登録されていません。</div>
    <?php endif; ?>
  </div>

  <!-- Back Link -->
  <div style="text-align: center;">
    <a href="tournament_view?id=<?= $tournamentId ?>" class="btn-cancel">&#x2190; 大会ページに戻る</a>
  </div>
</section>

<?php require __DIR__ . '/../templates/footer.php'; ?>
