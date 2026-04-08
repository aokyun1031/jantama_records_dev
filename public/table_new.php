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

$meta = $tournament['meta'];
$playerMode = (int) ($meta['player_mode'] ?? 4);

// 勝ち抜き中の選手のみ取得（eliminated_round = 0）
$activePlayerIds = Standing::activePlayerIds($tournamentId);

['data' => $registeredPlayerIds] = fetchData(fn() => Tournament::playerIds($tournamentId));
['data' => $allPlayers] = fetchData(fn() => Player::all());

$tournamentPlayers = array_values(array_filter(
    $allPlayers ?? [],
    fn($p) => in_array((int) $p['id'], $activePlayerIds, true)
));

// 次のラウンド番号を自動決定
['data' => $existingRounds] = fetchData(fn() => TableInfo::byTournament($tournamentId));
$nextRound = empty($existingRounds) ? 1 : max(array_keys($existingRounds)) + 1;
$prevRound = $nextRound - 1;

// 前ラウンドの卓組み（同卓回避用）
$prevGroups = [];
if ($prevRound >= 1) {
    ['data' => $prevGroups] = fetchData(fn() => TableInfo::playerGroupsByRound($tournamentId, $prevRound));
}

// 順位データ（成績考慮用）
$jsStandings = Standing::totalMap($tournamentId);

// POST処理
$validationError = '';
$postRoundNumber = (string) $nextRound;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        http_response_code(403);
        $validationError = '不正なリクエストです。ページを再読み込みしてください。';
    } else {
        $roundNumber = $nextRound;
        $postTables = $_POST['tables'] ?? [];

        $validIds = $activePlayerIds;
        $tablesData = [];
        $allAssigned = [];

        if (empty($postTables)) {
            $validationError = '卓を作成してください。';
        } else {
            foreach ($postTables as $t) {
                $name = trim($t['name'] ?? '');
                $playerIds = array_map('intval', $t['player_ids'] ?? []);
                if ($name === '' || mb_strlen($name) > 20) {
                    $validationError = '卓名が不正です。';
                    break;
                }
                if (empty($playerIds)) {
                    continue; // 代打ちのみの卓はスキップ
                }
                if (array_diff($playerIds, $validIds)) {
                    $validationError = '不正な選手が含まれています。';
                    break;
                }
                if (array_intersect($playerIds, $allAssigned)) {
                    $validationError = '同じ選手が複数の卓に割り当てられています。';
                    break;
                }
                $allAssigned = array_merge($allAssigned, $playerIds);
                $tablesData[] = ['name' => $name, 'player_ids' => $playerIds];
            }

            if (!$validationError && empty($tablesData)) {
                $validationError = '有効な卓がありません。';
            }

            if (!$validationError) {
                // ラウンド設定
                $isFinal = isset($_POST['is_final']) && $_POST['is_final'] === '1';
                $advanceCount = (int) ($_POST['advance_count'] ?? 2);
                $advanceMode = sanitizeInput('advance_mode');
                if (!in_array($advanceMode, ['per_table', 'overall'], true)) {
                    $advanceMode = 'per_table';
                }
                $gameCount = (int) ($_POST['game_count'] ?? 2);
                $gameType = sanitizeInput('game_type');
                $validGameTypes = ['hanchan', 'tonpu', 'ikkyoku'];
                if (!in_array($gameType, $validGameTypes, true)) {
                    $gameType = $meta['round_type'] ?? 'hanchan';
                }
                if ($advanceCount < 1) $advanceCount = 1;
                if ($gameCount < 1) $gameCount = 1;

                try {
                    TableInfo::createBatch($tournamentId, $roundNumber, $tablesData);
                    Tournament::start($tournamentId);
                    // ラウンド設定を保存
                    $rk = 'round_' . $roundNumber;
                    TournamentMeta::set($tournamentId, $rk . '_is_final', $isFinal ? '1' : '0');
                    TournamentMeta::set($tournamentId, $rk . '_advance_count', (string) $advanceCount);
                    TournamentMeta::set($tournamentId, $rk . '_advance_mode', $advanceMode);
                    TournamentMeta::set($tournamentId, $rk . '_game_count', (string) $gameCount);
                    TournamentMeta::set($tournamentId, $rk . '_game_type', $gameType);
                    $_SESSION['flash'] = count($tablesData) . '卓を作成しました。';
                    regenerateCsrfToken();
                    header('Location: tournament?id=' . $tournamentId);
                    exit;
                } catch (PDOException $e) {
                    error_log('[DB] ' . $e->getMessage());
                    $validationError = '卓の作成に失敗しました。';
                }
            }
        }
    }
}

// 決勝卓の自動判定（残り人数が1卓分以下なら必ず決勝）
$isForcedFinal = count($tournamentPlayers) <= $playerMode;

// JS用選手データ
$jsPlayers = array_map(fn($p) => [
    'id' => (int) $p['id'],
    'name' => $p['nickname'] ?? $p['name'],
    'icon' => $p['character_icon'] ?? '',
], $tournamentPlayers);

// --- テンプレート変数 ---
$pageTitle = '卓作成 - ' . h($tournament['name']) . ' - ' . SITE_NAME;
$pageCss = ['css/forms.css'];
$pageStyle = <<<'CSS'
.tn-hero { text-align: center; padding: 48px 20px 24px; }
.tn-badge { display: inline-block; background: var(--badge-bg); color: var(--badge-color); font-size: 0.7rem; font-weight: 700; padding: 4px 14px; border-radius: 20px; margin-bottom: 16px; letter-spacing: 2px; box-shadow: 0 2px 12px rgba(var(--accent-rgb),0.3); }
.tn-title { font-family: 'Noto Sans JP', sans-serif; font-size: clamp(1.4rem, 5vw, 2rem); font-weight: 900; color: var(--text); margin-bottom: 4px; }
.tn-subtitle { font-size: 0.85rem; color: var(--text-sub); }

.tn-content { max-width: 900px; margin: 0 auto 40px; padding: 0 16px; }

.tn-section { background: var(--card); border: 1px solid rgba(var(--accent-rgb), 0.25); border-radius: var(--radius); padding: 24px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.tn-hint { font-size: 0.75rem; color: var(--text-sub); margin-top: 6px; }

/* オプション */
.tn-options { margin-top: 16px; display: flex; flex-direction: column; gap: 12px; }
.tn-option-group { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.tn-option-label { font-size: 0.8rem; font-weight: 700; color: var(--text); min-width: 80px; flex-shrink: 0; }
.tn-toggle { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.8rem; color: var(--text-sub); font-weight: 600; }
.tn-toggle input { display: none; }
.tn-toggle-track { width: 36px; height: 20px; border-radius: 10px; background: var(--glass-border); position: relative; transition: background 0.2s; flex-shrink: 0; }
.tn-toggle input:checked + .tn-toggle-track { background: var(--purple); }
.tn-toggle-track::after { content: ''; position: absolute; width: 16px; height: 16px; border-radius: 50%; background: #fff; top: 2px; left: 2px; transition: transform 0.2s; }
.tn-toggle input:checked + .tn-toggle-track::after { transform: translateX(16px); }
.tn-toggle input:disabled + .tn-toggle-track { opacity: 0.4; cursor: not-allowed; }
.tn-radio-group { display: flex; gap: 6px; flex-wrap: wrap; }
.tn-radio { display: none; }
.tn-radio-label { display: inline-block; padding: 6px 14px; border: 2px solid var(--input-border); border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; color: var(--text-sub); cursor: pointer; transition: border-color 0.2s, background 0.2s, color 0.2s; }
.tn-radio:checked + .tn-radio-label { border-color: var(--purple); background: rgba(var(--accent-rgb), 0.08); color: var(--text); }
.tn-radio-label:hover { border-color: var(--input-border-hover); }
.tn-select { padding: 6px 12px; border: 1.5px solid var(--input-border); border-radius: var(--radius-sm); font-size: 0.8rem; font-weight: 700; font-family: 'Noto Sans JP', sans-serif; background: var(--input-bg); color: var(--text); cursor: pointer; transition: border-color 0.2s, box-shadow 0.2s; }
.tn-select:hover:not(:focus) { border-color: var(--input-border-hover); }
.tn-select:focus { outline: none; border-color: var(--input-border-focus); box-shadow: var(--input-focus-ring); }
.tn-advance-preview { font-size: 0.8rem; font-weight: 600; line-height: 1.6; padding: 10px 14px; border-radius: var(--radius-sm); margin-left: 80px; }
.tn-advance-preview.ok { background: rgba(var(--mint-rgb), 0.08); color: var(--success); border: 1px solid rgba(var(--mint-rgb), 0.2); }
.tn-advance-preview.warn { background: rgba(var(--gold-rgb), 0.08); color: var(--gold); border: 1px solid rgba(var(--gold-rgb), 0.2); }
.tn-advance-step { display: flex; align-items: center; gap: 6px; }
.tn-advance-arrow { color: var(--text-light); font-size: 0.7rem; }
.tn-section-title { font-weight: 800; font-size: 0.9rem; color: var(--text); margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid rgba(var(--accent-rgb), 0.15); }

/* 生成ボタン */
.tn-generate-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.tn-btn-generate { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: var(--btn-secondary-bg); color: var(--btn-text-color); border: none; border-radius: 10px; font-weight: 700; font-size: 0.9rem; font-family: 'Noto Sans JP', sans-serif; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 3px 12px rgba(var(--mint-rgb), 0.25); }
.tn-btn-generate:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(var(--mint-rgb), 0.35); }
.tn-btn-generate:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
.tn-generate-info { font-size: 0.8rem; color: var(--text-sub); }

/* 卓グリッド */
.tn-tables { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-top: 20px; }
.tn-table { background: var(--card); border: 1px solid var(--glass-border); border-radius: var(--radius); padding: 16px; }
.tn-table-name { font-weight: 800; font-size: 0.95rem; color: var(--text); margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid rgba(var(--accent-rgb), 0.15); text-align: center; }
.tn-slots { display: flex; flex-direction: column; gap: 8px; }

/* 選手スロット（ドラッグ可能） */
.tn-slot {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 12px;
  border: 1.5px solid rgba(var(--accent-rgb), 0.2);
  border-radius: var(--radius-sm);
  background: var(--card);
  cursor: grab;
  transition: border-color 0.15s, box-shadow 0.15s, opacity 0.15s;
  user-select: none;
  -webkit-user-select: none;
}
.tn-slot:hover { border-color: rgba(var(--accent-rgb), 0.4); box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.tn-slot.dragging { opacity: 0.4; }
.tn-slot.drag-over { border-color: var(--purple); box-shadow: 0 0 0 3px rgba(var(--accent-rgb), 0.15); background: rgba(var(--accent-rgb), 0.06); }
.tn-slot-icon { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0; pointer-events: none; }
.tn-slot-noicon { width: 28px; height: 28px; border-radius: 50%; background: var(--glass-border); display: flex; align-items: center; justify-content: center; font-size: 0.35rem; color: var(--text-sub); flex-shrink: 0; pointer-events: none; }
.tn-slot-name { font-size: 0.85rem; font-weight: 600; color: var(--text); pointer-events: none; }
.tn-slot-grip { margin-left: auto; color: var(--text-light); font-size: 0.8rem; pointer-events: none; }

/* 代打ちスロット */
.tn-slot.sub {
  border-style: dashed;
  border-color: rgba(var(--gold-rgb), 0.4);
  background: rgba(var(--gold-rgb), 0.04);
  cursor: default;
}
.tn-slot.sub .tn-slot-name { color: var(--gold); font-style: italic; }

.tn-sub-notice { display: flex; align-items: center; gap: 8px; padding: 12px 16px; margin-top: 16px; border-radius: var(--radius-sm); background: rgba(var(--gold-rgb), 0.08); border: 1px solid rgba(var(--gold-rgb), 0.2); font-size: 0.8rem; color: var(--gold); font-weight: 700; }

/* 初期状態（卓未生成） */
.tn-empty { text-align: center; padding: 40px 20px; color: var(--text-sub); font-size: 0.85rem; }

/* アクション */
.tn-actions { display: flex; gap: 12px; justify-content: center; margin-top: 24px; }
.tn-btn-save { display: inline-block; padding: 12px 32px; background: var(--btn-primary-bg); color: var(--btn-text-color); border: none; border-radius: 12px; font-weight: 700; font-size: 0.9rem; font-family: 'Noto Sans JP', sans-serif; cursor: pointer; transition: transform 0.3s, box-shadow 0.3s; box-shadow: 0 4px 16px rgba(var(--accent-rgb), 0.3); }
.tn-btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(var(--accent-rgb), 0.4); }
.tn-btn-save:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
.tn-btn-cancel { display: inline-flex; align-items: center; padding: 12px 24px; background: var(--card); color: var(--text-sub); border: 1px solid var(--glass-border); border-radius: 12px; font-weight: 700; font-size: 0.9rem; font-family: 'Noto Sans JP', sans-serif; text-decoration: none; transition: transform 0.3s; }
.tn-btn-cancel:hover { transform: translateY(-2px); }

CSS;

require __DIR__ . '/../templates/header.php';
?>

<div class="tn-hero">
  <div class="tn-badge">NEW TABLES</div>
  <div class="tn-title"><?= $nextRound ?>回戦 卓作成</div>
  <div class="tn-subtitle"><?= h($tournament['name']) ?></div>
</div>

<div class="tn-content">
  <?php if ($validationError): ?>
    <div class="edit-message error"><?= h($validationError) ?></div>
  <?php endif; ?>

<?php
  $defaultGameType = $meta['round_type'] ?? 'hanchan';
  $gameTypeLabels = [];
  foreach (RoundType::cases() as $rt) { $gameTypeLabels[$rt->value] = $rt->label() . '戦'; }
?>
  <form method="post" action="table_new?tournament_id=<?= $tournamentId ?>" id="table-form">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

    <!-- ラウンド設定 -->
    <div class="tn-section">
      <div class="tn-section-title"><?= $nextRound ?>回戦 ラウンド設定</div>
      <div class="tn-options">
        <?php if ($isForcedFinal): ?>
          <input type="hidden" name="is_final" value="1">
          <div class="tn-option-group">
            <span class="tn-option-label">決勝卓</span>
            <label class="tn-toggle">
              <input type="checkbox" checked disabled id="opt-final">
              <span class="tn-toggle-track"></span>
              決勝（残り<?= count($tournamentPlayers) ?>名）
            </label>
          </div>
        <?php else: ?>
          <div class="tn-option-group">
            <span class="tn-option-label">決勝卓</span>
            <label class="tn-toggle">
              <input type="checkbox" name="is_final" value="1" id="opt-final">
              <span class="tn-toggle-track"></span>
              このラウンドで優勝者を決定する
            </label>
          </div>
          <div class="tn-option-group" id="advance-group">
            <span class="tn-option-label">勝ち抜け</span>
            <div class="tn-radio-group">
              <input type="radio" name="advance_mode" value="per_table" id="amode-table" class="tn-radio" checked>
              <label for="amode-table" class="tn-radio-label">各卓</label>
              <input type="radio" name="advance_mode" value="overall" id="amode-overall" class="tn-radio">
              <label for="amode-overall" class="tn-radio-label">全体</label>
            </div>
            <select name="advance_count" class="tn-select" id="select-advance">
              <?php for ($i = 1; $i <= $playerMode - 1; $i++): ?>
                <option value="<?= $i ?>" <?= $i === 2 ? 'selected' : '' ?>>上位<?= $i ?>名</option>
              <?php endfor; ?>
            </select>
          </div>
          <div id="advance-preview" class="tn-advance-preview" style="display:none;"></div>
        <?php endif; ?>
        <div class="tn-option-group">
          <span class="tn-option-label">対局数</span>
          <select name="game_count" class="tn-select">
            <option value="1">1回</option>
            <option value="2" selected>2回</option>
            <option value="3">3回</option>
            <option value="4">4回</option>
          </select>
        </div>
        <div class="tn-option-group">
          <span class="tn-option-label">局数</span>
          <div class="tn-radio-group">
            <?php foreach ($gameTypeLabels as $val => $label): ?>
              <input type="radio" name="game_type" value="<?= $val ?>" id="gt-<?= $val ?>" class="tn-radio" <?= $val === $defaultGameType ? 'checked' : '' ?>>
              <label for="gt-<?= $val ?>" class="tn-radio-label"><?= h($label) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <?php if (!$isForcedFinal): ?>
    <!-- 生成コントロール -->
    <div class="tn-section">
      <div class="tn-generate-row">
        <button type="button" class="tn-btn-generate" id="btn-generate" <?= empty($tournamentPlayers) || count($tournamentPlayers) < $playerMode ? 'disabled' : '' ?>>
          &#x1F3B2; ランダムに卓を作成
        </button>
        <span class="tn-generate-info" id="generate-info"></span>
      </div>
      <div class="tn-hint">ボタンを押すと選手をランダムに卓へ振り分けます。何度でも押し直せます。</div>

      <div class="tn-options">
        <div class="tn-option-group">
          <span class="tn-option-label">同卓回避</span>
          <label class="tn-toggle" id="toggle-avoid">
            <input type="checkbox" id="opt-avoid" <?= $prevRound < 1 ? 'disabled' : '' ?>>
            <span class="tn-toggle-track"></span>
            <?= $prevRound >= 1 ? '前回戦の同卓を避ける' : '（前回戦データなし）' ?>
          </label>
        </div>
        <div class="tn-option-group">
          <span class="tn-option-label">成績考慮</span>
          <div class="tn-radio-group">
            <input type="radio" name="ranking_mode" value="none" id="rank-none" class="tn-radio" checked>
            <label for="rank-none" class="tn-radio-label">なし</label>
            <input type="radio" name="ranking_mode" value="swiss" id="rank-swiss" class="tn-radio">
            <label for="rank-swiss" class="tn-radio-label">スイスドロー</label>
            <input type="radio" name="ranking_mode" value="pot" id="rank-pot" class="tn-radio">
            <label for="rank-pot" class="tn-radio-label">ポット分け</label>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- 卓表示エリア -->
    <div id="tables-area">
      <div class="tn-empty" id="tables-empty">ボタンを押して卓を生成してください。</div>
    </div>

    <!-- hidden inputs for POST -->
    <div id="tables-data"></div>

    <div class="tn-actions">
      <a href="tournament?id=<?= $tournamentId ?>" class="btn-cancel">&#x2190; 大会ページに戻る</a>
      <button type="submit" class="tn-btn-save" id="btn-save" disabled>卓を保存</button>
    </div>
  </form>
</div>

<?php
$jsPlayersJson = json_encode($jsPlayers, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$jsPrevGroupsJson = json_encode($prevGroups ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$jsStandingsJson = json_encode($jsStandings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$pageInlineScript = <<<JS
(function() {
  var players = {$jsPlayersJson};
  var playerMode = {$playerMode};
  var prevGroups = {$jsPrevGroupsJson};
  var standings = {$jsStandingsJson};
  var tables = [];
  var dragSrc = null;

  // 決勝トグルで勝ち抜け欄の表示切替
  var optFinal = document.getElementById('opt-final');
  var advanceGroup = document.getElementById('advance-group');
  var advancePreview = document.getElementById('advance-preview');
  var selectAdvance = document.getElementById('select-advance');
  if (optFinal && advanceGroup && !optFinal.disabled) {
    optFinal.addEventListener('change', function() {
      advanceGroup.style.display = this.checked ? 'none' : '';
      updateAdvancePreview();
    });
  }
  if (selectAdvance) {
    selectAdvance.addEventListener('change', updateAdvancePreview);
  }

  // 勝ち抜けモード切替
  var advanceModeRadios = document.querySelectorAll('input[name="advance_mode"]');
  advanceModeRadios.forEach(function(radio) {
    radio.addEventListener('change', function() {
      rebuildAdvanceOptions();
      updateAdvancePreview();
    });
  });

  function getAdvanceMode() {
    var checked = document.querySelector('input[name="advance_mode"]:checked');
    return checked ? checked.value : 'per_table';
  }

  function rebuildAdvanceOptions() {
    if (!selectAdvance) return;
    var mode = getAdvanceMode();
    var totalPlayers = players.length;
    var max = mode === 'overall' ? totalPlayers - 1 : playerMode - 1;
    var oldVal = parseInt(selectAdvance.value);
    selectAdvance.innerHTML = '';
    for (var i = 1; i <= max; i++) {
      var opt = document.createElement('option');
      opt.value = i;
      opt.textContent = '上位' + i + '名';
      selectAdvance.appendChild(opt);
    }
    if (mode === 'overall') {
      var defaultVal = Math.ceil(totalPlayers / 2);
      selectAdvance.value = (oldVal > 0 && oldVal <= max) ? oldVal : defaultVal;
    } else {
      selectAdvance.value = (oldVal > 0 && oldVal <= max) ? oldVal : 2;
    }
  }

  function updateAdvancePreview() {
    if (!advancePreview) return;
    // 決勝チェック時は非表示
    if (optFinal && optFinal.checked) {
      advancePreview.style.display = 'none';
      return;
    }
    var tableCount = tables.length;
    if (tableCount === 0) {
      advancePreview.style.display = 'none';
      return;
    }
    var advance = selectAdvance ? parseInt(selectAdvance.value) : 2;
    var mode = getAdvanceMode();
    // シミュレーション: 決勝まで追う
    var steps = [];
    var cur = mode === 'overall' ? advance : tableCount * advance;
    var hasWarn = false;
    for (var round = 0; round < 10; round++) {
      var tc = Math.ceil(cur / playerMode);
      var subs = tc * playerMode - cur;
      var isFinal = tc === 1;
      steps.push({ players: cur, tables: tc, subs: subs, final: isFinal });
      if (subs > 0) hasWarn = true;
      if (isFinal) break;
      cur = tc * advance;
    }
    // 描画
    var html = '';
    for (var i = 0; i < steps.length; i++) {
      var s = steps[i];
      var label = s.final ? '決勝' : (i + 1) + '回戦後';
      html += '<div class="tn-advance-step">';
      if (i > 0) html += '<span class="tn-advance-arrow">\u2192</span>';
      html += '<span>' + label + ' ' + s.players + '名（' + s.tables + '卓）';
      if (s.subs > 0) html += ' <span style="color:var(--gold)">代打ち' + s.subs + '名</span>';
      html += '</span></div>';
    }
    advancePreview.innerHTML = html;
    advancePreview.className = 'tn-advance-preview ' + (hasWarn ? 'warn' : 'ok');
    advancePreview.style.display = '';
  }

  var area = document.getElementById('tables-area');
  var emptyEl = document.getElementById('tables-empty');
  var dataEl = document.getElementById('tables-data');
  var btnGen = document.getElementById('btn-generate');
  var btnSave = document.getElementById('btn-save');
  var infoEl = document.getElementById('generate-info');
  var optAvoid = document.getElementById('opt-avoid');
  var forcedFinal = players.length <= playerMode;

  if (forcedFinal) {
    // 決勝: 全員を1卓に自動配置
    tables = buildTables(players);
    render();
    updateData();
    btnSave.disabled = false;
  } else if (btnGen) {
    if (players.length < playerMode) {
      if (infoEl) infoEl.textContent = '選手が' + playerMode + '人未満のため卓を作成できません。';
      btnGen.disabled = true;
    }
    btnGen.addEventListener('click', generate);
  }

  // --- ユーティリティ ---
  function shuffle(arr) {
    var a = arr.slice();
    for (var i = a.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var t = a[i]; a[i] = a[j]; a[j] = t;
    }
    return a;
  }

  function buildTables(ordered) {
    var tc = Math.ceil(ordered.length / playerMode);
    var tbls = [];
    for (var t = 0; t < tc; t++) {
      var slots = [];
      for (var s = 0; s < playerMode; s++) {
        var idx = t * playerMode + s;
        if (idx < ordered.length) {
          var p = ordered[idx];
          slots.push({ id: p.id, name: p.name, icon: p.icon });
        } else {
          slots.push({ sub: true });
        }
      }
      tbls.push({ name: (t + 1) + '卓', slots: slots });
    }
    return tbls;
  }

  // --- 同卓回避ペアセット ---
  var avoidPairs = {};
  prevGroups.forEach(function(g) {
    for (var i = 0; i < g.length; i++) {
      for (var j = i + 1; j < g.length; j++) {
        avoidPairs[g[i] + ':' + g[j]] = true;
        avoidPairs[g[j] + ':' + g[i]] = true;
      }
    }
  });

  function hasConflict(tbls) {
    for (var t = 0; t < tbls.length; t++) {
      var ids = tbls[t].slots.filter(function(s) { return !s.sub; }).map(function(s) { return s.id; });
      for (var i = 0; i < ids.length; i++) {
        for (var j = i + 1; j < ids.length; j++) {
          if (avoidPairs[ids[i] + ':' + ids[j]]) return { ti: t, si1: i, si2: j };
        }
      }
    }
    return null;
  }

  function applyAvoidance(tbls) {
    for (var attempt = 0; attempt < 200; attempt++) {
      var c = hasConflict(tbls);
      if (!c) break;
      // swap one conflicting player with a random player from another table
      var si = c.si2; // index within real-player slots
      var realSlots = [];
      tbls[c.ti].slots.forEach(function(s, idx) { if (!s.sub) realSlots.push(idx); });
      var srcSlotIdx = realSlots[si];

      // find a swap candidate from other tables
      var candidates = [];
      for (var ot = 0; ot < tbls.length; ot++) {
        if (ot === c.ti) continue;
        tbls[ot].slots.forEach(function(s, os) {
          if (!s.sub) candidates.push({ ti: ot, si: os });
        });
      }
      if (candidates.length === 0) break;
      var pick = candidates[Math.floor(Math.random() * candidates.length)];
      var tmp = tbls[c.ti].slots[srcSlotIdx];
      tbls[c.ti].slots[srcSlotIdx] = tbls[pick.ti].slots[pick.si];
      tbls[pick.ti].slots[pick.si] = tmp;
    }
    return tbls;
  }

  // --- 生成アルゴリズム ---
  function generateRandom() {
    return buildTables(shuffle(players));
  }

  function generateSwiss() {
    // 成績順に並べてそのまま卓に振り分け（近い順位同士が同卓）
    var sorted = players.slice().sort(function(a, b) {
      return (standings[b.id] || 0) - (standings[a.id] || 0);
    });
    // 各卓内の席順をシャッフル
    var tbls = buildTables(sorted);
    tbls.forEach(function(t) { t.slots = shuffle(t.slots); });
    return tbls;
  }

  function generatePot() {
    // 成績順にソートしてポット分け
    var sorted = players.slice().sort(function(a, b) {
      return (standings[b.id] || 0) - (standings[a.id] || 0);
    });
    var tc = Math.ceil(sorted.length / playerMode);
    var tbls = [];
    for (var t = 0; t < tc; t++) {
      var slots = [];
      for (var s = 0; s < playerMode; s++) slots.push({ sub: true });
      tbls.push({ name: (t + 1) + '卓', slots: slots });
    }
    // ポットごとにシャッフルして各卓に1人ずつ配置
    for (var pot = 0; pot < playerMode; pot++) {
      var potPlayers = sorted.slice(pot * tc, (pot + 1) * tc);
      potPlayers = shuffle(potPlayers);
      for (var t = 0; t < potPlayers.length && t < tc; t++) {
        tbls[t].slots[pot] = { id: potPlayers[t].id, name: potPlayers[t].name, icon: potPlayers[t].icon };
      }
    }
    // 残り（端数）をシャッフルして空きに詰める
    var assigned = tc * playerMode;
    if (assigned < sorted.length) {
      var extra = shuffle(sorted.slice(assigned));
      var ei = 0;
      for (var t = 0; t < tc && ei < extra.length; t++) {
        for (var s = 0; s < playerMode && ei < extra.length; s++) {
          if (tbls[t].slots[s].sub) {
            tbls[t].slots[s] = { id: extra[ei].id, name: extra[ei].name, icon: extra[ei].icon };
            ei++;
          }
        }
      }
    }
    return tbls;
  }

  // --- メイン生成 ---
  function generate() {
    var rankMode = document.querySelector('input[name="ranking_mode"]:checked').value;
    var useAvoid = optAvoid.checked && !optAvoid.disabled;

    if (rankMode === 'swiss') {
      tables = generateSwiss();
    } else if (rankMode === 'pot') {
      tables = generatePot();
    } else {
      tables = generateRandom();
    }

    if (useAvoid) {
      applyAvoidance(tables);
    }

    var subCount = 0;
    tables.forEach(function(t) { t.slots.forEach(function(s) { if (s.sub) subCount++; }); });
    var info = tables.length + '卓生成';
    var tags = [];
    if (rankMode === 'swiss') tags.push('スイスドロー');
    if (rankMode === 'pot') tags.push('ポット分け');
    if (useAvoid) tags.push('同卓回避');
    if (tags.length) info += '（' + tags.join('・') + '）';
    if (subCount > 0) info += ' / 代打ち' + subCount + '名必要';
    infoEl.textContent = info;

    render();
    updateData();
    btnSave.disabled = false;
    updateAdvancePreview();
  }

  // --- 描画 ---
  function render() {
    while (area.firstChild) area.removeChild(area.firstChild);
    if (tables.length === 0) { area.appendChild(emptyEl); return; }

    var subNeeded = 0;
    var grid = document.createElement('div');
    grid.className = 'tn-tables';

    for (var ti = 0; ti < tables.length; ti++) {
      var tbl = tables[ti];
      var card = document.createElement('div');
      card.className = 'tn-table';
      var header = document.createElement('div');
      header.className = 'tn-table-name';
      header.textContent = tbl.name;
      card.appendChild(header);

      var slotsDiv = document.createElement('div');
      slotsDiv.className = 'tn-slots';

      for (var si = 0; si < tbl.slots.length; si++) {
        var slot = tbl.slots[si];
        var el = document.createElement('div');
        el.className = 'tn-slot' + (slot.sub ? ' sub' : '');
        el.setAttribute('data-ti', ti);
        el.setAttribute('data-si', si);

        if (slot.sub) {
          subNeeded++;
          el.innerHTML = '<span class="tn-slot-noicon" style="border:1px dashed rgba(var(--gold-rgb),0.5);">?</span><span class="tn-slot-name">代打ち</span>';
        } else {
          el.draggable = true;
          var iconHtml = slot.icon
            ? '<img src="img/chara_deformed/' + esc(slot.icon) + '" class="tn-slot-icon" width="28" height="28" alt="" loading="lazy">'
            : '<span class="tn-slot-noicon">NO<br>IMG</span>';
          el.innerHTML = iconHtml + '<span class="tn-slot-name">' + esc(slot.name) + '</span><span class="tn-slot-grip">&#x2630;</span>';
          el.addEventListener('dragstart', onDragStart);
          el.addEventListener('dragend', onDragEnd);
        }
        el.addEventListener('dragover', onDragOver);
        el.addEventListener('dragenter', onDragEnter);
        el.addEventListener('dragleave', onDragLeave);
        el.addEventListener('drop', onDrop);
        slotsDiv.appendChild(el);
      }
      card.appendChild(slotsDiv);
      grid.appendChild(card);
    }
    area.appendChild(grid);

    if (subNeeded > 0) {
      var notice = document.createElement('div');
      notice.className = 'tn-sub-notice';
      notice.innerHTML = '&#x26A0; ' + subNeeded + '名分の代打ちが必要です。対局前に代打ち選手を手配してください。';
      area.appendChild(notice);
    }
  }

  // --- ドラッグ&ドロップ ---
  function onDragStart(e) {
    dragSrc = { ti: +e.currentTarget.getAttribute('data-ti'), si: +e.currentTarget.getAttribute('data-si') };
    e.currentTarget.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', '');
  }
  function onDragEnd(e) {
    e.currentTarget.classList.remove('dragging'); dragSrc = null;
    var overs = area.querySelectorAll('.drag-over');
    for (var i = 0; i < overs.length; i++) overs[i].classList.remove('drag-over');
  }
  function onDragOver(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; }
  function onDragEnter(e) { e.preventDefault(); if (!e.currentTarget.classList.contains('dragging')) e.currentTarget.classList.add('drag-over'); }
  function onDragLeave(e) { e.currentTarget.classList.remove('drag-over'); }
  function onDrop(e) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-over');
    if (!dragSrc) return;
    var tti = +e.currentTarget.getAttribute('data-ti'), tsi = +e.currentTarget.getAttribute('data-si');
    if (dragSrc.ti === tti && dragSrc.si === tsi) return;
    var tmp = tables[dragSrc.ti].slots[dragSrc.si];
    tables[dragSrc.ti].slots[dragSrc.si] = tables[tti].slots[tsi];
    tables[tti].slots[tsi] = tmp;
    dragSrc = null;
    render(); updateData();
  }

  function updateData() {
    dataEl.innerHTML = '';
    for (var ti = 0; ti < tables.length; ti++) {
      var tbl = tables[ti];
      addHidden('tables[' + ti + '][name]', tbl.name);
      for (var si = 0; si < tbl.slots.length; si++) {
        if (!tbl.slots[si].sub) addHidden('tables[' + ti + '][player_ids][]', tbl.slots[si].id);
      }
    }
  }
  function addHidden(name, value) {
    var inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = name; inp.value = value;
    dataEl.appendChild(inp);
  }
  function esc(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }
})();
JS;

require __DIR__ . '/../templates/footer.php';
?>
