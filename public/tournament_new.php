<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();
ensureCsrfToken();

['data' => $players] = fetchData(fn() => Player::all());

// POST値の保持用
$validationError = '';
$postEventType = EventType::Saikyoi->value;
$postName = '';
$postPlayerMode = '4';
$postRoundType = 'hanchan';
$postThinkingTime = '5+20';
$postStartingPoints = '25000';
$postReturnPoints = '30000';
$postRedDora = '3';
$postOpenTanyao = '1';
$postHanRestriction = '1';
$postBust = '0';
$postPlayerIds = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validationError = validatePost();
    if ($validationError) {
        // バリデーションエラー
    } else {
        $postEventType = sanitizeInput('event_type');
        $postName = sanitizeInput('name');
        $postPlayerMode = sanitizeInput('player_mode');
        $postRoundType = sanitizeInput('round_type');
        $postThinkingTime = sanitizeInput('thinking_time');
        $postStartingPoints = sanitizeInput('starting_points');
        $postReturnPoints = sanitizeInput('return_points');
        $postRedDora = sanitizeInput('red_dora');
        $postOpenTanyao = sanitizeInput('open_tanyao');
        $postHanRestriction = sanitizeInput('han_restriction');
        $postBust = sanitizeInput('bust');
        $postPlayerIds = array_map('intval', $_POST['player_ids'] ?? []);

        // バリデーション
        $validPlayerModes = ['3', '4'];
        $validRoundTypes = ['tonpu', 'hanchan', 'ikkyoku'];
        $validThinkingTimes = ['3+5', '5+10', '5+20', '60+0', '300+0'];
        $validRedDora = ['0', '3', '4'];
        $validHanRestrictions = ['1', '2', '4'];

        $startingPoints = filter_var($postStartingPoints, FILTER_VALIDATE_INT);
        $returnPoints = filter_var($postReturnPoints, FILTER_VALIDATE_INT);

        $allPlayerIds = array_map(fn($p) => (int) $p['id'], $players ?? []);

        if (EventType::tryFrom($postEventType) === null) {
            $validationError = '不正なイベント種別です。';
        } elseif ($postName === '') {
            $validationError = '大会名を入力してください。';
        } elseif (mb_strlen($postName) > 100) {
            $validationError = '大会名は100文字以内で入力してください。';
        } elseif (!in_array($postPlayerMode, $validPlayerModes, true)) {
            $validationError = '不正な対局人数です。';
        } elseif (!in_array($postRoundType, $validRoundTypes, true)) {
            $validationError = '不正な局数設定です。';
        } elseif (!in_array($postThinkingTime, $validThinkingTimes, true)) {
            $validationError = '不正な長考時間です。';
        } elseif ($startingPoints === false || $startingPoints < 100 || $startingPoints > 200000) {
            $validationError = '配給原点は100〜200,000の範囲で入力してください。';
        } elseif ($returnPoints === false || $returnPoints < 100 || $returnPoints > 200000) {
            $validationError = '1位必要点数は100〜200,000の範囲で入力してください。';
        } elseif (!in_array($postRedDora, $validRedDora, true)) {
            $validationError = '不正な赤ドラ設定です。';
        } elseif (!in_array($postOpenTanyao, ['0', '1'], true)) {
            $validationError = '不正な食い断設定です。';
        } elseif (!in_array($postHanRestriction, $validHanRestrictions, true)) {
            $validationError = '不正な翻縛り設定です。';
        } elseif (!in_array($postBust, ['0', '1'], true)) {
            $validationError = '不正な飛び設定です。';
        } elseif (!empty($postPlayerIds) && array_diff($postPlayerIds, $allPlayerIds)) {
            $validationError = '不正な選手が含まれています。';
        } else {
            try {
                $tournamentId = Tournament::createWithDetails($postName, [
                    'event_type' => $postEventType,
                    'player_mode' => $postPlayerMode,
                    'round_type' => $postRoundType,
                    'thinking_time' => $postThinkingTime,
                    'starting_points' => $postStartingPoints,
                    'return_points' => $postReturnPoints,
                    'red_dora' => $postRedDora,
                    'open_tanyao' => $postOpenTanyao,
                    'han_restriction' => $postHanRestriction,
                    'bust' => $postBust,
                ], $postPlayerIds);

                $_SESSION['flash'] = '大会を作成しました。';
                $_SESSION['dm_dispatch_pending'] = $tournamentId;
                announceDiscordTournamentCreated($tournamentId);
                regenerateCsrfToken();
                header('Location: tournament?id=' . $tournamentId);
                exit;
            } catch (PDOException $e) {
                error_log('[DB] ' . $e->getMessage());
                $validationError = '大会の作成に失敗しました。';
            }
        }
    }
}

// --- テンプレート変数 ---
$pageTitle = '大会作成 - ' . SITE_NAME;
$pageDescription = '新しい麻雀トーナメント大会を作成します。';
$pageCss = ['css/forms.css'];
$pageStyle = '';

$pageTurnstile = true;
require __DIR__ . '/../templates/header.php';
?>

<div class="edit-hero">
  <div class="edit-badge">NEW TOURNAMENT</div>
  <div class="edit-title">大会作成</div>
  <div class="edit-subtitle">新しい大会を作成します</div>
</div>

<div class="edit-form">
  <?php if ($validationError): ?>
    <div class="edit-message error"><?= h($validationError) ?></div>
  <?php endif; ?>

  <form method="post" action="tournament_new">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

<?php require __DIR__ . '/../templates/tournament_rules.php'; ?>

    <!-- 参加選手 -->
    <div class="edit-section">
      <div class="edit-section-title">参加選手</div>
      <div class="edit-hint" style="margin-bottom: 16px;">事前に参加確定の選手だけチェック。未選択の選手には大会作成後 自動的にDiscord DMで参加表明URLが送信されます（Discord ID 未登録選手は対象外）。</div>
      <div class="player-select-controls">
        <button type="button" class="btn-select-toggle" id="btn-select-all">全選択</button>
        <button type="button" class="btn-select-toggle" id="btn-deselect-all">全解除</button>
        <span class="player-select-count" id="selected-count">0人選択中</span>
      </div>
      <div class="player-select-grid" id="player-grid">
        <?php foreach ($players ?? [] as $p): ?>
          <label class="player-select-option">
            <input type="checkbox" name="player_ids[]" value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], $postPlayerIds, true) ? 'checked' : '' ?>>
            <div class="player-select-inner">
              <?php if ($p['character_icon']): ?>
                <img src="img/chara_deformed/<?= h($p['character_icon']) ?>" alt="<?= h($p['name']) ?>" class="player-select-icon" width="36" height="36" loading="lazy">
              <?php else: ?>
                <div class="player-select-noicon">NO<br>IMG</div>
              <?php endif; ?>
              <span class="player-select-name"><?= h($p['name']) ?></span>
            </div>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="edit-actions">
      <a href="tournaments" class="btn-cancel">&#x2190; 大会一覧に戻る</a>
      <button type="submit" class="btn-save">大会を作成</button>
    </div>
  </form>
</div>

<?php
$pageScripts = ['js/player-select.js'];
$pageInlineScript = <<<'JS'
(function() {
  var form = document.querySelector('form');
  document.querySelector('button.btn-save').addEventListener('click', function(e) {
    if (!form.checkValidity()) {
      e.preventDefault();
      var el = form.querySelector(':invalid');
      if (el) {
        el.scrollIntoView({ block: 'center' });
        setTimeout(function() { form.reportValidity(); }, 400);
      }
    }
  });
})();
JS;

require __DIR__ . '/../templates/footer.php';
?>
