<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';

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


// POST処理
$success = isset($_GET['saved']) && $_GET['saved'] === '1';
$validationError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isDone) {
    if (!validateCsrfToken()) {
        http_response_code(403);
        $validationError = '不正なリクエストです。ページを再読み込みしてください。';
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
            if ($playedDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $playedDate)) {
                $validationError = '日付の形式が不正です。';
            } elseif ($playedTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $playedTime)) {
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
        } elseif ($action === 'paifu') {
            $paifuUrl = sanitizeInput('paifu_url');
            if ($paifuUrl !== '' && !filter_var($paifuUrl, FILTER_VALIDATE_URL)) {
                $validationError = '有効なURLを入力してください。';
            } else {
                try {
                    TableInfo::updatePaifuUrl($tableId, $paifuUrl);
                    regenerateCsrfToken();
                    header('Location: table?id=' . $tableId . '&saved=1');
                    exit;
                } catch (PDOException $e) {
                    error_log('[DB] ' . $e->getMessage());
                    $validationError = '保存に失敗しました。';
                }
            }
        } elseif ($action === 'scores') {
            $scores = [];
            $hasError = false;
            foreach ($table['players'] as $p) {
                $pid = (int) $p['player_id'];
                $raw = sanitizeInput('score_' . $pid);
                if ($raw === '') {
                    $validationError = h($p['nickname'] ?? $p['name']) . 'のスコアを入力してください。';
                    $hasError = true;
                    break;
                }
                $score = filter_var($raw, FILTER_VALIDATE_FLOAT);
                if ($score === false) {
                    $validationError = h($p['nickname'] ?? $p['name']) . 'のスコアが不正です。';
                    $hasError = true;
                    break;
                }
                $scores[] = [
                    'player_id' => $pid,
                    'score' => $score,
                    'is_above_cutoff' => true,
                ];
            }
            if (!$hasError) {
                try {
                    RoundResult::saveScores($tournamentId, (int) $table['round_number'], $scores);
                    regenerateCsrfToken();
                    header('Location: table?id=' . $tableId . '&saved=1');
                    exit;
                } catch (PDOException $e) {
                    error_log('[DB] ' . $e->getMessage());
                    $validationError = 'スコアの保存に失敗しました。';
                }
            }
        } elseif ($action === 'bulk') {
            // 一括保存: 日時 + 牌譜URL + スコア + 完了
            $playedDate = sanitizeInput('played_date');
            $playedTime = sanitizeInput('played_time');
            $paifuUrl = sanitizeInput('paifu_url');
            $dayOfWeek = $playedDate !== '' ? DayOfWeek::fromDate($playedDate) : '';

            $scores = [];
            $hasError = false;
            foreach ($table['players'] as $p) {
                $pid = (int) $p['player_id'];
                $raw = sanitizeInput('score_' . $pid);
                if ($raw === '') {
                    $validationError = h($p['nickname'] ?? $p['name']) . 'のスコアを入力してください。';
                    $hasError = true;
                    break;
                }
                $score = filter_var($raw, FILTER_VALIDATE_FLOAT);
                if ($score === false) {
                    $validationError = h($p['nickname'] ?? $p['name']) . 'のスコアが不正です。';
                    $hasError = true;
                    break;
                }
                $scores[] = ['player_id' => $pid, 'score' => $score, 'is_above_cutoff' => true];
            }

            if (!$hasError) {
                try {
                    TableInfo::updateSchedule($tableId, $playedDate ?: null, $dayOfWeek, $playedTime);
                    if ($paifuUrl !== '') {
                        TableInfo::updatePaifuUrl($tableId, $paifuUrl);
                    }
                    RoundResult::saveScores($tournamentId, (int) $table['round_number'], $scores);
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
        } elseif ($action === 'done') {
            // スコアが登録済みか確認
            $hasScores = !empty(array_filter($table['players'], fn($p) => $p['score'] !== null));
            if (!$hasScores) {
                $validationError = '対局結果を先に登録してください。';
            } else {
                try {
                    TableInfo::markDone($tableId);
                    Standing::updateTotals($tournamentId);

                    // 全卓完了チェック → 勝ち抜き判定
                    $roundNum = (int) $table['round_number'];
                    ['data' => $roundTables] = fetchData(fn() => TableInfo::byTournament($tournamentId));
                    $currentRoundTables = $roundTables[$roundNum] ?? [];
                    $allDone = !empty($currentRoundTables) && empty(array_filter($currentRoundTables, fn($t) => !$t['done']));

                    $flashMsg = '卓を完了しました。';
                    if ($allDone) {
                        $rk = 'round_' . $roundNum;
                        $isFinal = (TournamentMeta::get($tournamentId, $rk . '_is_final') === '1');
                        $advanceCount = (int) TournamentMeta::get($tournamentId, $rk . '_advance_count', '0');

                        if (!$isFinal && $advanceCount > 0) {
                            Standing::processRoundAdvancement($tournamentId, $roundNum, $advanceCount);
                            $flashMsg = $roundNum . '回戦が全卓完了しました。勝ち抜き判定を行いました。';
                        } elseif ($isFinal) {
                            $flashMsg = '決勝が完了しました！';
                        } else {
                            $flashMsg = $roundNum . '回戦が全卓完了しました。';
                        }
                    }

                    $_SESSION['flash'] = $flashMsg;
                    regenerateCsrfToken();
                    header('Location: tournament?id=' . $tournamentId);
                    exit;
                } catch (PDOException $e) {
                    error_log('[DB] ' . $e->getMessage());
                    $validationError = '完了処理に失敗しました。';
                }
            }
        }

        // POST後にテーブル情報を再取得
        if ($validationError) {
            ['data' => $table] = fetchData(fn() => TableInfo::findWithPlayers($tableId));
        }
    }
}

$hasScores = !empty(array_filter($table['players'], fn($p) => $p['score'] !== null));

// --- テンプレート変数 ---
$pageTitle = h($table['table_name']) . ' - ' . h($tournament['name']) . ' - 最強位戦';
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
.tb-player-icon { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
.tb-player-noicon { width: 32px; height: 32px; border-radius: 50%; background: var(--glass-border); display: flex; align-items: center; justify-content: center; font-size: 0.4rem; color: var(--text-sub); }
.tb-player-name { font-size: 0.85rem; font-weight: 600; color: var(--text); }

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

.tb-actions { text-align: center; margin-top: 24px; }
CSS;

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
  <div class="tb-section">
    <div class="tb-section-title">参加選手</div>
    <div class="tb-players">
      <?php foreach ($table['players'] as $p): ?>
        <div class="tb-player">
          <?php if ($p['character_icon']): ?>
            <img src="img/chara_deformed/<?= h($p['character_icon']) ?>" alt="<?= h($p['nickname'] ?? $p['name']) ?>" class="tb-player-icon" width="32" height="32" loading="lazy">
          <?php else: ?>
            <div class="tb-player-noicon">NO<br>IMG</div>
          <?php endif; ?>
          <span class="tb-player-name"><?= h($p['nickname'] ?? $p['name']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- 対局日 -->
  <div class="tb-section">
    <div class="tb-section-title" style="display:flex;align-items:center;gap:12px;">
      対局日
      <?php if (!$isDone): ?>
        <button type="button" class="tb-btn-random" id="btn-random-schedule">&#x1F3B2; ランダム</button>
      <?php endif; ?>
    </div>
    <?php if ($isDone): ?>
      <div><?= $table['played_date'] ? h($table['played_date']) . ($table['day_of_week'] ? '（' . h($table['day_of_week']) . '）' : '') . ($table['played_time'] ? ' ' . h($table['played_time']) : '') : '未設定' ?></div>
    <?php else: ?>
      <form method="post" action="table?id=<?= $tableId ?>">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="schedule">
        <div class="tb-form-row">
          <div>
            <label class="tb-label" for="input-date">日付</label>
            <input type="date" id="input-date" name="played_date" class="tb-input" value="<?= h($table['played_date'] ?? '') ?>">
          </div>
          <div>
            <label class="tb-label" for="input-time">時間</label>
            <input type="time" id="input-time" name="played_time" class="tb-input" value="<?= h($table['played_time'] ?? '') ?>">
          </div>
          <button type="submit" class="tb-btn-small">保存</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <!-- 牌譜URL -->
  <div class="tb-section">
    <div class="tb-section-title" style="display:flex;align-items:center;gap:12px;">
      牌譜URL
      <?php if (!$isDone): ?>
        <button type="button" class="tb-btn-random" id="btn-random-paifu">&#x1F3B2; ランダム</button>
      <?php endif; ?>
    </div>
    <?php if ($isDone && $table['paifu_url']): ?>
      <a href="<?= h($table['paifu_url']) ?>" class="tb-paifu-link" target="_blank" rel="noopener noreferrer"><?= h($table['paifu_url']) ?></a>
    <?php elseif ($isDone): ?>
      <div style="color: var(--text-sub); font-size: 0.85rem;">未登録</div>
    <?php else: ?>
      <form method="post" action="table?id=<?= $tableId ?>">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="paifu">
        <div class="tb-form-row">
          <div style="flex: 1;">
            <input type="url" name="paifu_url" class="tb-input" style="width: 100%;" value="<?= h($table['paifu_url'] ?? '') ?>" placeholder="https://game.mahjongsoul.com/...">
          </div>
          <button type="submit" class="tb-btn-small">保存</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <!-- 対局結果 -->
  <div class="tb-section">
    <div class="tb-section-title" style="display:flex;align-items:center;gap:12px;">
      対局結果
      <?php if (!$isDone): ?>
        <button type="button" class="tb-btn-random" id="btn-random-score">&#x1F3B2; ランダム</button>
      <?php endif; ?>
    </div>
    <?php if ($isDone): ?>
      <div class="tb-score-grid">
        <?php
          $sorted = $table['players'];
          usort($sorted, fn($a, $b) => (float) $b['score'] <=> (float) $a['score']);
          foreach ($sorted as $i => $p):
            $score = (float) $p['score'];
        ?>
          <div class="tb-score-row">
            <span class="tb-score-name"><?= $i + 1 ?>位 <?= h($p['nickname'] ?? $p['name']) ?></span>
            <span class="tb-score-value <?= $score >= 0 ? 'plus' : 'minus' ?>"><?= $score >= 0 ? '+' : '' ?><?= h((string) $score) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <form method="post" action="table?id=<?= $tableId ?>">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="scores">
        <div class="tb-score-grid">
          <?php foreach ($table['players'] as $p): ?>
            <div class="tb-score-row">
              <span class="tb-score-name"><?= h($p['nickname'] ?? $p['name']) ?></span>
              <input type="number" name="score_<?= (int) $p['player_id'] ?>" class="tb-input tb-score-input" step="0.1" value="<?= $p['score'] !== null ? h((string) $p['score']) : '' ?>" placeholder="0.0">
            </div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top: 16px; text-align: right;">
          <button type="submit" class="tb-btn-small">保存</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <!-- 完了操作 -->
  <?php if (!$isDone): ?>
    <div class="tb-section">
      <div class="tb-done-section">
        <form method="post" action="table?id=<?= $tableId ?>" onsubmit="return confirm('この卓を完了にしますか？')">
          <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
          <input type="hidden" name="action" value="done">
          <button type="submit" class="tb-btn-done" <?= $hasScores ? '' : 'disabled' ?>>卓を完了にする</button>
        </form>
        <?php if (!$hasScores): ?>
          <div style="margin-top: 8px; font-size: 0.75rem; color: var(--text-sub);">対局結果を登録すると完了にできます。</div>
        <?php endif; ?>
      </div>
    </div>
  <?php if (!$isDone): ?>
    <!-- 一括保存（デバッグ用） -->
    <div class="tb-section" style="border-color: rgba(var(--gold-rgb), 0.3); background: rgba(var(--gold-rgb), 0.02);">
      <div class="tb-section-title" style="display:flex;align-items:center;gap:12px; border-color: rgba(var(--gold-rgb), 0.2);">
        一括保存
        <button type="button" class="tb-btn-random" id="btn-random-all">&#x1F3B2; 全てランダム入力</button>
      </div>
      <div class="tn-hint" style="margin-bottom: 12px;">日時・牌譜URL・スコアをまとめて保存し、卓を完了にします。</div>
      <form method="post" action="table?id=<?= $tableId ?>" id="bulk-form">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="bulk">
        <input type="hidden" name="played_date" id="bulk-date">
        <input type="hidden" name="played_time" id="bulk-time">
        <input type="hidden" name="paifu_url" id="bulk-paifu">
        <?php foreach ($table['players'] as $p): ?>
          <input type="hidden" name="score_<?= (int) $p['player_id'] ?>" class="bulk-score" data-pid="<?= (int) $p['player_id'] ?>">
        <?php endforeach; ?>
        <button type="submit" class="tb-btn-done" id="btn-bulk-save">まとめて保存 &amp; 完了</button>
      </form>
    </div>
  <?php endif; ?>

  <?php else: ?>
    <div class="tb-section" style="text-align: center;">
      <div class="tb-completed-badge">完了</div>
    </div>
  <?php endif; ?>

  <div class="tb-actions">
    <a href="tournament?id=<?= $tournamentId ?>" class="btn-cancel">&#x2190; 大会ページに戻る</a>
  </div>
</div>

<?php
$meta = $tournament['meta'];
$jsStartingPoints = (int) ($meta['starting_points'] ?? 25000);
$jsReturnPoints = (int) ($meta['return_points'] ?? 30000);
$jsPlayerMode = (int) ($meta['player_mode'] ?? 4);
$jsPlayerCount = count($table['players']);

$pageInlineScript = <<<JS
(function() {
  // ランダム日時生成
  var btnRandSched = document.getElementById('btn-random-schedule');
  if (btnRandSched) {
    btnRandSched.addEventListener('click', function() {
      var now = new Date();
      var offset = Math.floor(Math.random() * 14) + 1; // 1〜14日後
      var d = new Date(now.getTime() + offset * 86400000);
      var y = d.getFullYear();
      var m = ('0' + (d.getMonth() + 1)).slice(-2);
      var day = ('0' + d.getDate()).slice(-2);
      var h = Math.floor(Math.random() * 12) + 10; // 10〜21時
      var min = [0, 30][Math.floor(Math.random() * 2)];
      var dateInput = document.getElementById('input-date');
      var timeInput = document.getElementById('input-time');
      if (dateInput) dateInput.value = y + '-' + m + '-' + day;
      if (timeInput) timeInput.value = ('0' + h).slice(-2) + ':' + ('0' + min).slice(-2);
    });
  }

  // ランダム牌譜URL生成
  var btnRandPaifu = document.getElementById('btn-random-paifu');
  if (btnRandPaifu) {
    btnRandPaifu.addEventListener('click', function() {
      var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
      var id = '';
      for (var i = 0; i < 12; i++) id += chars[Math.floor(Math.random() * chars.length)];
      var input = document.querySelector('input[name="paifu_url"]');
      if (input) input.value = 'https://example.com/paifu/' + id;
    });
  }

  // 一括フォームの値を各入力欄から同期
  var bulkForm = document.getElementById('bulk-form');
  if (bulkForm) {
    bulkForm.addEventListener('submit', function(e) {
      // 値コピーを先に実行
      var d = document.getElementById('input-date');
      var t = document.getElementById('input-time');
      var p = document.querySelector('input[name="paifu_url"]');
      if (d) document.getElementById('bulk-date').value = d.value;
      if (t) document.getElementById('bulk-time').value = t.value;
      if (p) document.getElementById('bulk-paifu').value = p.value;
      document.querySelectorAll('.bulk-score').forEach(function(h) {
        var src = document.querySelector('input[name="score_' + h.getAttribute('data-pid') + '"]:not(.bulk-score)');
        if (src) h.value = src.value;
      });
      // 値コピー後に確認ダイアログ
      if (!confirm('全項目を保存して卓を完了にしますか？')) {
        e.preventDefault();
      }
    });
  }

  // 全てランダム入力
  var btnAll = document.getElementById('btn-random-all');
  if (btnAll) {
    btnAll.addEventListener('click', function() {
      var bs = document.getElementById('btn-random-schedule');
      var bp = document.getElementById('btn-random-paifu');
      var br = document.getElementById('btn-random-score');
      if (bs) bs.click();
      if (bp) bp.click();
      if (br) br.click();
    });
  }

  // ランダムスコア生成
  var btnRandom = document.getElementById('btn-random-score');
  if (btnRandom) {
    var startPt = {$jsStartingPoints};
    var returnPt = {$jsReturnPoints};
    var pMode = {$jsPlayerMode};
    var pCount = {$jsPlayerCount};

    btnRandom.addEventListener('click', function() {
      // 現実的な最終持ち点を生成（配給原点ベース）
      // 合計は startPt * pCount（持ち点の総和は不変）
      var totalPool = startPt * pCount;
      var rawPoints = [];
      var remaining = totalPool;

      // 各プレイヤーの最終持ち点をランダム生成
      for (var i = 0; i < pCount - 1; i++) {
        // 残りをざっくり分配（標準偏差を持たせる）
        var avg = remaining / (pCount - i);
        var deviation = startPt * 0.6;
        var pts = Math.round((avg + (Math.random() - 0.5) * 2 * deviation) / 100) * 100;
        // 最低100点は残す（トビなしの場合）
        pts = Math.max(100, Math.min(remaining - (pCount - i - 1) * 100, pts));
        rawPoints.push(pts);
        remaining -= pts;
      }
      rawPoints.push(remaining);

      // 降順ソート
      rawPoints.sort(function(a, b) { return b - a; });

      // 返し点からの差分を1000で割ってポイント化（順位ウマ付き）
      var uma;
      if (pMode === 3) {
        uma = [15, 0, -15]; // 三麻ウマ
      } else {
        uma = [20, 10, -10, -20]; // 四麻ウマ
      }

      var scores = [];
      for (var i = 0; i < pCount; i++) {
        var diff = (rawPoints[i] - returnPt) / 1000;
        var u = i < uma.length ? uma[i] : 0;
        scores.push(Math.round((diff + u) * 10) / 10);
      }

      // 合計を0に補正（1位に端数を載せる）
      var sum = 0;
      for (var i = 0; i < scores.length; i++) sum += scores[i];
      scores[0] = Math.round((scores[0] - sum) * 10) / 10;

      // シャッフルして各入力欄にセット（順位はランダム）
      var indices = [];
      for (var i = 0; i < scores.length; i++) indices.push(i);
      for (var i = indices.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var t = indices[i]; indices[i] = indices[j]; indices[j] = t;
      }

      var inputs = document.querySelectorAll('.tb-score-input');
      for (var i = 0; i < inputs.length && i < scores.length; i++) {
        inputs[i].value = scores[indices[i]].toFixed(1);
      }
    });
  }
})();
JS;

require __DIR__ . '/../templates/footer.php';
?>
