<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();
ensureCsrfToken();

// --- バリデーション ---
$tournamentId = filter_input(INPUT_GET, 'tournament_id', FILTER_VALIDATE_INT);
if (!$tournamentId || $tournamentId <= 0) {
    abort404();
}
$tournament = requireTournamentWithMeta($tournamentId);
if ($tournament['status'] === TournamentStatus::Completed->value) {
    abort404();
}

['data' => $roundNumber] = fetchData(fn() => TableInfo::nextRoundNumber($tournamentId));
$roundNumber = $roundNumber ?? 1;

['data' => $existingCandidates] = fetchData(fn() => ScheduleCandidate::byRound($tournamentId, $roundNumber));
$existingCandidates = $existingCandidates ?? [];

$validationError = '';
$postedDates = null;
$postedTimes = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validationError = validatePost();
    if ($validationError) {
        // バリデーションエラー
    } else {
        $postedDates = $_POST['played_date'] ?? [];
        $postedTimes = $_POST['played_time'] ?? [];
        $candidates = [];
        $seen = [];

        for ($i = 0; $i < count($postedDates); $i++) {
            $date = preg_replace('/[\x00-\x1F\x7F]/u', '', trim((string) ($postedDates[$i] ?? '')));
            $time = preg_replace('/[\x00-\x1F\x7F]/u', '', trim((string) ($postedTimes[$i] ?? '')));
            if ($date === '' && $time === '') {
                continue; // 空行スキップ
            }
            if (!isValidDateString($date)) {
                $validationError = '日付の形式が不正です。';
                break;
            }
            if ($time === '' || mb_strlen($time) > 5) {
                $validationError = '時間帯は1〜5文字で入力してください（例: 昼, 19:00）。';
                break;
            }
            $key = $date . '|' . $time;
            if (isset($seen[$key])) {
                $validationError = '同じ日程が重複しています。';
                break;
            }
            $seen[$key] = true;
            $candidates[] = ['played_date' => $date, 'played_time' => $time];
        }

        if (!$validationError && empty($candidates)) {
            $validationError = '候補日程を1つ以上登録してください。';
        }

        if (!$validationError) {
            try {
                ScheduleCandidate::createBatch($tournamentId, $roundNumber, $candidates);
                $_SESSION['flash'] = '候補日程を保存しました。';
                $_SESSION['schedule_dm_dispatch_pending'] = ['tournament_id' => $tournamentId, 'round_number' => $roundNumber];
                regenerateCsrfToken();
                header('Location: schedule_combine?tournament_id=' . $tournamentId . '&round_number=' . $roundNumber);
                exit;
            } catch (PDOException $e) {
                error_log('[DB] ' . $e->getMessage());
                $validationError = '保存に失敗しました。';
            }
        }
    }
}

// JS用候補データ（バリデーションエラー時は入力値を保持）
if ($validationError && $postedDates !== null) {
    $jsCandidates = [];
    for ($i = 0; $i < count($postedDates); $i++) {
        $jsCandidates[] = [
            'played_date' => (string) ($postedDates[$i] ?? ''),
            'played_time' => (string) ($postedTimes[$i] ?? ''),
        ];
    }
} else {
    $jsCandidates = array_map(fn($c) => [
        'played_date' => $c['played_date'],
        'played_time' => $c['played_time'],
    ], $existingCandidates);
}

$hasExisting = !empty($existingCandidates);

// --- テンプレート変数 ---
$pageTitle = $roundNumber . '回戦 候補日程設定 - ' . $tournament['name'] . ' - ' . SITE_NAME;
$pageDescription = $tournament['name'] . ' の候補日程を設定します。';
$pageCss = ['css/forms.css', 'css/schedule_candidates_new.css'];
$pageScripts = ['js/schedule-candidates-new.js'];

$pageTurnstile = true;
require __DIR__ . '/../templates/header.php';
?>

<div class="sc-hero">
  <div class="sc-badge">SCHEDULE</div>
  <div class="sc-title"><?= $roundNumber ?>回戦 候補日程設定</div>
  <div class="sc-subtitle"><?= h($tournament['name']) ?></div>
</div>

<div class="sc-content">
  <?php if ($validationError): ?>
    <div class="edit-message error"><?= h($validationError) ?></div>
  <?php endif; ?>

  <?php if ($hasExisting): ?>
    <div class="sc-warning">&#x26A0; 候補日程を変更すると、既存の選手回答は削除されます。</div>
  <?php endif; ?>

  <form method="post" action="schedule_candidates_new?tournament_id=<?= $tournamentId ?>" id="sc-form">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

    <div class="sc-section">
      <div class="sc-section-title">候補日程</div>
      <div class="sc-candidate-list" id="sc-candidate-list"></div>
      <button type="button" class="sc-btn-add" id="sc-btn-add">+ 候補を追加</button>
    </div>

    <div class="sc-actions">
      <a href="tournament?id=<?= $tournamentId ?>" class="btn-cancel">&#x2190; 大会ページに戻る</a>
      <button type="submit" class="sc-btn-save">候補日程を保存</button>
    </div>
  </form>
</div>

<?php
$jsCandidatesJson = json_encode($jsCandidates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$pageInlineScript = <<<JS
window.__scheduleCandidatesData = {
  candidates: {$jsCandidatesJson}
};
JS;

require __DIR__ . '/../templates/footer.php';
?>
