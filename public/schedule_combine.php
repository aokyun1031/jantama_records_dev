<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();
ensureCsrfToken();

// --- バリデーション ---
$tournamentId = filter_input(INPUT_GET, 'tournament_id', FILTER_VALIDATE_INT);
$roundNumber = filter_input(INPUT_GET, 'round_number', FILTER_VALIDATE_INT);
if (!$tournamentId || !$roundNumber) {
    abort404();
}
$tournament = requireTournamentWithMeta($tournamentId);

['data' => $candidates] = fetchData(fn() => ScheduleCandidate::byRound($tournamentId, $roundNumber));
$candidates = $candidates ?? [];

// 候補日程保存直後フラグ（自動DM送信トリガー）
$pendingDispatch = $_SESSION['schedule_dm_dispatch_pending'] ?? null;
$scheduleDmDispatchPending = $pendingDispatch
    && (int) ($pendingDispatch['tournament_id'] ?? 0) === $tournamentId
    && (int) ($pendingDispatch['round_number'] ?? 0) === $roundNumber;
unset($_SESSION['schedule_dm_dispatch_pending']);

$flash = consumeFlash();
$validationError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validationError = validatePost();
    if (!$validationError) {
        $action = sanitizeInput('action');
        if ($action === 'respond_for_player' && $tournament['status'] !== TournamentStatus::Completed->value) {
            $targetPlayerId = (int) sanitizeInput('player_id');
            $candidateIds = array_unique(array_map('intval', $_POST['candidate_ids'] ?? []));
            $validCandidateIds = array_map(fn($c) => (int) $c['id'], $candidates);

            ['data' => $tournamentPlayerIds] = fetchData(fn() => Tournament::playerIds($tournamentId));

            if (!in_array($targetPlayerId, $tournamentPlayerIds ?? [], true)) {
                $validationError = '不正な選手です。';
            } elseif (empty($candidateIds)) {
                $validationError = '参加可能な日程を1つ以上選択してください。';
            } elseif (array_diff($candidateIds, $validCandidateIds)) {
                $validationError = '不正な候補日程が含まれています。';
            } else {
                try {
                    ScheduleResponse::replaceForPlayer($tournamentId, $roundNumber, $targetPlayerId, $candidateIds);
                    $_SESSION['flash'] = '回答を登録しました。';
                    regenerateCsrfToken();
                    header('Location: schedule_combine?tournament_id=' . $tournamentId . '&round_number=' . $roundNumber);
                    exit;
                } catch (PDOException $e) {
                    error_log('[DB] ' . $e->getMessage());
                    $validationError = '保存に失敗しました。';
                }
            }
        } else {
            $validationError = '不正な操作です。';
        }
    }
}

['data' => $responseCounts] = fetchData(fn() => ScheduleCandidate::responseCountsByRound($tournamentId, $roundNumber));
$responseCounts = $responseCounts ?? [];

['data' => $activePlayers] = fetchData(fn() => Standing::activePlayersWithDetails($tournamentId));
$activePlayers = $activePlayers ?? [];

['data' => $respondedPlayerIds] = fetchData(fn() => ScheduleResponse::respondedPlayerIds($tournamentId, $roundNumber));
$respondedPlayerIds = $respondedPlayerIds ?? [];

$unrespondedPlayers = array_values(array_filter(
    $activePlayers,
    fn($p) => !in_array((int) $p['id'], $respondedPlayerIds, true)
));

$candidateIds = array_map(fn($c) => (int) $c['id'], $candidates);
['data' => $respondersByCandidate] = fetchData(fn() => ScheduleResponse::byCandidateIds($candidateIds));
$respondersByCandidate = $respondersByCandidate ?? [];

$playerMap = [];
foreach ($activePlayers as $p) {
    $playerMap[(int) $p['id']] = $p;
}

$respondedPlayers = array_values(array_filter(
    $activePlayers,
    fn($p) => in_array((int) $p['id'], $respondedPlayerIds, true)
));

$playerRespondedCandidateIds = [];
foreach ($respondersByCandidate as $cid => $pids) {
    foreach ($pids as $pid) {
        $playerRespondedCandidateIds[$pid][] = $cid;
    }
}

// --- テンプレート変数 ---
$pageTitle = $roundNumber . '回戦 回答状況 - ' . $tournament['name'] . ' - ' . SITE_NAME;
$pageDescription = $tournament['name'] . ' の候補日程の回答状況を確認します。';
$pageCss = ['css/forms.css', 'css/schedule_combine.css'];
$pageScripts = ['js/dispatch-schedule-dm.js'];

$pageTurnstile = true;
require __DIR__ . '/../templates/header.php';
?>

<div class="sb-hero">
  <div class="sb-badge">SCHEDULE</div>
  <div class="sb-title"><?= $roundNumber ?>回戦 回答状況</div>
  <div class="sb-subtitle"><?= h($tournament['name']) ?></div>
</div>

<div class="sb-content" data-csrf-token="<?= h($_SESSION['csrf_token']) ?>" data-tournament-id="<?= $tournamentId ?>" data-round-number="<?= $roundNumber ?>"<?= $scheduleDmDispatchPending ? ' data-dispatch-schedule-pending="1"' : '' ?>>
  <?php if ($flash): ?>
    <div class="edit-message success"><?= h($flash) ?></div>
  <?php elseif ($validationError): ?>
    <div class="edit-message error"><?= h($validationError) ?></div>
  <?php endif; ?>

  <?php if (empty($candidates)): ?>
    <div class="sb-empty">
      候補日程が未設定です。
      <a href="schedule_candidates_new?tournament_id=<?= $tournamentId ?>">候補日程を設定する</a>
    </div>
  <?php else: ?>
    <div class="sb-section">
      <div class="sb-section-title-row">
        <div class="sb-section-title">候補日程ごとの回答状況</div>
        <a href="schedule_candidates_new?tournament_id=<?= $tournamentId ?>" class="sb-edit-link">&#x1F4C5; 候補日程を編集</a>
      </div>
      <div class="sb-candidate-list">
        <?php foreach ($candidates as $c):
          $cid = (int) $c['id'];
          $dateLabel = date('n/j', strtotime($c['played_date']));
          $dowChar = $c['day_of_week'] !== '' ? mb_substr($c['day_of_week'], 0, 1) : '';
          $responderIds = $respondersByCandidate[$cid] ?? [];
        ?>
          <div class="sb-candidate-row">
            <div class="sb-candidate-row-main">
              <span class="sb-candidate-label"><?= h($dateLabel) ?>(<?= h($dowChar) ?>) <?= h($c['played_time']) ?></span>
              <span class="sb-candidate-count"><?= h($responseCounts[$cid] ?? 0) ?>名</span>
            </div>
            <?php if (!empty($responderIds)): ?>
              <div class="sb-candidate-names">
                <?php foreach ($responderIds as $rid):
                  $rp = $playerMap[$rid] ?? null;
                  $rName = $rp ? ($rp['nickname'] ?? $rp['name']) : ('ID:' . $rid);
                ?>
                  <span class="sb-candidate-name-chip">
                    <?php if ($rp && !empty($rp['character_icon'])): ?>
                      <img src="img/chara_deformed/<?= h($rp['character_icon']) ?>" alt="" class="sb-candidate-name-icon" width="18" height="18" loading="lazy">
                    <?php else: ?>
                      <span class="sb-candidate-name-noicon"></span>
                    <?php endif; ?>
                    <?= h($rName) ?>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="sb-section">
      <div class="sb-section-title">未回答選手（<?= h(count($unrespondedPlayers)) ?>名 / <?= h(count($activePlayers)) ?>名）</div>
      <?php if (empty($unrespondedPlayers)): ?>
        <div class="sb-all-responded">全選手が回答済みです。</div>
      <?php else: ?>
        <div class="sb-player-grid">
          <?php foreach ($unrespondedPlayers as $p): ?>
            <div class="sb-player-card">
              <div class="sb-player-info">
                <?php if (!empty($p['character_icon'])): ?>
                  <img src="img/chara_deformed/<?= h($p['character_icon']) ?>" alt="<?= h($p['nickname'] ?? $p['name']) ?>" class="sb-player-icon" width="32" height="32" loading="lazy">
                <?php else: ?>
                  <div class="sb-player-noicon">NO<br>IMG</div>
                <?php endif; ?>
                <span class="sb-player-name"><?= h($p['nickname'] ?? $p['name']) ?></span>
                <?php if (empty($p['discord_user_id'])): ?>
                  <span class="sb-player-badge">Discord未登録</span>
                <?php else: ?>
                  <button type="button" class="sb-btn-dispatch-one" data-dispatch-schedule-tournament="<?= $tournamentId ?>" data-dispatch-schedule-round="<?= $roundNumber ?>" data-dispatch-schedule-player="<?= (int) $p['id'] ?>">再送</button>
                <?php endif; ?>
              </div>
              <form method="post" action="schedule_combine?tournament_id=<?= $tournamentId ?>&amp;round_number=<?= $roundNumber ?>" class="sb-player-respond-form">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="respond_for_player">
                <input type="hidden" name="player_id" value="<?= (int) $p['id'] ?>">
                <div class="sb-player-candidates">
                  <?php foreach ($candidates as $c):
                    $cDateLabel = date('n/j', strtotime($c['played_date']));
                    $cDowChar = $c['day_of_week'] !== '' ? mb_substr($c['day_of_week'], 0, 1) : '';
                  ?>
                    <label class="sb-player-candidate-option">
                      <input type="checkbox" name="candidate_ids[]" value="<?= (int) $c['id'] ?>">
                      <?= h($cDateLabel) ?>(<?= h($cDowChar) ?>) <?= h($c['played_time']) ?>
                    </label>
                  <?php endforeach; ?>
                </div>
                <button type="submit" class="sb-btn-respond-save">代理で登録</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="sb-dispatch-all-wrap">
          <button type="button" class="sb-btn-dispatch-all" data-dispatch-schedule-tournament="<?= $tournamentId ?>" data-dispatch-schedule-round="<?= $roundNumber ?>">全選手にDM送信</button>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($respondedPlayers)): ?>
      <div class="sb-section">
        <details class="sb-responded-details">
          <summary class="sb-section-title">回答済み選手の回答を編集（<?= h(count($respondedPlayers)) ?>名）</summary>
          <div class="sb-player-grid">
            <?php foreach ($respondedPlayers as $p):
              $pid = (int) $p['id'];
              $checkedIds = $playerRespondedCandidateIds[$pid] ?? [];
            ?>
              <div class="sb-player-card">
                <div class="sb-player-info">
                  <?php if (!empty($p['character_icon'])): ?>
                    <img src="img/chara_deformed/<?= h($p['character_icon']) ?>" alt="<?= h($p['nickname'] ?? $p['name']) ?>" class="sb-player-icon" width="32" height="32" loading="lazy">
                  <?php else: ?>
                    <div class="sb-player-noicon">NO<br>IMG</div>
                  <?php endif; ?>
                  <span class="sb-player-name"><?= h($p['nickname'] ?? $p['name']) ?></span>
                </div>
                <form method="post" action="schedule_combine?tournament_id=<?= $tournamentId ?>&amp;round_number=<?= $roundNumber ?>" class="sb-player-respond-form">
                  <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="action" value="respond_for_player">
                  <input type="hidden" name="player_id" value="<?= $pid ?>">
                  <div class="sb-player-candidates">
                    <?php foreach ($candidates as $c):
                      $cDateLabel = date('n/j', strtotime($c['played_date']));
                      $cDowChar = $c['day_of_week'] !== '' ? mb_substr($c['day_of_week'], 0, 1) : '';
                    ?>
                      <label class="sb-player-candidate-option">
                        <input type="checkbox" name="candidate_ids[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $checkedIds, true) ? 'checked' : '' ?>>
                        <?= h($cDateLabel) ?>(<?= h($cDowChar) ?>) <?= h($c['played_time']) ?>
                      </label>
                    <?php endforeach; ?>
                  </div>
                  <button type="submit" class="sb-btn-respond-save">回答を更新</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        </details>
      </div>
    <?php endif; ?>

    <div class="sb-actions">
      <a href="tournament?id=<?= $tournamentId ?>" class="btn-cancel">&#x2190; 大会ページに戻る</a>
      <a href="table_new?tournament_id=<?= $tournamentId ?>" class="sb-btn-generate">組み合わせを自動生成 &#x2192;</a>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
