<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();
ensureCsrfToken();

// --- バリデーション ---
$tableId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$tableId || $tableId <= 0) {
    abort404();
}

['data' => $table] = fetchData(fn() => TableInfo::findWithPlayers($tableId));
if (!$table) {
    abort404();
}

$tournamentId = (int) $table['tournament_id'];
$tournament = requireTournamentWithMeta($tournamentId);
$isDone = (bool) $table['done'];
$isDev = !isProduction();

$meta = $tournament['meta'];
$rk = 'round_' . $table['round_number'];
$gameCount = max(1, (int) ($meta[$rk . '_game_count'] ?? 1));

// 牌譜URL取得
$paifuUrls = TablePaifuUrl::byTable($tableId);
$paifuUrlMap = [];
foreach ($paifuUrls as $pu) {
    $paifuUrlMap[(int) $pu['game_number']] = $pu['url'];
}


// POST処理
$success = filter_input(INPUT_GET, 'saved') === '1';
$validationError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isDone) {
    $validationError = validatePost();
    if ($validationError) {
        // バリデーションエラー
    } else {
        $action = sanitizeInput('action');

        if ($action === 'schedule') {
            $playedDate = sanitizeInput('played_date');
            $playedTime = sanitizeInput('played_time');
            // 日付から曜日を自動計算
            $dayOfWeek = '';
            if ($playedDate !== '') {
                $dayOfWeek = DayOfWeek::fromDate($playedDate);
            }
            if ($playedDate === '') {
                $validationError = '日付を入力してください。';
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $playedDate)) {
                $validationError = '日付の形式が不正です。';
            } elseif ($playedTime === '') {
                $validationError = '時間を入力してください。';
            } elseif (!preg_match('/^\d{2}:\d{2}$/', $playedTime)) {
                $validationError = '時間の形式が不正です。';
            } else {
                try {
                    TableInfo::updateSchedule($tableId, $playedDate ?: null, $dayOfWeek, $playedTime);
                    regenerateCsrfToken();
                    header('Location: table?id=' . $tableId . '&saved=1');
                    exit;
                } catch (PDOException $e) {
                    error_log('[DB] ' . $e->getMessage());
                    $validationError = '保存に失敗しました。';
                }
            }
        } elseif ($action === 'game_data') {
            // 牌譜URL + スコアを一括保存 → 卓を完了にする
            $urls = [];
            $allGameScores = [];
            $hasError = false;
            for ($g = 1; $g <= $gameCount; $g++) {
                // 牌譜URL
                $raw = sanitizeInput('paifu_url_' . $g);
                if ($raw === '') {
                    $validationError = ($gameCount > 1 ? $g . '局目: ' : '') . '牌譜URLを入力してください。';
                    $hasError = true;
                    break;
                }
                $url = extractUrl($raw);
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    $validationError = ($gameCount > 1 ? $g . '局目: ' : '') . '有効なURLを入力してください。';
                    $hasError = true;
                    break;
                }
                $urls[$g] = $url;

                // スコア（全員必須）
                $scores = [];
                foreach ($table['players'] as $p) {
                    $pid = (int) $p['player_id'];
                    $rawScore = sanitizeInput('score_' . $g . '_' . $pid);
                    if ($rawScore === '') {
                        $validationError = ($gameCount > 1 ? $g . '局目: ' : '') . h($p['nickname'] ?? $p['name']) . 'のスコアを入力してください。';
                        $hasError = true;
                        break 2;
                    }
                    $score = filter_var($rawScore, FILTER_VALIDATE_FLOAT);
                    if ($score === false) {
                        $validationError = ($gameCount > 1 ? $g . '局目: ' : '') . h($p['nickname'] ?? $p['name']) . 'のスコアが不正です。';
                        $hasError = true;
                        break 2;
                    }
                    $scores[] = [
                        'player_id' => $pid,
                        'score' => $score,
                    ];
                }
                $allGameScores[$g] = $scores;
            }
            if (!$hasError) {
                try {
                    TablePaifuUrl::saveAll($tableId, $urls);
                    foreach ($allGameScores as $g => $gameScores) {
                        RoundResult::saveScores($tournamentId, (int) $table['round_number'], $gameScores, $g);
                    }
                    TableInfo::markDone($tableId);
                    Standing::updateTotals($tournamentId);
                    $_SESSION['flash'] = Tournament::processRoundCompletion($tournamentId, (int) $table['round_number']);
                    regenerateCsrfToken();
                    header('Location: tournament?id=' . $tournamentId);
                    exit;
                } catch (PDOException $e) {
                    error_log('[DB] ' . $e->getMessage());
                    $validationError = '保存に失敗しました。';
                }
            }
        } elseif ($action === 'bulk' && $isDev) {
            // 一括保存: 日時 + 牌譜URL + スコア + 完了
            $playedDate = sanitizeInput('played_date');
            $playedTime = sanitizeInput('played_time');
            $dayOfWeek = $playedDate !== '' ? DayOfWeek::fromDate($playedDate) : '';

            $allGameScores = [];
            $bulkUrls = [];
            $hasError = false;
            for ($g = 1; $g <= $gameCount; $g++) {
                $rawUrl = sanitizeInput('paifu_url_' . $g);
                $bulkUrls[$g] = $rawUrl !== '' ? extractUrl($rawUrl) : '';
                $scores = [];
                foreach ($table['players'] as $p) {
                    $pid = (int) $p['player_id'];
                    $raw = sanitizeInput('score_' . $g . '_' . $pid);
                    if ($raw === '') {
                        $validationError = $g . '局目: ' . h($p['nickname'] ?? $p['name']) . 'のスコアを入力してください。';
                        $hasError = true;
                        break 2;
                    }
                    $score = filter_var($raw, FILTER_VALIDATE_FLOAT);
                    if ($score === false) {
                        $validationError = $g . '局目: ' . h($p['nickname'] ?? $p['name']) . 'のスコアが不正です。';
                        $hasError = true;
                        break 2;
                    }
                    $scores[] = ['player_id' => $pid, 'score' => $score];
                }
                $allGameScores[$g] = $scores;
            }

            if (!$hasError) {
                try {
                    TableInfo::updateSchedule($tableId, $playedDate ?: null, $dayOfWeek, $playedTime);
                    TablePaifuUrl::saveAll($tableId, $bulkUrls);
                    foreach ($allGameScores as $g => $scores) {
                        RoundResult::saveScores($tournamentId, (int) $table['round_number'], $scores, $g);
                    }
                    TableInfo::markDone($tableId);
                    Standing::updateTotals($tournamentId);

                    $_SESSION['flash'] = Tournament::processRoundCompletion($tournamentId, (int) $table['round_number']);
                    regenerateCsrfToken();
                    header('Location: tournament?id=' . $tournamentId);
                    exit;
                } catch (PDOException $e) {
                    error_log('[DB] ' . $e->getMessage());
                    $validationError = '保存に失敗しました。';
                }
            }

            if ($validationError) {
                ['data' => $table] = fetchData(fn() => TableInfo::findWithPlayers($tableId));
            }
        }

        // POST後にテーブル情報を再取得
        if ($validationError) {
            ['data' => $table] = fetchData(fn() => TableInfo::findWithPlayers($tableId));
            $paifuUrls = TablePaifuUrl::byTable($tableId);
            $paifuUrlMap = [];
            foreach ($paifuUrls as $pu) {
                $paifuUrlMap[(int) $pu['game_number']] = $pu['url'];
            }
        }
    }
}


// --- テンプレート変数 ---
$pageTitle = h($table['table_name']) . ' - ' . h($tournament['name']) . ' - ' . SITE_NAME;
$pageDescription = $tournament['name'] . ' ' . $table['table_name'] . ' の対局情報・成績を管理します。';
$pageCss = ['css/forms.css', 'css/table.css'];
$pageScripts = !$isDone ? ['js/table.js'] : [];

$pageTurnstile = true;
require __DIR__ . '/../templates/header.php';
?>

<div class="tb-hero">
  <div class="tb-badge">TABLE</div>
  <div class="tb-title"><?= h($tournament['name']) ?> <?= h($table['table_name']) ?></div>
  <div class="tb-subtitle">
    <?= (int) $table['round_number'] ?>回戦<?php if ($isDone): ?> ・ <span class="tb-done-mark">&#10003; 完了</span><?php endif; ?>
  </div>
</div>

<div class="tb-content">
  <?php if ($success): ?>
    <div class="edit-message success">保存しました。</div>
  <?php elseif ($validationError): ?>
    <div class="edit-message error"><?= h($validationError) ?></div>
  <?php endif; ?>

  <!-- 参加選手 -->
  <?php $playerMode = (int) ($meta['player_mode'] ?? 4); ?>
  <?php $subCount = max(0, $playerMode - count($table['players'])); ?>
  <div class="tb-section">
    <div class="tb-section-title">参加選手</div>
    <div class="tb-players">
      <?php foreach ($table['players'] as $p): ?>
        <a href="player?id=<?= (int) $p['player_id'] ?>" class="tb-player tb-player-link">
          <?= charaIcon($p['character_icon'], 32) ?>
          <span class="tb-player-name"><?= h($p['nickname'] ?? $p['name']) ?></span>
        </a>
      <?php endforeach; ?>
      <?php for ($s = 0; $s < $subCount; $s++): ?>
        <div class="tb-player tb-player-sub">
          <span class="tb-player-sub-icon">?</span>
          <span class="tb-player-name">代打ち</span>
        </div>
      <?php endfor; ?>
    </div>
  </div>

  <?php if (!$isDone): ?>
  <form method="post" action="table?id=<?= $tableId ?>" id="table-form">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
  <?php endif; ?>

  <!-- 対局日 -->
  <div class="tb-section">
    <div class="tb-section-title" style="display:flex;align-items:center;gap:12px;">
      対局日
      <?php if (!$isDone && $isDev): ?>
        <button type="button" class="tb-btn-random" id="btn-random-schedule">&#x1F3B2; ランダム</button>
      <?php endif; ?>
    </div>
    <?php if ($isDone): ?>
      <?php
        $schedText = '未設定';
        if ($table['played_date']) {
            $d = new DateTime($table['played_date']);
            $schedText = (int) $d->format('Y') . '/' . (int) $d->format('n') . '/' . (int) $d->format('j');
            if (!empty($table['day_of_week'])) {
                $schedText .= '（' . mb_substr($table['day_of_week'], 0, 1) . '）';
            }
            if (!empty($table['played_time'])) {
                $schedText .= ' ' . substr($table['played_time'], 0, 5);
            }
        }
      ?>
      <div><?= h($schedText) ?></div>
    <?php else: ?>
      <div class="tb-form-row">
        <div>
          <label class="tb-label" for="input-date">日付</label>
          <input type="date" id="input-date" name="played_date" class="tb-input" value="<?= h($table['played_date'] ?? '') ?>">
        </div>
        <div>
          <label class="tb-label" for="input-time">時間</label>
          <input type="time" id="input-time" name="played_time" class="tb-input" value="<?= h($table['played_time'] ?? '') ?>">
        </div>
        <button type="submit" name="action" value="schedule" class="tb-btn-small">対局日のみ保存</button>
      </div>
    <?php endif; ?>
  </div>

  <!-- 対局結果（牌譜URL + スコアをゲームごとにまとめて表示） -->
  <?php if ($isDone): ?>
    <?php for ($g = 1; $g <= $gameCount; $g++): ?>
      <div class="tb-section">
        <div class="tb-section-title"><?= $gameCount > 1 ? $g . '局目' : '対局結果' ?></div>
        <?php $url = $paifuUrlMap[$g] ?? ''; ?>
        <?php if ($url !== ''): ?>
          <div style="margin-bottom: 12px;">
            <span class="tb-label">牌譜</span>
            <a href="<?= h($url) ?>" class="tb-paifu-link" target="_blank" rel="noopener noreferrer"><?= h($url) ?></a>
          </div>
        <?php endif; ?>
        <div class="tb-score-grid">
          <?php
            $gameData = [];
            foreach ($table['players'] as $p) {
                $s = $table['game_scores'][$g][(int) $p['player_id']] ?? null;
                $gameData[] = ['name' => $p['nickname'] ?? $p['name'], 'score' => $s];
            }
            usort($gameData, fn($a, $b) => (float) ($b['score'] ?? 0) <=> (float) ($a['score'] ?? 0));
            foreach ($gameData as $i => $gd):
              $score = (float) ($gd['score'] ?? 0);
          ?>
            <div class="tb-score-row">
              <span class="tb-score-name"><?= $i + 1 ?>位 <?= h($gd['name']) ?></span>
              <span class="tb-score-value <?= $score >= 0 ? 'plus' : 'minus' ?>"><?= $score >= 0 ? '+' : '' ?><?= h((string) $score) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endfor; ?>
    <?php if ($gameCount > 1): ?>
      <div class="tb-section">
        <div class="tb-section-title">合計</div>
        <div class="tb-score-grid">
          <?php
            $sorted = $table['players'];
            usort($sorted, fn($a, $b) => (float) ($b['score'] ?? 0) <=> (float) ($a['score'] ?? 0));
            foreach ($sorted as $i => $p):
              $score = (float) ($p['score'] ?? 0);
          ?>
            <div class="tb-score-row">
              <span class="tb-score-name"><?= $i + 1 ?>位 <?= h($p['nickname'] ?? $p['name']) ?></span>
              <span class="tb-score-value <?= $score >= 0 ? 'plus' : 'minus' ?>" style="font-size: 1rem;"><?= $score >= 0 ? '+' : '' ?><?= h((string) $score) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php else: ?>
      <?php for ($g = 1; $g <= $gameCount; $g++): ?>
        <div class="tb-section">
          <div class="tb-section-title" style="display:flex;align-items:center;gap:12px;">
            <?= $gameCount > 1 ? $g . '局目' : '対局結果' ?>
            <?php if ($isDev): ?>
              <button type="button" class="tb-btn-random btn-random-game" data-game="<?= $g ?>">&#x1F3B2; ランダム</button>
            <?php endif; ?>
          </div>
          <div style="margin-bottom: 12px;">
            <span class="tb-label">牌譜URL</span>
            <input type="url" name="paifu_url_<?= $g ?>" class="tb-input tb-paifu-input" style="width: 100%; box-sizing: border-box;" value="<?= h($paifuUrlMap[$g] ?? '') ?>" placeholder="https://game.mahjongsoul.com/...">
          </div>
          <div class="tb-score-grid">
            <?php foreach ($table['players'] as $p):
              $existingScore = $table['game_scores'][$g][(int) $p['player_id']] ?? null;
            ?>
              <div class="tb-score-row">
                <span class="tb-score-name"><?= h($p['nickname'] ?? $p['name']) ?></span>
                <input type="number" name="score_<?= $g ?>_<?= (int) $p['player_id'] ?>" class="tb-input tb-score-input" step="0.1" inputmode="decimal" value="<?= $existingScore !== null ? h((string) $existingScore) : '' ?>" placeholder="0.0">
              </div>
            <?php endforeach; ?>
          </div>
          <div class="tb-sum-box tb-sum-pending" data-sum-box="<?= $g ?>">合計: -</div>
        </div>
      <?php endfor; ?>
      <div class="tb-done-section">
        <input type="hidden" name="complete" value="1">
        <button type="submit" name="action" value="game_data" class="tb-btn-done" data-confirm="対局結果を保存して卓を完了にしますか？">対局結果を保存して卓を完了にする</button>
      </div>

    <?php if ($isDev): ?>
      <!-- 一括保存（開発環境のみ） -->
      <div class="tb-section" style="border-color: rgba(var(--gold-rgb), 0.3); background: rgba(var(--gold-rgb), 0.02);">
        <div class="tb-section-title" style="display:flex;align-items:center;gap:12px; border-color: rgba(var(--gold-rgb), 0.2);">
          一括保存
          <button type="button" class="tb-btn-random" id="btn-random-all">&#x1F3B2; 全てランダム入力</button>
        </div>
        <div class="tn-hint" style="margin-bottom: 12px;">日時・牌譜URL・スコアをまとめて保存し、卓を完了にします。</div>
        <button type="submit" name="action" value="bulk" class="tb-btn-done" data-confirm="全項目を保存して卓を完了にしますか？">まとめて保存 &amp; 完了</button>
      </div>
    <?php endif; ?>

  </form>
  <?php endif; ?>

  <div class="tb-actions">
    <?php if (filter_input(INPUT_GET, 'from') === 'view'): ?>
      <a href="tournament_view?id=<?= $tournamentId ?>" class="btn-cancel">&#x2190; 大会閲覧ページに戻る</a>
    <?php else: ?>
      <a href="tournament?id=<?= $tournamentId ?>" class="btn-cancel">&#x2190; 大会管理ページに戻る</a>
    <?php endif; ?>
  </div>
</div>

<?php
$pageInlineScript = '';
if (!$isDone) {
    $jsData = [
        'gameCount' => $gameCount,
        'startPt' => (int) ($meta['starting_points'] ?? 25000),
        'returnPt' => (int) ($meta['return_points'] ?? 30000),
        'pMode' => (int) ($meta['player_mode'] ?? 4),
        'pCount' => count($table['players']),
        'hasSub' => $subCount > 0,
        'isDev' => $isDev,
    ];
    $jsDataJson = json_encode($jsData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    $pageInlineScript = "window.__tableData = {$jsDataJson};";
}

require __DIR__ . '/../templates/footer.php';
?>
