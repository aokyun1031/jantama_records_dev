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
$success = isset($_GET['saved']) && $_GET['saved'] === '1';
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
                        'is_above_cutoff' => true,
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
                    $scores[] = ['player_id' => $pid, 'score' => $score, 'is_above_cutoff' => true];
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
$pageCss = ['css/forms.css'];
$pageStyle = <<<'CSS'
.tb-hero { text-align: center; padding: 48px 20px 24px; }
.tb-badge { display: inline-block; background: var(--badge-bg); color: var(--badge-color); font-size: 0.7rem; font-weight: 700; padding: 4px 14px; border-radius: 20px; margin-bottom: 16px; letter-spacing: 2px; box-shadow: 0 2px 12px rgba(var(--accent-rgb),0.3); }
.tb-title { font-family: 'Noto Sans JP', sans-serif; font-size: clamp(1.4rem, 5vw, 2rem); font-weight: 900; color: var(--text); margin-bottom: 4px; }
.tb-subtitle { font-size: 0.85rem; color: var(--text-sub); }
.tb-content { max-width: 700px; margin: 0 auto 40px; padding: 0 16px; }

.tb-section { background: var(--card); border: 1px solid rgba(var(--accent-rgb), 0.25); border-radius: var(--radius); padding: 24px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.tb-section-title { font-weight: 800; font-size: 0.95rem; color: var(--text); margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid rgba(var(--accent-rgb), 0.15); }

.tb-players { display: flex; flex-wrap: wrap; gap: 12px; }
.tb-player { display: flex; align-items: center; gap: 8px; }
.tb-player-name { font-size: 0.85rem; font-weight: 600; color: var(--text); }
.tb-player-sub .tb-player-name { color: var(--gold); font-style: italic; }
.tb-player-sub-icon { width: 32px; height: 32px; border-radius: 50%; border: 1.5px dashed rgba(var(--gold-rgb), 0.5); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; color: var(--gold); flex-shrink: 0; }

.tb-label { font-weight: 700; font-size: 0.85rem; color: var(--text); margin-bottom: 8px; display: block; }
.tb-input { padding: 10px 14px; border: 1.5px solid var(--input-border); border-radius: var(--radius-sm); font-size: 1rem; font-family: 'Noto Sans JP', sans-serif; background: var(--input-bg); color: var(--text); box-sizing: border-box; transition: border-color 0.2s, box-shadow 0.2s; }
.tb-input::placeholder { color: var(--input-placeholder); }
.tb-input:hover:not(:disabled):not(:focus) { border-color: var(--input-border-hover); }
.tb-input:focus { outline: none; border-color: var(--input-border-focus); box-shadow: var(--input-focus-ring); }
.tb-input:disabled { background: var(--input-disabled-bg); border-color: var(--input-disabled-border); color: var(--text-sub); cursor: not-allowed; }

.tb-form-row { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
.tb-btn-small { padding: 10px 20px; background: var(--btn-primary-bg); color: var(--btn-text-color); border: none; border-radius: 8px; font-weight: 700; font-size: 0.8rem; font-family: 'Noto Sans JP', sans-serif; cursor: pointer; transition: transform 0.2s; }
.tb-btn-small:hover { transform: translateY(-1px); }
.tb-btn-small:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

.tb-score-grid { display: flex; flex-direction: column; gap: 12px; }
.tb-score-row { display: flex; align-items: center; gap: 12px; }
.tb-score-name { font-size: 0.85rem; font-weight: 600; color: var(--text); min-width: 100px; }
.tb-score-input { width: 120px; }
.tb-btn-random { padding: 6px 14px; background: var(--card); color: var(--text-sub); border: 1px solid var(--glass-border); border-radius: 8px; font-weight: 700; font-size: 0.75rem; font-family: 'Noto Sans JP', sans-serif; cursor: pointer; transition: background 0.2s, border-color 0.2s; }
.tb-btn-random:hover { background: rgba(var(--accent-rgb), 0.06); border-color: rgba(var(--accent-rgb), 0.3); color: var(--text); }
.tb-score-value { font-weight: 700; font-size: 0.9rem; }
.tb-score-value.plus { color: var(--success); }
.tb-score-value.minus { color: var(--danger); }

.tb-done-section { text-align: center; padding: 20px; }
.tb-btn-done { padding: 12px 32px; background: var(--btn-secondary-bg); color: var(--btn-text-color); border: none; border-radius: 12px; font-weight: 700; font-size: 0.9rem; font-family: 'Noto Sans JP', sans-serif; cursor: pointer; transition: transform 0.3s, box-shadow 0.3s; box-shadow: 0 4px 16px rgba(var(--mint-rgb), 0.3); }
.tb-btn-done:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(var(--mint-rgb), 0.4); }
.tb-btn-done:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

.tb-completed-badge { display: inline-block; background: rgba(var(--mint-rgb), 0.15); color: var(--success); font-size: 0.75rem; font-weight: 700; padding: 4px 12px; border-radius: 12px; margin-bottom: 8px; }
.tb-paifu-link { color: var(--purple); text-decoration: none; font-size: 0.8rem; word-break: break-all; }
.tb-paifu-link:hover { text-decoration: underline; }

.tb-sum-box { margin-top: 12px; padding: 8px 14px; border-radius: var(--radius-sm); font-size: 0.85rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
.tb-sum-box.tb-sum-pending { background: rgba(var(--accent-rgb), 0.04); color: var(--text-sub); border: 1px solid rgba(var(--accent-rgb), 0.12); }
.tb-sum-box.tb-sum-ok { background: rgba(var(--mint-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--mint-rgb), 0.3); }
.tb-sum-box.tb-sum-ng { background: rgba(var(--coral-rgb), 0.08); color: var(--danger); border: 1px solid rgba(var(--coral-rgb), 0.3); }
.tb-sum-box.tb-sum-skip { background: rgba(var(--gold-rgb), 0.08); color: var(--text-sub); border: 1px solid rgba(var(--gold-rgb), 0.2); font-weight: 600; font-size: 0.78rem; }

.tb-actions { text-align: center; margin-top: 24px; }
CSS;

$pageTurnstile = true;
require __DIR__ . '/../templates/header.php';
?>

<div class="tb-hero">
  <div class="tb-badge"><?= $isDone ? 'COMPLETED' : 'TABLE' ?></div>
  <div class="tb-title"><?= h($table['table_name']) ?></div>
  <div class="tb-subtitle"><?= (int) $table['round_number'] ?>回戦</div>
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
        <div class="tb-player">
          <?= charaIcon($p['character_icon'], 32) ?>
          <span class="tb-player-name"><?= h($p['nickname'] ?? $p['name']) ?></span>
        </div>
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
$jsStartingPoints = (int) ($meta['starting_points'] ?? 25000);
$jsReturnPoints = (int) ($meta['return_points'] ?? 30000);
$jsPlayerMode = (int) ($meta['player_mode'] ?? 4);
$jsPlayerCount = count($table['players']);
$jsGameCount = $gameCount;
$jsHasSub = $subCount > 0 ? 'true' : 'false';
$jsIsDev = $isDev ? 'true' : 'false';

$pageInlineScript = !$isDone ? <<<JS
(function() {
  var form = document.getElementById('table-form');
  if (!form) return;

  var gameCount = {$jsGameCount};
  var startPt = {$jsStartingPoints};
  var returnPt = {$jsReturnPoints};
  var pMode = {$jsPlayerMode};
  var pCount = {$jsPlayerCount};
  var hasSub = {$jsHasSub};
  var isDev = {$jsIsDev};
  var SUM_TOLERANCE = 0.05;

  function fmtSigned(n) {
    var r = Math.round(n * 10) / 10;
    return (r > 0 ? '+' : r < 0 ? '' : '') + r.toFixed(1);
  }

  function updateGameSum(g) {
    var box = form.querySelector('[data-sum-box="' + g + '"]');
    if (!box) return;
    var inputs = form.querySelectorAll('input[name^="score_' + g + '_"]');
    var sum = 0;
    var hasEmpty = false;
    inputs.forEach(function(inp) {
      var v = inp.value.trim();
      if (v === '') { hasEmpty = true; return; }
      var n = parseFloat(v);
      if (!isNaN(n)) sum += n;
    });
    box.className = 'tb-sum-box';
    if (hasSub) {
      box.textContent = '代打ちを含むため自動検算は行いません';
      box.classList.add('tb-sum-skip');
    } else if (hasEmpty) {
      box.textContent = '（すべてのスコアを入力すると自動で検算します）';
      box.classList.add('tb-sum-pending');
    } else if (Math.abs(sum) < SUM_TOLERANCE) {
      box.textContent = '✓ OK';
      box.classList.add('tb-sum-ok');
    } else {
      box.textContent = '合計: ' + fmtSigned(sum) + '（0.0 になるように調整してください）';
      box.classList.add('tb-sum-ng');
    }
  }

  function validateForm() {
    var btn = form.querySelector('button[value="game_data"]');
    if (!btn) return;

    var ok = true;
    for (var g = 1; g <= gameCount; g++) {
      var u = form.querySelector('input[name="paifu_url_' + g + '"]');
      if (u && u.value.trim() === '') { ok = false; break; }
      var inputs = form.querySelectorAll('input[name^="score_' + g + '_"]');
      var sum = 0, empty = false;
      inputs.forEach(function(inp) {
        var v = inp.value.trim();
        if (v === '') empty = true;
        else sum += parseFloat(v) || 0;
      });
      if (empty) { ok = false; break; }
      if (!hasSub && Math.abs(sum) >= SUM_TOLERANCE) { ok = false; break; }
    }
    btn.disabled = !ok;
  }

  function refreshAll() {
    for (var g = 1; g <= gameCount; g++) updateGameSum(g);
    validateForm();
  }

  form.addEventListener('input', function(e) {
    if (!e.target.matches) return;
    if (e.target.matches('.tb-score-input, .tb-paifu-input')) {
      var m = e.target.name.match(/^score_(\d+)_/);
      if (m) updateGameSum(parseInt(m[1], 10));
      validateForm();
    }
  });

  form.addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;
    if (!e.target.matches) return;
    if (!e.target.matches('.tb-score-input, .tb-paifu-input')) return;
    e.preventDefault();
    var nav = Array.prototype.slice.call(form.querySelectorAll('.tb-paifu-input, .tb-score-input'));
    var idx = nav.indexOf(e.target);
    if (idx >= 0 && idx + 1 < nav.length) {
      nav[idx + 1].focus();
      if (nav[idx + 1].select) nav[idx + 1].select();
    } else {
      var btn = form.querySelector('button[value="game_data"]');
      if (btn && !btn.disabled) btn.focus();
    }
  });

  refreshAll();

  if (!isDev) return;

  function genRandomScores() {
    var totalPool = startPt * pCount;
    var rawPoints = [];
    var remaining = totalPool;
    for (var i = 0; i < pCount - 1; i++) {
      var avg = remaining / (pCount - i);
      var deviation = startPt * 0.6;
      var pts = Math.round((avg + (Math.random() - 0.5) * 2 * deviation) / 100) * 100;
      pts = Math.max(100, Math.min(remaining - (pCount - i - 1) * 100, pts));
      rawPoints.push(pts);
      remaining -= pts;
    }
    rawPoints.push(remaining);
    rawPoints.sort(function(a, b) { return b - a; });
    var uma = pMode === 3 ? [15, 0, -15] : [20, 10, -10, -20];
    var scores = [];
    for (var i = 0; i < pCount; i++) {
      var diff = (rawPoints[i] - returnPt) / 1000;
      var u = i < uma.length ? uma[i] : 0;
      scores.push(Math.round((diff + u) * 10) / 10);
    }
    var sum = 0;
    for (var i = 0; i < scores.length; i++) sum += scores[i];
    scores[0] = Math.round((scores[0] - sum) * 10) / 10;
    // シャッフル
    var indices = [];
    for (var i = 0; i < scores.length; i++) indices.push(i);
    for (var i = indices.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var t = indices[i]; indices[i] = indices[j]; indices[j] = t;
    }
    return scores.map(function(_, idx) { return scores[indices[idx]]; });
  }

  // ランダム日時生成
  var btnRandSched = document.getElementById('btn-random-schedule');
  if (btnRandSched) {
    btnRandSched.addEventListener('click', function() {
      var now = new Date();
      var offset = Math.floor(Math.random() * 14) + 1;
      var d = new Date(now.getTime() + offset * 86400000);
      var y = d.getFullYear();
      var m = ('0' + (d.getMonth() + 1)).slice(-2);
      var day = ('0' + d.getDate()).slice(-2);
      var h = Math.floor(Math.random() * 12) + 10;
      var min = [0, 30][Math.floor(Math.random() * 2)];
      var dateInput = document.getElementById('input-date');
      var timeInput = document.getElementById('input-time');
      if (dateInput) dateInput.value = y + '-' + m + '-' + day;
      if (timeInput) timeInput.value = ('0' + h).slice(-2) + ':' + ('0' + min).slice(-2);
    });
  }

  // ゲームごとのランダムボタン（牌譜URL + スコア）
  function randomPaifuId() {
    var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    var id = '';
    for (var i = 0; i < 12; i++) id += chars[Math.floor(Math.random() * chars.length)];
    return id;
  }
  document.querySelectorAll('.btn-random-game').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var g = btn.getAttribute('data-game');
      var paifuInput = document.querySelector('input[name="paifu_url_' + g + '"]');
      if (paifuInput) paifuInput.value = 'https://example.com/paifu/' + randomPaifuId();
      var scores = genRandomScores();
      var inputs = document.querySelectorAll('input[name^="score_' + g + '_"]');
      for (var i = 0; i < inputs.length && i < scores.length; i++) {
        inputs[i].value = scores[i].toFixed(1);
      }
      refreshAll();
    });
  });

  // 全てランダム入力
  var btnAll = document.getElementById('btn-random-all');
  if (btnAll) {
    btnAll.addEventListener('click', function() {
      var bs = document.getElementById('btn-random-schedule');
      if (bs) bs.click();
      document.querySelectorAll('.btn-random-game').forEach(function(b) { b.click(); });
    });
  }
})();
JS : '';
unset($jsHasSub, $jsIsDev);

require __DIR__ . '/../templates/footer.php';
?>
