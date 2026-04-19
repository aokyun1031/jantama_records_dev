<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

// --- バリデーション ---
$playerId = filter_input(INPUT_GET, 'player_id', FILTER_VALIDATE_INT);
$tournamentId = filter_input(INPUT_GET, 'tournament_id', FILTER_VALIDATE_INT);
if (!$playerId || !$tournamentId) {
    abort404();
}

// --- データ取得 ---
$player = requirePlayer($playerId);
$tournament = requireTournamentWithMeta($tournamentId);
$meta = $tournament['meta'];
$ruleTags = buildRuleTags($meta);

['data' => $standing] = fetchData(fn() => Standing::findByPlayer($tournamentId, $playerId));
['data' => $rounds, 'error' => $error] = fetchData(fn() => TableInfo::byPlayerAndTournament($tournamentId, $playerId));

// --- テンプレート変数 ---
$pageTitle = h($player['name']) . ' - ' . h($tournament['name']) . ' - ' . SITE_NAME;
$pageDescription = h($player['name']) . ' の ' . h($tournament['name']) . ' 戦績です。';
$pageCss = ['css/player-tournament.css'];

// --- 表示 ---
require __DIR__ . '/../templates/header.php';
?>

<div class="result-hero">
  <div class="result-badge">TOURNAMENT RECORD</div>
  <div class="result-tournament-name"><?= h($tournament['name']) ?></div>
  <div class="result-rules">
    <?php foreach ($ruleTags as $tag): ?>
      <span class="result-rule-tag"><?= h($tag) ?></span>
    <?php endforeach; ?>
  </div>
  <div class="result-identity">
    <?php if ($player['character_icon']): ?>
      <img src="img/chara_deformed/<?= h($player['character_icon']) ?>" alt="<?= h($player['name']) ?>" class="result-hero-icon" width="88" height="88">
    <?php endif; ?>
    <h1 class="result-title"><?= h($player['nickname'] ?? $player['name']) ?></h1>
  </div>
  <?php if ($standing):
    $isChampion = ($standing['is_champion'] === true || $standing['is_champion'] === 't' || $standing['is_champion'] === '1')
                  && $tournament['status'] === TournamentStatus::Completed->value;
    $elimRound  = (int) $standing['eliminated_round'];
  ?>
    <?php if ($isChampion): ?>
      <div class="result-outcome outcome-champion">優勝</div>
    <?php elseif ($elimRound > 0): ?>
      <div class="result-outcome outcome-eliminated"><?= $elimRound ?>回戦敗退</div>
    <?php else: ?>
      <div class="result-outcome outcome-finals">決勝進出</div>
    <?php endif; ?>
    <div class="result-standing">
      <div class="result-standing-label">総合ポイント</div>
      <div class="result-standing-value"><?= number_format((float) $standing['total'], 1) ?>pt</div>
    </div>
  <?php endif; ?>
</div>

<?php if ($error): ?>
  <div class="result-error">
    データベース接続エラー。しばらくしてから再度お試しください。
  </div>
<?php elseif (empty($rounds)): ?>
  <div class="result-error">
    この大会の対局記録はありません。
  </div>
<?php else: ?>
  <?php
    $tournamentFinalRound = 0;
    if ($tournament['status'] === TournamentStatus::Completed->value) {
      $tournamentFinalRound = max(array_map(fn($r) => (int) $r['round_number'], $rounds));
    }
    foreach ($rounds as $ri => $round):
      $roundNum = (int) $round['round_number'];
      $isFinal = $roundNum === $tournamentFinalRound && $tournament['status'] === TournamentStatus::Completed->value;
  ?>
    <div class="round-section" style="animation-delay: <?= $ri * 0.1 ?>s">
      <div class="round-header">
        <span class="round-label"><?= (int) $round['round_number'] ?>回戦</span>
        <span class="round-table-name"><?= h($round['table_name']) ?></span>
      </div>
      <?php
        $championShown = false;
        foreach ($round['members'] as $member):
          $memberElim = (int) $member['eliminated_round'];
          // 当該ラウンドで敗退 or 過去敗退 → 敗退、それ以外（0 or 未来敗退）→ 通過
          $advanced = $memberElim === 0 || $memberElim > $roundNum;
      ?>
        <?php $isSelf = $member['id'] === $playerId; ?>
        <?= $isSelf
          ? '<div class="member-row member-self">'
          : '<a href="player?id=' . (int) $member['id'] . '" class="member-row member-row-link">' ?>
          <div class="member-name">
            <?= charaIcon($member['character_icon'] ?? null, 28) ?>
            <span class="member-name-text"><?= h($member['name']) ?></span>
          </div>
          <?php if ($member['score'] !== null): ?>
            <div class="member-score <?= (float) $member['score'] >= 0 ? 'score-plus' : 'score-minus' ?>">
              <?= (float) $member['score'] >= 0 ? '+' : '' ?><?= number_format((float) $member['score'], 1) ?>
            </div>
            <?php if ($isFinal && $advanced && !$championShown): $championShown = true; ?>
              <div class="member-tag tag-champion">優勝</div>
            <?php elseif ($isFinal): ?>
              <div class="member-tag tag-cutoff">敗退</div>
            <?php elseif ($advanced): ?>
              <div class="member-tag tag-pass">通過</div>
            <?php else: ?>
              <div class="member-tag tag-cutoff">敗退</div>
            <?php endif; ?>
          <?php else: ?>
            <div class="member-score" style="color: var(--text-light);">-</div>
            <div></div>
          <?php endif; ?>
        <?= $isSelf ? '</div>' : '</a>' ?>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<div style="text-align: center;">
  <a href="player?id=<?= $playerId ?>" class="btn-cancel">&#x2190; 個人ページに戻る</a>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
