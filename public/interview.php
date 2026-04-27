<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

$tournamentId = requireTournamentId();
['data' => $tournamentData] = fetchData(fn() => Tournament::find($tournamentId));
$tournamentName = $tournamentData['name'] ?? '';
$eventType = EventType::tryFrom(TournamentMeta::get($tournamentId, 'event_type'));

['data' => $finalists] = fetchData(fn() => Standing::finalists($tournamentId));
['data' => $interviews] = fetchData(fn() => Interview::byTournament($tournamentId));
['data' => $champion] = fetchData(fn() => Standing::champion($tournamentId));

$pageTitle = '優勝インタビュー - ' . $tournamentName;
$pageDescription = $tournamentName . ' 優勝者への優勝インタビューを掲載しています。';
$pageOgp = [
    'title' => '優勝インタビュー - ' . $tournamentName,
    'description' => $tournamentName . ' 優勝者への優勝インタビューを掲載しています。',
    'url' => SITE_URL . '/interview?id=' . $tournamentId,
];
$pageCss = ['css/champion.css', 'css/interview.css'];

require __DIR__ . '/../templates/header.php';
?>

<!-- Hero -->
<div class="interview-hero">
  <div class="interview-badge">CHAMPION INTERVIEW</div>
  <h1 class="interview-title">優勝インタビュー</h1>
  <?php if ($eventType): ?>
    <span class="interview-profile-tag"><?= h($eventType->label()) ?></span>
  <?php endif; ?>
  <div class="interview-subtitle"><?= h($tournamentName) ?> チャンピオンに聞く</div>
</div>

<!-- Profile Card -->
<section class="interview-section">
  <div class="interview-profile">
    <div class="interview-avatar">
      <?php if ($champion && !empty($champion['character_icon'])): ?>
        <img src="img/chara_deformed/<?= h($champion['character_icon']) ?>" alt="<?= h($champion['name']) ?>" width="80" height="80">
      <?php endif; ?>
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
