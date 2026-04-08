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
$pageStyle = <<<'CSS'
.result-hero {
  text-align: center;
  padding: 48px 20px 32px;
}

.result-badge {
  display: inline-block;
  background: var(--badge-bg);
  color: var(--badge-color);
  font-size: 0.7rem;
  font-weight: 700;
  padding: 4px 14px;
  border-radius: 20px;
  margin-bottom: 20px;
  letter-spacing: 2px;
  box-shadow: 0 2px 12px rgba(var(--accent-rgb),0.3);
  animation: fadeDown 0.8s ease both;
}

.result-tournament-name {
  font-family: 'Noto Sans JP', sans-serif;
  font-size: clamp(1.2rem, 4vw, 1.6rem);
  font-weight: 900;
  color: var(--text);
  margin-bottom: 8px;
}
.result-rules {
  display: flex;
  justify-content: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}
.result-rule-tag {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 12px;
  background: rgba(var(--accent-rgb), 0.08);
  color: var(--text-sub);
}
.result-identity {
  margin-bottom: 12px;
  animation: fadeUp 1s ease both;
}

.result-hero-icon {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  object-fit: cover;
  box-shadow: 0 4px 20px rgba(0,0,0,0.15);
  margin-bottom: 16px;
  animation: fadeUp 0.8s ease 0.2s both;
}

.result-title {
  font-family: 'Noto Sans JP', sans-serif;
  font-size: clamp(1.8rem, 6vw, 2.5rem);
  font-weight: 900;
  background: var(--title-gradient);
  background-size: 300% 300%;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: titleGrad 6s ease infinite, fadeUp 1s ease both;
  margin-bottom: 4px;
}

.result-nickname {
  font-size: 0.85rem;
  color: var(--text-sub);
  margin-bottom: 12px;
}

.result-outcome {
  font-family: 'Noto Sans JP', sans-serif;
  font-weight: 900;
  font-size: 1.8rem;
  line-height: 1;
  margin-bottom: 10px;
  animation: fadeUp 1s ease 0.2s both;
}

.outcome-champion {
  font-size: 2rem;
  background: var(--champion-progress-gradient);
  background-size: 300% 300%;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: titleGrad 4s ease infinite, fadeUp 1s ease 0.2s both;
  filter: drop-shadow(0 1px 2px rgba(var(--gold-rgb),0.3));
}

.outcome-finals {
  color: var(--mint);
}

.outcome-eliminated {
  color: var(--text);
}

.result-standing {
  animation: fadeUp 1s ease 0.4s both;
  text-align: center;
}

.result-standing-label {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--text-sub);
  letter-spacing: 1px;
  margin-bottom: 4px;
}

.result-standing-value {
  font-family: 'Inter', sans-serif;
  font-weight: 700;
  font-size: 1rem;
  color: var(--text-sub);
}



.round-section {
  background: var(--card);
  backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-sm);
  margin-bottom: 16px;
  overflow: hidden;
  box-shadow: var(--shadow-sm);
  opacity: 0;
  transform: translateY(16px);
  animation: roundFadeIn 0.5s ease forwards;
}

.round-header {
  padding: 14px 20px;
  font-weight: 700;
  font-size: 0.9rem;
  color: var(--text);
  background: linear-gradient(135deg, rgba(var(--accent-rgb),0.08), rgba(var(--accent-rgb),0.05));
  border-bottom: 1px solid var(--glass-border);
  display: flex;
  align-items: center;
  gap: 10px;
}

.round-label {
  font-family: 'Inter', sans-serif;
  font-weight: 800;
  font-size: 0.75rem;
  background: var(--btn-primary-bg);
  color: var(--btn-text-color);
  padding: 2px 10px;
  border-radius: 10px;
}

.round-table-name {
  color: var(--text-sub);
  font-weight: 600;
  font-size: 0.85rem;
}

.member-row {
  display: grid;
  grid-template-columns: 1fr auto auto;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  border-bottom: 1px solid rgba(var(--accent-rgb),0.06);
  transition: background 0.2s;
}

.member-row:last-child {
  border-bottom: none;
}

.member-self {
  background: linear-gradient(135deg, rgba(var(--accent-rgb),0.08), rgba(var(--accent-rgb),0.04));
}

.member-name {
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--text);
}

.member-self .member-name {
  font-weight: 800;
  background: var(--btn-primary-bg);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.member-score {
  font-family: 'Inter', sans-serif;
  font-weight: 700;
  font-size: 0.9rem;
  min-width: 60px;
  text-align: right;
}

.score-plus {
  color: var(--coral);
}

.score-minus {
  color: var(--blue);
}

.member-tag {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 8px;
  min-width: 36px;
  text-align: center;
}

.tag-cutoff {
  background: linear-gradient(135deg, rgba(var(--coral-rgb),0.12), rgba(var(--coral-rgb),0.05));
  color: var(--coral);
}

.tag-pass {
  background: linear-gradient(135deg, rgba(var(--mint-rgb),0.12), rgba(var(--mint-rgb),0.05));
  color: var(--mint);
}

.tag-champion {
  background: linear-gradient(135deg, rgba(var(--gold-rgb),0.2), rgba(var(--gold-rgb),0.08));
  color: var(--gold);
}

.result-error {
  text-align: center;
  padding: 24px;
  background: linear-gradient(135deg, rgba(var(--coral-rgb),0.1), rgba(var(--coral-rgb),0.05));
  border: 1px solid rgba(var(--coral-rgb),0.3);
  border-radius: var(--radius);
  color: var(--text);
  font-size: 0.9rem;
  margin-bottom: 24px;
}

.back-btn {
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

.back-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 24px rgba(var(--accent-rgb), 0.4);
}

@keyframes roundFadeIn {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
CSS;

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
    $isChampion = (int)$standing['rank'] === 1 && $tournament['status'] === TournamentStatus::Completed->value;
    $elimRound  = (int)$standing['eliminated_round'];
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
      <div class="result-standing-value"><?= number_format((float)$standing['total'], 1) ?>pt</div>
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
    ['data' => $finalRoundStr] = fetchData(fn() => TournamentMeta::get($tournamentId, 'current_round', '0'));
    $tournamentFinalRound = (int)($finalRoundStr ?? '0');
    foreach ($rounds as $ri => $round):
      $isFinal = (int)$round['round_number'] === $tournamentFinalRound && $tournament['status'] === TournamentStatus::Completed->value;
  ?>
    <div class="round-section" style="animation-delay: <?= $ri * 0.1 ?>s">
      <div class="round-header">
        <span class="round-label"><?= (int)$round['round_number'] ?>回戦</span>
        <span class="round-table-name"><?= h($round['table_name']) ?></span>
      </div>
      <?php
        $championShown = false;
        foreach ($round['members'] as $member):
      ?>
        <div class="member-row <?= $member['id'] === $playerId ? 'member-self' : '' ?>">
          <div class="member-name"><?= h($member['name']) ?></div>
          <?php if ($member['score'] !== null):
            $aboveCutoff = $member['is_above_cutoff'] !== false && $member['is_above_cutoff'] !== 'f';
          ?>
            <div class="member-score <?= (float)$member['score'] >= 0 ? 'score-plus' : 'score-minus' ?>">
              <?= (float)$member['score'] >= 0 ? '+' : '' ?><?= number_format((float)$member['score'], 1) ?>
            </div>
            <?php if ($isFinal && !$championShown): $championShown = true; ?>
              <div class="member-tag tag-champion">優勝</div>
            <?php elseif ($isFinal): ?>
              <div class="member-tag tag-cutoff">敗退</div>
            <?php elseif ($aboveCutoff): ?>
              <div class="member-tag tag-pass">通過</div>
            <?php else: ?>
              <div class="member-tag tag-cutoff">敗退</div>
            <?php endif; ?>
          <?php else: ?>
            <div class="member-score" style="color: var(--text-light);">-</div>
            <div></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<div style="text-align: center;">
  <a href="player?id=<?= $playerId ?>" class="btn-cancel">&#x2190; 個人ページに戻る</a>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
