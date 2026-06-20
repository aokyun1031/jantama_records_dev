<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();
ensureCsrfToken();

$flash = consumeFlash();

// --- バリデーション ---
$tournamentId = requireTournamentId();
$tournament = requireTournamentWithMeta($tournamentId);

$meta = $tournament['meta'];

// --- POST処理（削除） ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flash = validatePost();
    if ($flash) {
        // バリデーションエラー
    } else {
        $action = sanitizeInput('action');
        if ($action === 'delete') {
            if ($tournament['status'] === TournamentStatus::Completed->value) {
                $flash = '完了済みの大会は削除できません。';
            } else {
                Tournament::delete($tournamentId);
                $_SESSION['flash'] = '大会を削除しました。';
                regenerateCsrfToken();
                header('Location: tournaments');
                exit;
            }
        }
    }
}

// --- データ取得 ---
['data' => $rounds] = fetchData(fn() => TableInfo::byTournament($tournamentId));
['data' => $standings] = fetchData(fn() => Standing::all($tournamentId));

// 大会作成直後フラグ（自動DM送信トリガー）
$dmDispatchPending = (($_SESSION['dm_dispatch_pending'] ?? 0) === $tournamentId);
unset($_SESSION['dm_dispatch_pending']);

$isCompleted = $tournament['status'] === TournamentStatus::Completed->value;
$hasTables = !empty($rounds);
$canDelete = !$isCompleted;
$playerCount = count($standings ?? []);

// --- 進行状態の判定 ---
// phase: 'no_players' | 'current_round' | 'round_complete'
$currentRound = 0;
$currentRoundTables = [];
$currentRoundDone = 0;
$currentRoundTotal = 0;
$pastRounds = [];

if (!empty($rounds)) {
    $roundNumbers = array_keys($rounds);
    $currentRound = max($roundNumbers);
    $currentRoundTables = $rounds[$currentRound];
    $currentRoundTotal = count($currentRoundTables);
    $currentRoundDone = count(array_filter($currentRoundTables, fn($t) => $t['done']));

    // 過去ラウンド（現在ラウンド以外）
    foreach ($rounds as $rn => $tables) {
        if ($rn < $currentRound) {
            $pastRounds[$rn] = $tables;
        }
    }
}

$allCurrentDone = $currentRoundTotal > 0 && $currentRoundDone === $currentRoundTotal;
$nextRound = $currentRound + 1;

// 決勝完了判定
$isFinalDone = false;
$champion = null;
if ($currentRound > 0) {
    $rk = 'round_' . $currentRound;
    $isFinalRound = ($meta[$rk . '_is_final'] ?? '0') === '1';
    if ($isFinalRound && $allCurrentDone) {
        $isFinalDone = true;
        ['data' => $champion] = fetchData(fn() => Standing::champion($tournamentId));
        $championId = $champion ? (int) $champion['player_id'] : null;
    }
}

if ($playerCount === 0) {
    $phase = 'no_players';
} elseif ($isFinalDone) {
    $phase = 'tournament_complete';
} elseif ($allCurrentDone || $currentRoundTotal === 0) {
    $phase = 'round_complete';
} else {
    $phase = 'current_round';
}

// ルール要約
$ruleTags = buildRuleTags($meta);

// --- テンプレート変数 ---
$pageTitle = $tournament['name'] . ' - ' . SITE_NAME;
$pageDescription = $tournament['name'] . 'の大会詳細ページです。';
$pageCss = ['css/forms.css', 'css/tournament.css'];
$pageTurnstile = true;
require __DIR__ . '/../templates/header.php';

// 日付表示ヘルパー: "2026/4/11（土）21:00"
function formatTableDate(array $t): string {
    $date = date('Y/n/j', strtotime($t['played_date']));
    $dow = $t['day_of_week'] ? mb_substr($t['day_of_week'], 0, 1) : '';
    $time = $t['played_time'] ?? '';
    return $date . ($dow !== '' ? '（' . $dow . '）' : '') . ($time !== '' ? ' ' . $time : '');
}

// ステータス表示ヘルパー
$tsEnum = TournamentStatus::tryFrom($tournament['status']);
$statusLabel = $tsEnum?->label() ?? $tournament['status'];
$statusClass = $tsEnum?->cssClass() ?? '';
?>

<div class="td-hero">
  <div class="td-badge">TOURNAMENT</div>
  <div class="td-title-row">
    <h1 class="td-title"><?= h($tournament['name']) ?></h1>
    <a href="tournament_edit?id=<?= $tournamentId ?>" class="td-edit-link" title="大会情報を編集">
      <svg class="td-edit-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
    </a>
  </div>
  <div class="td-rules">
    <?php foreach ($ruleTags as $tag): ?>
      <span class="td-rule-tag"><?= h($tag) ?></span>
    <?php endforeach; ?>
    <span class="td-status <?= $statusClass ?>"><?= h($statusLabel) ?></span>
  </div>
</div>

<div class="td-content" data-csrf-token="<?= h($_SESSION['csrf_token']) ?>" data-tournament-id="<?= $tournamentId ?>"<?= $dmDispatchPending ? ' data-dm-dispatch-pending="1"' : '' ?>>
  <?php if ($flash): ?>
    <div class="edit-message success"><?= h($flash) ?></div>
  <?php endif; ?>

  <?php if ($phase === 'tournament_complete' && !$isCompleted): ?>
    <a href="tournament_view?id=<?= $tournamentId ?>" class="td-sub-link">&#x1F441; 閲覧ページ</a>
    <div class="td-cta" style="border-color: rgba(var(--gold-rgb), 0.4);">
      <span class="td-cta-icon gold"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
      <div class="td-cta-body">
        <div class="td-cta-title">決勝が完了しました</div>
        <div class="td-cta-desc">優勝者インタビューを設定して、大会を完了しましょう。</div>
      </div>
      <a href="interview_edit?id=<?= $tournamentId ?>" class="td-cta-btn" style="background: var(--gold); box-shadow: 0 3px 12px rgba(var(--gold-rgb), 0.3);">インタビュー設定</a>
    </div>
  <?php elseif (!$isCompleted): ?>
    <?php if ($phase === 'no_players'): ?>
      <div class="td-cta">
        <span class="td-cta-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg></span>
        <div class="td-cta-body">
          <div class="td-cta-title">まず選手を登録しましょう</div>
          <div class="td-cta-desc">大会に参加する選手を登録してください。</div>
        </div>
        <a href="tournament_players?id=<?= $tournamentId ?>" class="td-cta-btn">選手登録</a>
      </div>

    <?php elseif ($phase === 'round_complete' && $currentRound === 0): ?>
      <a href="tournament_players?id=<?= $tournamentId ?>" class="td-sub-link">&#x1F465; 選手登録（<?= $playerCount ?>名）</a>
      <div class="td-cta">
        <span class="td-cta-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></span>
        <div class="td-cta-body">
          <div class="td-cta-title">1回戦の卓を作成しましょう</div>
          <div class="td-cta-desc"><?= $playerCount ?>名の選手が登録されています。<br>卓を作成して大会を開始しましょう。</div>
        </div>
        <a href="table_new?tournament_id=<?= $tournamentId ?>" class="td-cta-btn secondary">卓を作成</a>
      </div>

    <?php elseif ($phase === 'round_complete'): ?>
      <a href="tournament_players?id=<?= $tournamentId ?>" class="td-sub-link">&#x1F465; 選手登録（<?= $playerCount ?>名）</a>
      <a href="tournament_view?id=<?= $tournamentId ?>" class="td-sub-link">&#x1F441; 閲覧ページ</a>
      <div class="td-cta">
        <span class="td-cta-icon green"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
        <div class="td-cta-body">
          <div class="td-cta-title"><?= $currentRound ?>回戦が完了しました</div>
          <div class="td-cta-desc"><?= $nextRound ?>回戦の卓を作成して次のラウンドへ進みましょう。</div>
        </div>
        <a href="table_new?tournament_id=<?= $tournamentId ?>" class="td-cta-btn secondary">卓を作成</a>
      </div>

    <?php elseif ($phase === 'current_round'): ?>
      <a href="tournament_players?id=<?= $tournamentId ?>" class="td-sub-link">&#x1F465; 選手登録（<?= $playerCount ?>名）</a>
      <a href="tournament_view?id=<?= $tournamentId ?>" class="td-sub-link">&#x1F441; 閲覧ページ</a>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($isCompleted): ?>
    <a href="tournament_view?id=<?= $tournamentId ?>" class="td-sub-link">&#x1F441; 閲覧ページ</a>
    <div class="td-cta" style="border-color: rgba(var(--gold-rgb), 0.3); background: linear-gradient(135deg, rgba(var(--gold-rgb), 0.06), rgba(var(--accent-rgb), 0.02));">
      <span class="td-cta-icon gold"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
      <div class="td-cta-body">
        <div class="td-cta-title">大会は終了しました</div>
      </div>
      <a href="interview_edit?id=<?= $tournamentId ?>" class="td-cta-btn" style="background: var(--gold); box-shadow: 0 3px 12px rgba(var(--gold-rgb), 0.3);">インタビュー編集</a>
    </div>
  <?php endif; ?>

  <!-- === 現在のラウンド === -->
  <?php if ($currentRoundTotal > 0): ?>
    <?php
      $rk = 'round_' . $currentRound;
      $rIsFinal = ($meta[$rk . '_is_final'] ?? '0') === '1';
      $rAdvance = (int) ($meta[$rk . '_advance_count'] ?? 0);
      $rAdvanceMode = $meta[$rk . '_advance_mode'] ?? 'per_table';
      $rGameCount = (int) ($meta[$rk . '_game_count'] ?? 0);
      $rGameType = $meta[$rk . '_game_type'] ?? '';
      $gameTypeLabel = (RoundType::tryFrom($rGameType ?? ''))?->label() ?? '';
    ?>
    <div class="td-round">
      <div class="td-round-header">
        <?= $currentRound ?>回戦<?= $rIsFinal ? '（決勝）' : '' ?>
        <?php if ($allCurrentDone): ?>
          <span class="td-round-progress complete">全卓完了</span>
        <?php else: ?>
          <span class="td-round-progress in-progress"><?= $currentRoundDone ?>/<?= $currentRoundTotal ?>卓 完了</span>
        <?php endif; ?>
      </div>
      <?php if ($rGameCount > 0 || $rAdvance > 0): ?>
        <div class="td-round-meta">
          <?php if ($gameTypeLabel): ?>
            <span class="td-round-tag"><?= h($gameTypeLabel) ?><?= $rGameCount > 0 ? $rGameCount . '回' : '' ?></span>
          <?php endif; ?>
          <?php if ($rIsFinal): ?>
            <span class="td-round-tag">総合ポイント1位が優勝</span>
          <?php elseif ($rAdvance > 0): ?>
            <span class="td-round-tag"><?= $rAdvanceMode === 'overall' ? '全体' : '各卓' ?>上位<?= $rAdvance ?>名勝ち抜け</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="td-round-grid">
        <?php foreach ($currentRoundTables as $t): ?>
          <?php
            $tableStatus = $t['done'] ? 'done' : ($t['played_date'] ? 'scheduled' : 'waiting');
            $tableStatusLabel = match ($tableStatus) { 'done' => '完了', 'scheduled' => '日程確定', 'waiting' => '待機中' };
          ?>
          <a href="table?id=<?= (int) $t['table_id'] ?>" class="td-table-card table-card <?= $tableStatus === 'done' ? 'table-done' : ($tableStatus === 'waiting' ? 'table-pending' : '') ?>">
            <div class="table-card-head">
              <span class="table-card-name"><?= h($t['table_name']) ?></span>
              <span class="td-table-status <?= $tableStatus ?>"><?= $tableStatusLabel ?></span>
            </div>
            <?php if ($t['played_date']): ?>
              <div class="td-table-date"><?= h(formatTableDate($t)) ?></div>
            <?php endif; ?>
            <ul class="td-table-players">
              <?php foreach ($t['players'] as $p):
                $pClass = '';
                if ($t['done'] && $p['score'] !== null) {
                  if ($rIsFinal && !empty($championId)) {
                    $pClass = ($p['player_id'] === $championId) ? ' advanced' : ' eliminated';
                  } else {
                    $pClass = ($p['eliminated_round'] === $currentRound) ? ' eliminated' : ' advanced';
                  }
                }
              ?>
                <li class="td-table-player<?= $pClass ?>">
                  <?= charaIcon($p['icon'], 28) ?>
                  <span class="td-table-player-name"><?= h($p['name']) ?></span>
                  <?php if ($t['done'] && $p['score'] !== null): ?>
                    <span class="td-table-player-score <?= (float) $p['score'] >= 0 ? 'score-plus' : 'score-minus' ?>">
                      <?= (float) $p['score'] >= 0 ? '+' : '' ?><?= h((string) $p['score']) ?>
                    </span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
              <?php $subCount = max(0, (int) ($meta['player_mode'] ?? 4) - count($t['players'])); ?>
              <?php for ($s = 0; $s < $subCount; $s++): ?>
                <li class="td-table-player sub">
                  <span class="td-table-sub-icon">?</span>
                  <span class="td-table-player-name">代打ち</span>
                </li>
              <?php endfor; ?>
            </ul>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- === 過去ラウンド === -->
  <?php if (!empty($pastRounds)): ?>
        <?php foreach (array_reverse($pastRounds, true) as $roundNumber => $tables): ?>
          <?php
            $doneCount = count(array_filter($tables, fn($t) => $t['done']));
            $prk = 'round_' . $roundNumber;
            $prIsFinal = ($meta[$prk . '_is_final'] ?? '0') === '1';
            $prAdvance = (int) ($meta[$prk . '_advance_count'] ?? 0);
            $prAdvanceMode = $meta[$prk . '_advance_mode'] ?? 'per_table';
            $prGameCount = (int) ($meta[$prk . '_game_count'] ?? 0);
            $prGameType = $meta[$prk . '_game_type'] ?? '';
            $prGameTypeLabel = (RoundType::tryFrom($prGameType))?->label() ?? '';
          ?>
          <div class="td-round">
            <div class="td-round-header">
              <?= $roundNumber ?>回戦<?= $prIsFinal ? '（決勝）' : '' ?>
              <span class="td-round-progress complete"><?= $doneCount ?>/<?= count($tables) ?>卓 完了</span>
            </div>
            <?php if ($prGameCount > 0 || $prAdvance > 0): ?>
              <div class="td-round-meta">
                <?php if ($prGameTypeLabel): ?>
                  <span class="td-round-tag"><?= h($prGameTypeLabel) ?><?= $prGameCount > 0 ? $prGameCount . '回' : '' ?></span>
                <?php endif; ?>
                <?php if ($prIsFinal): ?>
                  <span class="td-round-tag">総合ポイント1位が優勝</span>
                <?php elseif ($prAdvance > 0): ?>
                  <span class="td-round-tag"><?= $prAdvanceMode === 'overall' ? '全体' : '各卓' ?>上位<?= $prAdvance ?>名勝ち抜け</span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <div class="td-round-grid">
              <?php foreach ($tables as $t): ?>
                <?php
                  $tableStatus = $t['done'] ? 'done' : ($t['played_date'] ? 'scheduled' : 'waiting');
                  $tableStatusLabel = match ($tableStatus) { 'done' => '完了', 'scheduled' => '日程確定', 'waiting' => '待機中' };
                ?>
                <a href="table?id=<?= (int) $t['table_id'] ?>" class="td-table-card table-card <?= $tableStatus === 'done' ? 'table-done' : ($tableStatus === 'waiting' ? 'table-pending' : '') ?>">
                  <div class="table-card-head">
                    <span class="table-card-name"><?= h($t['table_name']) ?></span>
                    <span class="td-table-status <?= $tableStatus ?>"><?= $tableStatusLabel ?></span>
                  </div>
                  <ul class="td-table-players">
                    <?php foreach ($t['players'] as $p):
                      $pClass = '';
                      if ($t['done'] && $p['score'] !== null) {
                        $pClass = ($p['eliminated_round'] === $roundNumber) ? ' eliminated' : ' advanced';
                      }
                    ?>
                      <li class="td-table-player<?= $pClass ?>">
                        <?= charaIcon($p['icon'], 28) ?>
                        <span class="td-table-player-name"><?= h($p['name']) ?></span>
                        <?php if ($t['done'] && $p['score'] !== null): ?>
                          <span class="td-table-player-score <?= (float) $p['score'] >= 0 ? 'score-plus' : 'score-minus' ?>">
                            <?= (float) $p['score'] >= 0 ? '+' : '' ?><?= h((string) $p['score']) ?>
                          </span>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                    <?php $subCount = max(0, (int) ($meta['player_mode'] ?? 4) - count($t['players'])); ?>
                    <?php for ($s = 0; $s < $subCount; $s++): ?>
                      <li class="td-table-player sub">
                        <span class="td-table-sub-icon">?</span>
                        <span class="td-table-player-name">代打ち</span>
                      </li>
                    <?php endfor; ?>
                  </ul>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
  <?php endif; ?>

  <!-- 登録選手一覧 -->
  <?php if (!empty($standings)): ?>
    <div class="td-standings">
      <div class="td-standings-title">登録選手一覧（<?= count($standings) ?>名）</div>
      <table class="td-standings-table">
        <thead><tr><th></th><th>選手</th><th>総合ポイント</th></tr></thead>
        <tbody>
          <?php if ($isCompleted): ?>
            <?php foreach ($standings as $s): ?>
              <tr>
                <td><?= charaIcon($s['character_icon'], 28) ?></td>
                <td><?= h($s['nickname'] ?? $s['name']) ?></td>
                <td class="<?= (float) $s['total'] >= 0 ? 'score-plus' : 'score-minus' ?>">
                  <?= (float) $s['total'] >= 0 ? '+' : '' ?><?= h((string) $s['total']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <?php
              $activeList = array_filter($standings, fn($s) => (int) $s['eliminated_round'] === 0);
              $eliminatedList = array_filter($standings, fn($s) => (int) $s['eliminated_round'] > 0);
            ?>
            <?php foreach ($activeList as $s): ?>
              <tr>
                <td><?= charaIcon($s['character_icon'], 28) ?></td>
                <td><?= h($s['nickname'] ?? $s['name']) ?></td>
                <td class="<?= (float) $s['total'] >= 0 ? 'score-plus' : 'score-minus' ?>">
                  <?= (float) $s['total'] >= 0 ? '+' : '' ?><?= h((string) $s['total']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!empty($eliminatedList)): ?>
              <tr class="td-standings-divider"><td colspan="3">敗退</td></tr>
              <?php foreach ($eliminatedList as $s): ?>
                <tr class="td-standings-eliminated">
                  <td><?= charaIcon($s['character_icon'], 24) ?></td>
                  <td><?= h($s['nickname'] ?? $s['name']) ?></td>
                  <td class="<?= (float) $s['total'] >= 0 ? 'score-plus' : 'score-minus' ?>">
                    <?= (float) $s['total'] >= 0 ? '+' : '' ?><?= h((string) $s['total']) ?>
                    <span class="td-elim-round"><?= (int) $s['eliminated_round'] ?>回戦敗退</span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="td-actions">
    <a href="tournaments" class="btn-cancel">&#x2190; 大会一覧に戻る</a>
  </div>

  <?php if ($canDelete): ?>
    <div class="td-delete">
      <!-- <div class="td-delete-title">大会の削除</div> -->
      <div class="td-delete-desc">この大会と関連する全データ（卓・成績・インタビュー）を削除します。<br>この操作は取り消せません。</div>
      <form method="post" action="tournament?id=<?= $tournamentId ?>" data-confirm="本当にこの大会を削除しますか？&#10;関連する全データも削除されます。">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="td-btn-delete">大会を削除</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php
$pageScripts = ['js/dispatch-dm.js'];

require __DIR__ . '/../templates/footer.php';
?>
