<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();
ensureCsrfToken();

// --- バリデーション ---
$tournamentId = filter_input(INPUT_GET, 'tournament_id', FILTER_VALIDATE_INT);
$roundNumber = filter_input(INPUT_GET, 'round_number', FILTER_VALIDATE_INT);
$playerId = filter_input(INPUT_GET, 'player_id', FILTER_VALIDATE_INT);
if (!$tournamentId || !$roundNumber || !$playerId) {
    abort404();
}

$tournament = requireTournamentWithMeta($tournamentId);
$player = requirePlayer($playerId);

if ($tournament['status'] === TournamentStatus::Completed->value) {
    abort404();
}

['data' => $tournamentPlayerIds] = fetchData(fn() => Tournament::playerIds($tournamentId));
if (!in_array($playerId, $tournamentPlayerIds ?? [], true)) {
    abort404();
}

['data' => $candidates] = fetchData(fn() => ScheduleCandidate::byRound($tournamentId, $roundNumber));
$candidates = $candidates ?? [];
if (empty($candidates)) {
    abort404();
}

['data' => $checkedIds] = fetchData(fn() => ScheduleResponse::byPlayer($tournamentId, $roundNumber, $playerId));
$checkedIds = $checkedIds ?? [];

$flash = consumeFlash();
$validationError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validationError = validatePost();
    if (!$validationError) {
        $candidateIds = array_unique(array_map('intval', $_POST['candidate_ids'] ?? []));
        $validIds = array_map(fn($c) => (int) $c['id'], $candidates);

        if (empty($candidateIds)) {
            $validationError = '参加可能な日程を1つ以上選択してください。';
        } elseif (array_diff($candidateIds, $validIds)) {
            $validationError = '不正な候補日程が含まれています。';
        } else {
            try {
                ScheduleResponse::replaceForPlayer($tournamentId, $roundNumber, $playerId, $candidateIds);
                $_SESSION['flash'] = '回答しました。';
                regenerateCsrfToken();
                header('Location: schedule_response?tournament_id=' . $tournamentId . '&round_number=' . $roundNumber . '&player_id=' . $playerId);
                exit;
            } catch (PDOException $e) {
                error_log('[DB] ' . $e->getMessage());
                $validationError = '保存に失敗しました。';
            }
        }
        $checkedIds = $candidateIds;
    }
}

$ruleTags = buildRuleTags($tournament['meta']);

// --- テンプレート変数 ---
$pageTitle = $roundNumber . '回戦 参加可能日回答 - ' . $tournament['name'] . ' - ' . SITE_NAME;
$pageDescription = ($player['nickname'] ?? $player['name']) . ' さん専用の参加可能日回答ページです。';
$pageCss = ['css/forms.css', 'css/schedule_response.css'];
$pageTurnstile = true;
require __DIR__ . '/../templates/header.php';
?>

<div class="sr-hero">
  <div class="sr-badge">SCHEDULE</div>
  <h1 class="sr-title"><?= $roundNumber ?>回戦 参加可能日回答</h1>
  <div class="sr-subtitle"><?= h($tournament['name']) ?></div>
  <div class="sr-rules">
    <?php foreach ($ruleTags as $tag): ?>
      <span class="sr-rule-tag"><?= h($tag) ?></span>
    <?php endforeach; ?>
  </div>
</div>

<div class="sr-content">
  <?php if ($flash): ?>
    <div class="edit-message success"><?= h($flash) ?></div>
  <?php elseif ($validationError): ?>
    <div class="edit-message error"><?= h($validationError) ?></div>
  <?php endif; ?>

  <div class="sr-self-card">
    <?php if ($player['character_icon']): ?>
      <img src="img/chara_deformed/<?= h($player['character_icon']) ?>" alt="<?= h($player['nickname'] ?? $player['name']) ?>" class="sr-self-icon" width="64" height="64">
    <?php else: ?>
      <div class="sr-self-noicon">NO<br>IMG</div>
    <?php endif; ?>
    <div class="sr-self-name"><?= h($player['nickname'] ?? $player['name']) ?> さん</div>
  </div>

  <div class="sr-hint">参加可能な日程をすべて選択してください（複数選択可）。</div>

  <form method="post" action="schedule_response?tournament_id=<?= $tournamentId ?>&amp;round_number=<?= $roundNumber ?>&amp;player_id=<?= $playerId ?>" class="sr-form">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

    <div class="sr-candidate-list">
      <?php foreach ($candidates as $c):
        $cid = (int) $c['id'];
        $dateLabel = date('n/j', strtotime($c['played_date']));
        $dowChar = $c['day_of_week'] !== '' ? mb_substr($c['day_of_week'], 0, 1) : '';
      ?>
        <label class="sr-candidate-option">
          <input type="checkbox" name="candidate_ids[]" value="<?= $cid ?>" <?= in_array($cid, $checkedIds, true) ? 'checked' : '' ?>>
          <span class="sr-candidate-label"><?= h($dateLabel) ?>(<?= h($dowChar) ?>) <?= h($c['played_time']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <button type="submit" class="sr-btn-save">回答する</button>
  </form>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
