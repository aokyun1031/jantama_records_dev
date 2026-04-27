<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();
ensureCsrfToken();

// --- バリデーション ---
$tournamentId = requireTournamentId();
$tournament = requireTournamentWithMeta($tournamentId);

if ($tournament['status'] === TournamentStatus::Completed->value) {
    abort404();
}

['data' => $players] = fetchData(fn() => Player::all());
['data' => $currentPlayerIds] = fetchData(fn() => Tournament::playerIds($tournamentId));
['data' => $playedPlayerIds] = fetchData(fn() => Tournament::playedPlayerIds($tournamentId));
$playedPlayerIds = $playedPlayerIds ?? [];

$success = isset($_GET['saved']) && $_GET['saved'] === '1';
$validationError = '';
$postPlayerIds = $currentPlayerIds ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validationError = validatePost();
    if ($validationError) {
        // バリデーションエラー
    } else {
        $postPlayerIds = array_map('intval', $_POST['player_ids'] ?? []);
        $meta = $tournament['meta'];
        $playerMode = (int) ($meta['player_mode'] ?? 4);

        $allPlayerIds = array_map(fn($p) => (int) $p['id'], $players ?? []);
        if (count($postPlayerIds) < $playerMode) {
            $validationError = "最低{$playerMode}人の選手を登録してください。";
        } elseif (!empty($postPlayerIds) && array_diff($postPlayerIds, $allPlayerIds)) {
            $validationError = '不正な選手が含まれています。';
        } else {
            try {
                Tournament::updatePlayers($tournamentId, $postPlayerIds);
                $_SESSION['flash'] = '選手登録を保存しました。';
                regenerateCsrfToken();
                header('Location: tournament?id=' . $tournamentId);
                exit;
            } catch (PDOException $e) {
                error_log('[DB] ' . $e->getMessage());
                $validationError = '保存に失敗しました。';
            }
        }
    }
}

// --- テンプレート変数 ---
$pageTitle = $tournament['name'] . ' 選手登録 - ' . SITE_NAME;
$pageDescription = $tournament['name'] . ' に参加する選手を登録します。';
$pageCss = ['css/forms.css'];
$pageStyle = '';

$pageTurnstile = true;
require __DIR__ . '/../templates/header.php';
?>

<div class="edit-hero">
  <div class="edit-badge">PLAYERS</div>
  <div class="edit-title"><?= h($tournament['name']) ?></div>
  <div class="edit-subtitle">参加選手の登録</div>
</div>

<div class="edit-form">
  <?php if ($success): ?>
    <div class="edit-message success">保存しました。</div>
  <?php elseif ($validationError): ?>
    <div class="edit-message error"><?= h($validationError) ?></div>
  <?php endif; ?>

  <form method="post" action="tournament_players?id=<?= $tournamentId ?>">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

    <div class="edit-section">
      <div class="edit-section-title">参加選手</div>
      <div class="edit-hint" style="margin-bottom: 16px;">卓に割り当て済みの選手は変更できません。</div>
      <div class="player-select-controls">
        <button type="button" class="btn-select-toggle" id="btn-select-all">全選択</button>
        <button type="button" class="btn-select-toggle" id="btn-deselect-all">全解除</button>
        <span class="player-select-count" id="selected-count">0人選択中</span>
      </div>
      <div class="player-select-grid" id="player-grid">
        <?php foreach ($players ?? [] as $p):
          $pid = (int) $p['id'];
          $isLocked = in_array($pid, $playedPlayerIds, true);
          $isChecked = in_array($pid, $postPlayerIds, true);
        ?>
          <label class="player-select-option <?= $isLocked ? 'locked' : '' ?>" data-name="<?= h(mb_strtolower($p['nickname'] ?? $p['name'])) ?>">
            <?php if ($isLocked): ?>
              <input type="checkbox" name="player_ids[]" value="<?= $pid ?>" checked disabled>
              <input type="hidden" name="player_ids[]" value="<?= $pid ?>">
            <?php else: ?>
              <input type="checkbox" name="player_ids[]" value="<?= $pid ?>" <?= $isChecked ? 'checked' : '' ?>>
            <?php endif; ?>
            <div class="player-select-inner">
              <?php if ($p['character_icon']): ?>
                <img src="img/chara_deformed/<?= h($p['character_icon']) ?>" alt="<?= h($p['nickname'] ?? $p['name']) ?>" class="player-select-icon" width="36" height="36" loading="lazy">
              <?php else: ?>
                <div class="player-select-noicon">NO<br>IMG</div>
              <?php endif; ?>
              <span class="player-select-name"><?= h($p['nickname'] ?? $p['name']) ?></span>
              <?php if ($isLocked): ?>
                <span class="player-select-locked-badge">対局済</span>
              <?php endif; ?>
            </div>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="edit-actions">
      <a href="tournament?id=<?= $tournamentId ?>" class="btn-cancel">&#x2190; 大会ページに戻る</a>
      <button type="submit" class="btn-save">保存</button>
    </div>
  </form>
</div>

<?php
$pageScripts = ['js/player-select.js'];

require __DIR__ . '/../templates/footer.php';
?>
