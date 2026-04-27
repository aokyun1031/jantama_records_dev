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
['data' => $activePlayerIds] = fetchData(fn() => Standing::activePlayerIds($tournamentId));
$activePlayerIds = $activePlayerIds ?? [];

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
['data' => $jsStandings] = fetchData(fn() => Standing::totalMap($tournamentId));
$jsStandings = $jsStandings ?? [];

// POST処理
$validationError = '';
$postRoundNumber = (string) $nextRound;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validationError = validatePost();
    if ($validationError) {
        // バリデーションエラー
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
                $name = preg_replace('/[\x00-\x1F\x7F]/u', '', trim($t['name'] ?? ''));
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
                // 決勝ラウンドは残り選手が1卓に収まる場合に自動判定
                $isFinal = count($tournamentPlayers) <= $playerMode;
                $advanceCount = (int) (sanitizeInput('advance_count') ?: 2);
                $advanceMode = sanitizeInput('advance_mode');
                if (!in_array($advanceMode, ['per_table', 'overall'], true)) {
                    $advanceMode = 'per_table';
                }
                $gameCount = (int) (sanitizeInput('game_count') ?: 2);
                $gameType = sanitizeInput('game_type');
                $validGameTypes = ['hanchan', 'tonpu', 'ikkyoku'];
                if (!in_array($gameType, $validGameTypes, true)) {
                    $gameType = $meta['round_type'] ?? 'hanchan';
                }
                if ($advanceCount < 1) $advanceCount = 1;
                if ($gameCount < 1) $gameCount = 1;

                if ($advanceMode === 'overall' && $advanceCount % $playerMode !== 0) {
                    $validationError = '全体モードの勝ち抜け人数は対局人数の倍数で指定してください。';
                }

                if (!$validationError) {
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
$pageTitle = '卓作成 - ' . $tournament['name'] . ' - ' . SITE_NAME;
$pageDescription = $tournament['name'] . ' の新しい卓を作成します。';
$pageCss = ['css/forms.css', 'css/table_new.css'];
$pageScripts = ['js/table_new.js'];

$pageTurnstile = true;
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
          <div class="tn-option-group">
            <span class="tn-option-label">決勝</span>
            <span class="tn-final-badge">決勝ラウンド（残り<?= count($tournamentPlayers) ?>名）</span>
          </div>
        <?php else: ?>
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
      </div>
      <div class="tn-hint">ボタンを押すと選手をランダムに卓へ振り分けます。何度でも押し直せます。</div>

      <div class="tn-options">
        <div class="tn-option-group">
          <span class="tn-option-label">同卓回避</span>
          <label class="tn-toggle" id="toggle-avoid">
            <input type="checkbox" id="opt-avoid" <?= $prevRound < 1 ? 'disabled' : '' ?>>
            <span class="tn-toggle-track"></span>
          </label>
        </div>
        <?php $rankingDisabled = $nextRound <= 1; ?>
        <div class="tn-option-group">
          <span class="tn-option-label">成績考慮</span>
          <div class="tn-radio-group">
            <input type="radio" name="ranking_mode" value="none" id="rank-none" class="tn-radio" checked <?= $rankingDisabled ? 'disabled' : '' ?>>
            <label for="rank-none" class="tn-radio-label">なし</label>
            <input type="radio" name="ranking_mode" value="swiss" id="rank-swiss" class="tn-radio" <?= $rankingDisabled ? 'disabled' : '' ?>>
            <label for="rank-swiss" class="tn-radio-label">スイスドロー</label>
            <input type="radio" name="ranking_mode" value="pot" id="rank-pot" class="tn-radio" <?= $rankingDisabled ? 'disabled' : '' ?>>
            <label for="rank-pot" class="tn-radio-label">ポット分け</label>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- 生成情報 -->
    <div class="tn-generate-summary" id="generate-summary" style="display:none;">
      <div class="tn-generate-info" id="generate-info"></div>
      <div id="advance-preview" class="tn-advance-preview"></div>
    </div>

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
window.__tableNewData = {
  players: {$jsPlayersJson},
  playerMode: {$playerMode},
  nextRound: {$nextRound},
  prevGroups: {$jsPrevGroupsJson},
  standings: {$jsStandingsJson}
};
JS;

require __DIR__ . '/../templates/footer.php';
?>
