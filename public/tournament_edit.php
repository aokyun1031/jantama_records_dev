<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();
ensureCsrfToken();

// --- バリデーション ---
$tournamentId = requireTournamentId();
$tournament = requireTournamentWithMeta($tournamentId);

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
$pageTitle = h($tournament['name']) . ' 編集 - ' . SITE_NAME;
$pageCss = ['css/forms.css'];
$pageStyle = '';

$pageTurnstile = true;
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

<?php require __DIR__ . '/../templates/tournament_rules.php'; ?>

    <div class="edit-actions">
      <a href="tournament?id=<?= $tournamentId ?>" class="btn-cancel">&#x2190; 大会ページに戻る</a>
      <div class="cf-turnstile" data-sitekey="<?= h(turnstileSiteKey()) ?>"></div>
      <button type="submit" class="btn-save">保存</button>
    </div>
  </form>

</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
