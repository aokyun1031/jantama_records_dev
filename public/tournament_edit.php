<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';

startSecureSession();
ensureCsrfToken();

// --- バリデーション ---
$tournamentId = requireTournamentId();
$tournament = requireTournamentWithMeta($tournamentId);

if ($tournament['status'] === TournamentStatus::Completed->value) {
    abort404();
}

$meta = $tournament['meta'];

// POST値の保持用（初期値はDB値）
$success = isset($_GET['saved']) && $_GET['saved'] === '1';
$validationError = '';
$postEventType = $meta['event_type'] ?? EventType::Saikyoi->value;
$postName = $tournament['name'];
$postPlayerMode = $meta['player_mode'] ?? '4';
$postRoundType = $meta['round_type'] ?? 'hanchan';
$postThinkingTime = $meta['thinking_time'] ?? '5+20';
$postStartingPoints = $meta['starting_points'] ?? '25000';
$postReturnPoints = $meta['return_points'] ?? '30000';
$postRedDora = $meta['red_dora'] ?? '3';
$postOpenTanyao = $meta['open_tanyao'] ?? '1';
$postHanRestriction = $meta['han_restriction'] ?? '1';
$postBust = $meta['bust'] ?? '0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        http_response_code(403);
        $validationError = '不正なリクエストです。ページを再読み込みしてください。';
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

        // バリデーション
        $validPlayerModes = ['3', '4'];
        $validRoundTypes = ['tonpu', 'hanchan', 'ikkyoku'];
        $validThinkingTimes = ['3+5', '5+10', '5+20', '60+0', '300+0'];
        $validRedDora = ['0', '3', '4'];
        $validHanRestrictions = ['1', '2', '4'];

        $startingPoints = filter_var($postStartingPoints, FILTER_VALIDATE_INT);
        $returnPoints = filter_var($postReturnPoints, FILTER_VALIDATE_INT);

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
            $validationError = '不正な持ち時間です。';
        } elseif ($startingPoints === false || $startingPoints < 100 || $startingPoints > 200000) {
            $validationError = '配給原点は100〜200,000の範囲で入力してください。';
        } elseif ($returnPoints === false || $returnPoints < 100 || $returnPoints > 200000) {
            $validationError = '返し点は100〜200,000の範囲で入力してください。';
        } elseif (!in_array($postRedDora, $validRedDora, true)) {
            $validationError = '不正な赤ドラ設定です。';
        } elseif (!in_array($postOpenTanyao, ['0', '1'], true)) {
            $validationError = '不正な喰いタン設定です。';
        } elseif (!in_array($postHanRestriction, $validHanRestrictions, true)) {
            $validationError = '不正な翻縛り設定です。';
        } elseif (!in_array($postBust, ['0', '1'], true)) {
            $validationError = '不正なトビ設定です。';
        } else {
            try {
                Tournament::updateDetails($tournamentId, $postName, [
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
                ]);

                regenerateCsrfToken();
                header('Location: tournament_edit?id=' . $tournamentId . '&saved=1');
                exit;
            } catch (PDOException $e) {
                error_log('[DB] ' . $e->getMessage());
                $validationError = '保存に失敗しました。';
            }
        }
    }
}

// --- テンプレート変数 ---
$pageTitle = h($tournament['name']) . ' 編集 - 最強位戦';
$pageCss = ['css/forms.css'];
$pageStyle = '';

require __DIR__ . '/../templates/header.php';
?>

<div class="edit-hero">
  <div class="edit-badge">EDIT TOURNAMENT</div>
  <div class="edit-title"><?= h($tournament['name']) ?></div>
  <div class="edit-subtitle">大会情報の編集</div>
</div>

<div class="edit-form">
  <?php if ($success): ?>
    <div class="edit-message success">保存しました。</div>
  <?php elseif ($validationError): ?>
    <div class="edit-message error"><?= h($validationError) ?></div>
  <?php endif; ?>

  <form method="post" action="tournament_edit?id=<?= $tournamentId ?>">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

    <!-- イベント種別 -->
    <div class="edit-section">
      <div class="edit-label">イベント種別</div>
      <div class="edit-radio-group">
        <?php foreach (EventType::cases() as $et): ?>
          <label class="edit-radio-option">
            <input type="radio" name="event_type" value="<?= h($et->value) ?>" <?= $postEventType === $et->value ? 'checked' : '' ?>>
            <span class="edit-radio-label"><?= h($et->label()) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 大会名 -->
    <div class="edit-section">
      <label class="edit-label" for="input-name">大会名</label>
      <input type="text" id="input-name" name="name" class="edit-input" value="<?= h($postName) ?>" maxlength="100" required>
    </div>

    <!-- ルール設定 -->
    <div class="edit-section">
      <div class="edit-section-title">ルール設定</div>
      <div class="edit-hint" style="margin-bottom: 16px;">卓作成時に変更可能です。</div>

      <div class="edit-field">
        <div class="edit-label">対局人数</div>
        <div class="edit-radio-group">
          <?php foreach (PlayerMode::cases() as $pm): ?>
            <label class="edit-radio-option">
              <input type="radio" name="player_mode" value="<?= h($pm->value) ?>" <?= $postPlayerMode === $pm->value ? 'checked' : '' ?>>
              <span class="edit-radio-label"><?= h($pm->fullLabel()) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="edit-field">
        <div class="edit-label">局数</div>
        <div class="edit-radio-group">
          <?php foreach (RoundType::cases() as $rt): ?>
            <label class="edit-radio-option">
              <input type="radio" name="round_type" value="<?= h($rt->value) ?>" <?= $postRoundType === $rt->value ? 'checked' : '' ?>>
              <span class="edit-radio-label"><?= h($rt->label()) ?>戦</span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="edit-field">
        <label class="edit-label" for="select-thinking-time">持ち時間</label>
        <select id="select-thinking-time" name="thinking_time" class="edit-select">
          <option value="3+5" <?= $postThinkingTime === '3+5' ? 'selected' : '' ?>>3+5秒</option>
          <option value="5+10" <?= $postThinkingTime === '5+10' ? 'selected' : '' ?>>5+10秒</option>
          <option value="5+20" <?= $postThinkingTime === '5+20' ? 'selected' : '' ?>>5+20秒（標準）</option>
          <option value="60+0" <?= $postThinkingTime === '60+0' ? 'selected' : '' ?>>60+0秒</option>
          <option value="300+0" <?= $postThinkingTime === '300+0' ? 'selected' : '' ?>>300+0秒</option>
        </select>
      </div>

      <div class="edit-field">
        <div class="edit-label">配給原点 / 返し点</div>
        <div class="points-row">
          <div class="points-field">
            <input type="number" name="starting_points" class="edit-input" value="<?= h($postStartingPoints) ?>" min="100" max="200000" step="100" required>
            <div class="edit-hint">配給原点</div>
          </div>
          <div class="points-field">
            <input type="number" name="return_points" class="edit-input" value="<?= h($postReturnPoints) ?>" min="100" max="200000" step="100" required>
            <div class="edit-hint">返し点</div>
          </div>
        </div>
      </div>

      <div class="edit-field">
        <div class="edit-label">赤ドラ</div>
        <div class="edit-radio-group">
          <label class="edit-radio-option">
            <input type="radio" name="red_dora" value="0" <?= $postRedDora === '0' ? 'checked' : '' ?>>
            <span class="edit-radio-label">赤無し</span>
          </label>
          <label class="edit-radio-option">
            <input type="radio" name="red_dora" value="3" <?= $postRedDora === '3' ? 'checked' : '' ?>>
            <span class="edit-radio-label">赤ドラ3</span>
          </label>
          <label class="edit-radio-option">
            <input type="radio" name="red_dora" value="4" <?= $postRedDora === '4' ? 'checked' : '' ?>>
            <span class="edit-radio-label">赤ドラ4</span>
          </label>
        </div>
      </div>

      <div class="edit-field">
        <div class="edit-label">喰いタン</div>
        <div class="edit-radio-group">
          <label class="edit-radio-option">
            <input type="radio" name="open_tanyao" value="1" <?= $postOpenTanyao === '1' ? 'checked' : '' ?>>
            <span class="edit-radio-label">有効</span>
          </label>
          <label class="edit-radio-option">
            <input type="radio" name="open_tanyao" value="0" <?= $postOpenTanyao === '0' ? 'checked' : '' ?>>
            <span class="edit-radio-label">無効</span>
          </label>
        </div>
      </div>

      <div class="edit-field">
        <div class="edit-label">翻縛り</div>
        <div class="edit-radio-group">
          <?php foreach (HanRestriction::cases() as $hr): ?>
            <label class="edit-radio-option">
              <input type="radio" name="han_restriction" value="<?= h($hr->value) ?>" <?= $postHanRestriction === $hr->value ? 'checked' : '' ?>>
              <span class="edit-radio-label"><?= h($hr->label()) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="edit-field">
        <div class="edit-label">トビ</div>
        <div class="edit-radio-group">
          <label class="edit-radio-option">
            <input type="radio" name="bust" value="1" <?= $postBust === '1' ? 'checked' : '' ?>>
            <span class="edit-radio-label">あり</span>
          </label>
          <label class="edit-radio-option">
            <input type="radio" name="bust" value="0" <?= $postBust === '0' ? 'checked' : '' ?>>
            <span class="edit-radio-label">なし</span>
          </label>
        </div>
      </div>
    </div>

    <div class="edit-actions">
      <a href="tournament?id=<?= $tournamentId ?>" class="btn-cancel">&#x2190; 大会ページに戻る</a>
      <button type="submit" class="btn-save">保存</button>
    </div>
  </form>

</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
