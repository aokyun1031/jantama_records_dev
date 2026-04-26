<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();
ensureCsrfToken();

// --- バリデーション ---
$tournamentId = filter_input(INPUT_GET, 'tournament_id', FILTER_VALIDATE_INT);
$playerId = filter_input(INPUT_GET, 'player_id', FILTER_VALIDATE_INT);
if (!$tournamentId || !$playerId) {
    abort404();
}

$tournament = requireTournamentWithMeta($tournamentId);
$player = requirePlayer($playerId);

if ($tournament['status'] === TournamentStatus::Completed->value) {
    abort404();
}

['data' => $currentPlayerIds] = fetchData(fn() => Tournament::playerIds($tournamentId));
['data' => $playedPlayerIds] = fetchData(fn() => Tournament::playedPlayerIds($tournamentId));
$currentPlayerIds = $currentPlayerIds ?? [];
$playedPlayerIds = $playedPlayerIds ?? [];

$isJoined = in_array($playerId, $currentPlayerIds, true);
$isLocked = in_array($playerId, $playedPlayerIds, true);

$flash = consumeFlash();
$validationError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validationError = validatePost();
    if (!$validationError) {
        $action = sanitizeInput('action');
        try {
            if ($action === 'join') {
                Tournament::addPlayer($tournamentId, $playerId);
                $_SESSION['flash'] = '参加しました。';
            } elseif ($action === 'leave') {
                if ($isLocked) {
                    $validationError = '対局済のため参加取消はできません。';
                } else {
                    Tournament::removePlayer($tournamentId, $playerId);
                    $_SESSION['flash'] = '参加を取り消しました。';
                }
            } else {
                $validationError = '不正な操作です。';
            }
            if (!$validationError) {
                regenerateCsrfToken();
                header('Location: tournament_join?tournament_id=' . $tournamentId . '&player_id=' . $playerId);
                exit;
            }
        } catch (RuntimeException $e) {
            $validationError = $e->getMessage();
        } catch (PDOException $e) {
            error_log('[DB] ' . $e->getMessage());
            $validationError = '保存に失敗しました。';
        }
    }
}

['data' => $allPlayers] = fetchData(fn() => Player::all());
$allPlayers = $allPlayers ?? [];
$otherJoinedPlayers = array_filter(
    $allPlayers,
    fn($p) => in_array((int) $p['id'], $currentPlayerIds, true) && (int) $p['id'] !== $playerId
);

$ruleTags = buildRuleTags($tournament['meta']);

// --- テンプレート変数 ---
$pageTitle = h($tournament['name']) . ' 参加表明 - ' . SITE_NAME;
$pageDescription = h($player['nickname'] ?? $player['name']) . ' さん専用の参加表明ページです。';
$pageCss = ['css/tournament-join.css'];
$pageTurnstile = true;
require __DIR__ . '/../templates/header.php';
?>

<div class="tj-hero">
  <div class="tj-badge">JOIN</div>
  <h1 class="tj-title"><?= h($tournament['name']) ?></h1>
  <div class="tj-rules">
    <?php foreach ($ruleTags as $tag): ?>
      <span class="tj-rule-tag"><?= h($tag) ?></span>
    <?php endforeach; ?>
  </div>
</div>

<div class="tj-content">
  <?php if ($flash):
    $isLeave = str_contains($flash, '取り消し');
  ?>
    <div class="tj-flash <?= $isLeave ? 'leave' : 'joined' ?>">
      <div class="tj-flash-icon">
        <?php if ($isLeave): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        <?php else: ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
        <?php endif; ?>
      </div>
      <div class="tj-flash-body">
        <div class="tj-flash-title"><?= $isLeave ? '参加を取り消しました' : '参加表明 完了' ?></div>
        <div class="tj-flash-sub"><?= $isLeave ? 'また気が向いたらいつでも参加できます。' : '大会の開始をお待ちください。' ?></div>
      </div>
    </div>
  <?php endif; ?>
  <?php if ($validationError): ?>
    <div class="edit-message error"><?= h($validationError) ?></div>
  <?php endif; ?>

  <div class="tj-self-card">
    <?php if ($player['character_icon']): ?>
      <img src="img/chara_deformed/<?= h($player['character_icon']) ?>" alt="<?= h($player['nickname'] ?? $player['name']) ?>" class="tj-self-icon" width="96" height="96">
    <?php else: ?>
      <div class="tj-self-noicon">NO<br>IMG</div>
    <?php endif; ?>
    <div class="tj-self-name"><?= h($player['nickname'] ?? $player['name']) ?> さん</div>
    <div class="tj-self-status">
      <?php if ($isLocked): ?>
        <span class="tj-status-badge locked">対局済 / 参加確定</span>
      <?php elseif ($isJoined): ?>
        <span class="tj-status-badge joined">参加中</span>
      <?php else: ?>
        <span class="tj-status-badge not-joined">未参加</span>
      <?php endif; ?>
    </div>

    <?php if ($isLocked): ?>
      <div class="tj-locked-note">既に対局しているため、参加取消はできません。</div>
    <?php else: ?>
      <form method="post" action="tournament_join?tournament_id=<?= $tournamentId ?>&amp;player_id=<?= $playerId ?>" class="tj-action-form">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <?php if ($isJoined): ?>
          <input type="hidden" name="action" value="leave">
          <button type="submit" class="tj-btn tj-btn-leave" data-confirm="参加を取り消しますか？">参加を取り消す</button>
        <?php else: ?>
          <input type="hidden" name="action" value="join">
          <button type="submit" class="tj-btn tj-btn-join">参加する</button>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!empty($otherJoinedPlayers)): ?>
    <div class="tj-others">
      <div class="tj-others-title">参加中の他選手（<?= count($otherJoinedPlayers) ?>名）</div>
      <div class="tj-others-grid">
        <?php foreach ($otherJoinedPlayers as $p): ?>
          <div class="tj-other-card">
            <?php if ($p['character_icon']): ?>
              <img src="img/chara_deformed/<?= h($p['character_icon']) ?>" alt="<?= h($p['nickname'] ?? $p['name']) ?>" class="tj-other-icon" width="40" height="40" loading="lazy">
            <?php else: ?>
              <div class="tj-other-noicon">NO<br>IMG</div>
            <?php endif; ?>
            <span class="tj-other-name"><?= h($p['nickname'] ?? $p['name']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
