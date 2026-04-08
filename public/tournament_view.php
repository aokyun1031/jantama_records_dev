<?php
declare(strict_types=1);
require __DIR__ . '/../config/database.php';

// --- バリデーション ---
$tournamentId = requireTournamentId();
['data' => $tournamentData] = fetchData(fn() => Tournament::findWithMeta($tournamentId));
if (!$tournamentData || $tournamentData['status'] === TournamentStatus::Preparing->value) {
    abort404();
}
$meta = $tournamentData['meta'];
$tournamentName = $tournamentData['name'];
$isCompleted = $tournamentData['status'] === TournamentStatus::Completed->value;

// --- データ取得 ---
['data' => $finalists] = fetchData(fn() => Standing::finalists($tournamentId));
['data' => $allStandings] = fetchData(fn() => Standing::all($tournamentId));
['data' => $roundsData] = fetchData(fn() => TableInfo::byTournament($tournamentId));
['data' => $interviews] = fetchData(fn() => Interview::byTournament($tournamentId));

$champion = Standing::champion($tournamentId);

$playerMode = (int) ($meta['player_mode'] ?? 4);

// ラウンド数を動的に決定
$roundNumbers = !empty($roundsData) ? array_keys($roundsData) : [];
$totalRounds = count($roundNumbers);
$lastRound = $totalRounds > 0 ? max($roundNumbers) : 0;

// ラウンド設定を取得
$roundSettings = [];
foreach ($roundNumbers as $rn) {
    $rk = 'round_' . $rn;
    $roundSettings[$rn] = [
        'is_final' => ($meta[$rk . '_is_final'] ?? '0') === '1',
        'advance_count' => (int) ($meta[$rk . '_advance_count'] ?? 0),
        'advance_mode' => $meta[$rk . '_advance_mode'] ?? 'per_table',
        'game_count' => (int) ($meta[$rk . '_game_count'] ?? 0),
        'game_type' => $meta[$rk . '_game_type'] ?? '',
    ];
}

// 各ラウンドの参加人数を計算
$roundPlayerCounts = [];
foreach ($roundNumbers as $rn) {
    $playerSet = [];
    foreach ($roundsData[$rn] as $t) {
        foreach ($t['players'] as $p) {
            $playerSet[$p['name']] = true;
        }
    }
    $roundPlayerCounts[$rn] = count($playerSet);
}

// JS用データ構築
$roundScores = [];
$jsRoundTables = [];
$jsRoundAbove = [];
$jsRoundBelow = [];

// standings の eliminated_round をベースに勝ち抜け/敗退を判定
$eliminatedMap = [];
foreach ($allStandings ?? [] as $s) {
    $eliminatedMap[$s['name']] = (int) $s['eliminated_round'];
}

foreach ($roundNumbers as $r) {
    ['data' => $results] = fetchData(fn() => RoundResult::byRound($tournamentId, $r));
    ['data' => $tables] = fetchData(fn() => TableInfo::byRound($tournamentId, $r));

    foreach ($results ?? [] as $res) {
        $roundScores[$res['name']][] = (float) $res['score'];
    }

    $above = [];
    $below = [];
    foreach ($results ?? [] as $res) {
        $displayName = $res['nickname'] ?? $res['name'];
        $icon = $res['character_icon'] ?? '';
        $entry = ['name' => $displayName, 'score' => (float) $res['score'], 'icon' => $icon];
        $elimRound = $eliminatedMap[$res['name']] ?? 0;
        if ($elimRound === $r) {
            $below[] = $entry;
        } else {
            $above[] = $entry;
        }
    }
    $jsRoundAbove[$r] = $above;
    $jsRoundBelow[$r] = $below;

    $jsTables = [];
    foreach ($tables ?? [] as $t) {
        $jsTable = [
            'name' => $t['table_name'],
            'sched' => $t['day_of_week'] ?? '',
            'players' => array_map(fn($p) => ['name' => $p['name'], 'icon' => $p['icon'] ?? ''], $t['players']),
            'done' => (bool) $t['done'],
        ];
        $jsTables[] = $jsTable;
    }
    $jsRoundTables[$r] = $jsTables;
}

$jsStandings = [];
foreach ($allStandings ?? [] as $s) {
    $displayName = $s['nickname'] ?? $s['name'];
    $jsStandings[] = [
        'rank' => (int) $s['rank'],
        'name' => $displayName,
        'icon' => $s['character_icon'] ?? '',
        'total' => (float) $s['total'],
        'r' => $roundScores[$s['name']] ?? [],
        'pending' => (bool) $s['pending'],
        'elim' => (int) $s['eliminated_round'],
    ];
}

// ルールタグ
$ruleTags = buildRuleTags($meta);
$eventType = EventType::tryFrom($meta['event_type'] ?? '');
$eventLabel = $eventType ? $eventType->label() : '';

// --- テンプレート変数 ---
$pageTitle = h($tournamentName) . ' - 最強位戦';
$pageDescription = h($tournamentName) . 'の全対局結果と最終順位を掲載しています。';
$pageOgp = [
    'title' => $tournamentName . ' - 最強位戦',
    'description' => $tournamentName . 'の全対局結果と最終順位を掲載しています。',
    'url' => 'https://jantama-records.onrender.com/tournament_view?id=' . $tournamentId,
];
$pageCss = ['css/finals.css', 'css/mahjong-deco.css', 'css/champion.css'];
$pageScripts = ['js/effects.js'];
$pageStyle = <<<'CSS'
/* 進行中ステップ */
.step-circle.current { background: rgba(var(--mint-rgb), 0.15); color: var(--success); border-color: var(--success); animation: pulse 2s ease infinite; }
@keyframes pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(var(--mint-rgb), 0.3); } 50% { box-shadow: 0 0 0 6px rgba(var(--mint-rgb), 0); } }

/* 未対戦卓の黄色背景を無効化 */
.table-pending { border-color: var(--glass-border); background: var(--card); }
.table-pending .table-card-name { color: var(--table-card-name-color); }
.table-detail-pending { background: rgba(var(--accent-rgb), 0.03); border: 1px solid var(--glass-border); color: var(--text-sub); }

/* 卓カードのプレイヤーリスト */
.table-card-players li::before { content: none; }
.table-card-players li { line-height: 1.4; padding: 4px 0; display: flex; align-items: center; gap: 6px; }
.table-card-players { padding: 0; }

/* 全体順位のアイコン整列 */
.standing-name { display: flex; align-items: center; gap: 2px; flex-wrap: wrap; }
.standing-name img { flex-shrink: 0; }

/* 対戦結果のアイコン整列 */
.result-name { display: flex; align-items: center; gap: 2px; }
.result-name img { flex-shrink: 0; }
.table-detail-player img { flex-shrink: 0; }

/* 勝ち抜けバッジ */
.result-advance-badge { display: inline-block; font-size: 0.6rem; font-weight: 700; color: var(--success); background: rgba(var(--mint-rgb), 0.12); padding: 1px 8px; border-radius: 8px; margin-left: 8px; vertical-align: middle; }

/* 決勝卓のカード改善 */
.finalist-card { text-align: center; }
.finalist-card img { display: block; margin: 0 auto 8px; }

/* セクション間の余白統一 */
.progress-section { margin-bottom: 8px; }

/* 対戦結果タブのスペーシング */
.tab-content { padding-top: 8px; }

/* 卓別結果のスペーシング改善 */
.table-detail-row { padding: 8px 10px; }
.table-detail-block { margin-bottom: 14px; }
CSS;

// --- 全レンダリングをインラインスクリプトに統合（render.js 不使用） ---
$jsStandingsJson = json_encode($jsStandings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$jsAllRoundData = [];
foreach ($roundNumbers as $r) {
    $jsAllRoundData[$r] = [
        'tables' => json_encode($jsRoundTables[$r] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG),
        'above' => json_encode($jsRoundAbove[$r] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG),
        'below' => json_encode($jsRoundBelow[$r] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG),
    ];
}

ob_start();
?>
(function(){
// --- Data ---
var MAX_BAR=130;
var MEDALS=['\u{1F947}','\u{1F948}','\u{1F949}'];
var standings=<?= $jsStandingsJson ?>;
var rounds={};
<?php foreach ($roundNumbers as $r): ?>
rounds[<?= $r ?>]={tables:<?= $jsAllRoundData[$r]['tables'] ?>,above:<?= $jsAllRoundData[$r]['above'] ?>,below:<?= $jsAllRoundData[$r]['below'] ?>};
<?php endforeach; ?>

// --- Helpers (from render.js) ---
function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML}
function cls(s){return s>=0?'plus':'minus'}
function fmt(s){return (s>=0?'+':'')+s.toFixed(1)}
function barW(s){return Math.min(Math.abs(s)/MAX_BAR*100,100)}

function icon(ic,sz){
  sz=sz||24;
  if(!ic){
    var fs=Math.max(0.4,(sz*0.016).toFixed(2));
    return '<span class="chara-icon-none" style="width:'+sz+'px;height:'+sz+'px;font-size:'+fs+'rem">NO<br>IMG</span>';
  }
  return '<img src="img/chara_deformed/'+esc(ic)+'" width="'+sz+'" height="'+sz+'" alt="" style="border-radius:50%;vertical-align:middle" loading="lazy">';
}

function buildScoreMap(above,below){
  var map={};
  for(var i=0;i<above.length;i++) map[above[i].name]={score:above[i].score,icon:above[i].icon};
  for(var i=0;i<below.length;i++) map[below[i].name]={score:below[i].score,icon:below[i].icon};
  return map;
}

function renderTables(tables,opts){
  var html='<div class="table-grid">';
  for(var i=0;i<tables.length;i++){
    var t=tables[i];
    var cardCls='table-card';
    var schedHtml='';
    if(opts&&opts.showDone){
      cardCls+=t.done?' table-done':' table-pending';
      schedHtml=t.done?'\u2713 完了':'未対戦';
    } else if(t.sched){
      schedHtml=t.sched;
    }
    html+='<div class="'+cardCls+'"><div class="table-card-head">'
      +'<span class="table-card-name">'+esc(t.name)+'</span>'
      +(schedHtml?'<span class="table-card-sched">'+schedHtml+'</span>':'')
      +'</div><ul class="table-card-players">';
    for(var j=0;j<t.players.length;j++){
      var p=t.players[j];
      html+='<li>'+icon(p.icon,22)+esc(p.name)+'</li>';
    }
    html+='</ul></div>';
  }
  return html+'</div>';
}

function renderResults(results,startRank){
  var html='';
  for(var i=0;i<results.length;i++){
    var r=results[i];
    html+='<div class="result-row result-advance">'
      +'<div class="result-rank">'+(startRank+i)+'</div>'
      +'<div class="result-name">'+icon(r.icon)+esc(r.name)+'<span class="result-advance-badge">\u2714 勝ち抜け</span></div>'
      +'<div class="result-score '+cls(r.score)+'">'+fmt(r.score)+'</div>'
    +'</div>';
  }
  return html;
}

function renderElim(results,startRank){
  var html='<div class="elim-section"><div class="elim-title">\u25BC 敗退</div>';
  for(var i=0;i<results.length;i++){
    var r=results[i];
    html+='<div class="result-row elim-row">'
      +'<div class="result-rank">'+(startRank+i)+'</div>'
      +'<div class="result-name">'+icon(r.icon)+esc(r.name)+'</div>'
      +'<div class="result-score '+cls(r.score)+'">'+fmt(r.score)+'</div>'
    +'</div>';
  }
  return html+'</div>';
}

function renderTableDetails(tables,scoreMap,opts){
  var html='<div class="table-details">';
  var posLabels=['1st','2nd','3rd','4th'];
  for(var i=0;i<tables.length;i++){
    var t=tables[i];
    var isPending=opts&&opts.showDone&&!t.done;
    html+='<div class="table-detail-block">';
    html+='<div class="table-detail-head">'
      +'<span class="table-detail-name">'+esc(t.name)+' 結果</span>';
    if(isPending){
      html+='<span class="table-detail-badge" style="background:rgba(var(--accent-rgb),0.06);color:var(--text-sub);border:1px solid var(--glass-border)">未対戦</span>';
    }
    html+='</div>';
    if(isPending){
      html+='<div class="table-detail-pending">'+t.players.map(function(p){return esc(p.name)}).join('\u30FB')+' の対戦待ち</div>';
    } else {
      var sorted=t.players.map(function(p){var s=scoreMap[p.name]; return {name:p.name,icon:p.icon,score:s?s.score:0}});
      sorted.sort(function(a,b){return b.score-a.score});
      for(var j=0;j<sorted.length;j++){
        var p=sorted[j];
        var posCls=j===0?'pos-1':'';
        var trophy='';
        html+='<div class="table-detail-row">'
          +'<div class="table-detail-pos '+posCls+'">'+posLabels[j]+'</div>'
          +'<div class="table-detail-player">'+trophy+icon(p.icon)+esc(p.name)+'</div>'
          +'<div class="table-detail-score '+cls(p.score)+'">'+fmt(p.score)+'</div>'
        +'</div>';
      }
    }
    html+='</div>';
  }
  return html+'</div>';
}

window.switchTab=function(idx){
  var btns=document.querySelectorAll('.tab-btn');
  var tabs=document.querySelectorAll('.tab-content');
  for(var i=0;i<btns.length;i++){
    btns[i].classList.toggle('active',i===idx);
    tabs[i].classList.toggle('active',i===idx);
  }
};

// --- Render standings ---
var box=document.getElementById('standings');
if(box){
  var html='';
  var shownDivider=false;
  for(var i=0;i<standings.length;i++){
    var p=standings[i];
    if(p.elim===0 && p.total<0 && !shownDivider){
      shownDivider=true;
      html+='<div class="standing-divider">\u00B1 0</div>';
    }
    var c=cls(p.total);
    var bw=barW(p.total);
    var detail=p.r.map(function(v){return (v>=0?'+':'')+v.toFixed(1)}).join(' \u2192 ');
    if(p.elim>0) detail+=' \u2192 '+p.elim+'回戦敗退';
    var rankHtml=p.rank>0&&p.rank<=3?'<span class="medal">'+MEDALS[p.rank-1]+'</span>':''+(i+1);
    var badgeHtml='';
    if(p.pending) badgeHtml='<span class="badge-pending">未対戦</span>';
    else if(p.elim>0) badgeHtml='<span class="badge-elim">'+p.elim+'回戦敗退</span>';
    var topCls=(i<3&&p.elim===0)?' top-'+(i+1):'';
    var elimCls=p.elim>0?' eliminated':'';
    html+='<div class="standing-item'+topCls+elimCls+'" data-delay="'+(i*0.08)+'" data-bar="'+bw+'">'
      +'<div class="standing-bar '+c+'"></div>'
      +'<div class="standing-rank">'+rankHtml+'</div>'
      +'<div class="standing-info">'
        +'<div class="standing-name">'+icon(p.icon,28)+esc(p.name)+' '+badgeHtml+'</div>'
        +'<div class="standing-detail">'+detail+'</div>'
      +'</div>'
      +'<div class="standing-score '+c+'" data-target="'+p.total+'">'+fmt(p.total)+'</div>'
    +'</div>';
  }
  box.innerHTML=html;
}

// --- Populate tabs ---
var roundKeys=[<?= implode(',', $roundNumbers) ?>];
var finalRounds={<?php
  $finals = [];
  foreach ($roundNumbers as $rn) {
      if (($roundSettings[$rn]['is_final'] ?? false)) $finals[] = $rn . ':true';
  }
  echo implode(',', $finals);
?>};
for(var ri=0;ri<roundKeys.length;ri++){
  var rn=roundKeys[ri];
  var rd=rounds[rn];
  var tabEl=document.getElementById('tab'+ri);
  if(!tabEl||!rd) continue;
  var scoreMap=buildScoreMap(rd.above,rd.below);
  var showDone=<?= $lastRound > 0 ? 'rn>=' . ($lastRound - 1) : 'true' ?>;
  var isFinal=!!finalRounds[rn] || (ri===roundKeys.length-1 && <?= $isCompleted ? 'true' : 'false' ?>);
  if(isFinal){
    // 決勝: 卓別結果のみ表示（全体順位は不要）
    tabEl.innerHTML=
      renderTables(rd.tables,{showDone:true})
      +renderTableDetails(rd.tables,scoreMap,{showDone:true});
  } else {
    tabEl.innerHTML=
      renderTables(rd.tables,showDone?{showDone:true}:null)
      +renderTableDetails(rd.tables,scoreMap,showDone?{showDone:true}:null)
      +'<div class="results-list"><div class="results-sub">全体順位</div>'+renderResults(rd.above,1)+'</div>'
      +(rd.below.length>0?renderElim(rd.below,rd.above.length+1):'');
  }
}
})();
<?php
$pageInlineScript = ob_get_clean();

require __DIR__ . '/../templates/header.php';

// 決勝のファイナリスト
$seeds = ['1ST', '2ND', '3RD', '4TH'];
$finalRound = null;
foreach ($roundSettings as $rn => $rs) {
    if ($rs['is_final']) {
        $finalRound = $rn;
        break;
    }
}
?>

<!-- Floating Tile Scatter (page-level) -->
<div class="tile-scatter" id="tile-scatter"></div>

<!-- Hero -->
<section class="hero">
  <span class="hero-tile-accent top-left">&#x1F004;</span>
  <span class="hero-tile-accent top-right">&#x1F005;</span>
  <span class="hero-tile-accent bot-left">&#x1F000;</span>
  <span class="hero-tile-accent bot-right">&#x1F006;</span>
  <div class="hero-badge"><?= $eventLabel ? h($eventLabel) : '麻雀トーナメント' ?></div>
  <h1 class="hero-title"><?= h($tournamentName) ?></h1>
  <?php if (!$isCompleted): ?>
    <div style="margin-bottom: 8px;"><span style="display:inline-block;font-size:0.75rem;font-weight:700;padding:4px 14px;border-radius:12px;background:rgba(var(--mint-rgb),0.15);color:var(--success);letter-spacing:1px;"><?= h(TournamentStatus::InProgress->label()) ?></span></div>
  <?php endif; ?>
  <div class="hero-rules">
    <?php foreach ($ruleTags as $tag): ?>
      <span><?= h($tag) ?></span>
    <?php endforeach; ?>
  </div>

  <?php if ($isCompleted && $champion): ?>
    <!-- Champion Celebration -->
    <section class="champion-section reveal">
      <div class="champion-container">
        <div class="champion-glow"></div>
        <div class="champion-header">
          <div class="champion-pretitle">&#x1F3C6; CHAMPION &#x1F3C6;</div>
          <h2 class="champion-title">優勝おめでとう！</h2>
          <div class="champion-subtitle"><?= h($tournamentName) ?> 優勝者</div>
        </div>
        <div class="champion-content">
          <div class="champion-avatar">
            <?php if (!empty($champion['character_icon'])): ?>
              <img src="img/chara_deformed/<?= h($champion['character_icon']) ?>" alt="優勝者 <?= h($champion['nickname'] ?? $champion['name']) ?>" class="champion-image" width="200" height="200" loading="lazy">
            <?php endif; ?>
            <div class="champion-crown">&#x1F451;</div>
          </div>
          <div class="champion-info">
            <div class="champion-name"><?= h($champion['nickname'] ?? $champion['name']) ?></div>
            <div class="champion-score">総得点: <?= (float) $champion['total'] >= 0 ? '+' : '' ?><?= h((string) $champion['total']) ?></div>
            <div class="champion-message">見事な戦いぶりで優勝！<br>おめでとうございます！</div>
          </div>
        </div>
        <?php if (!empty($interviews)): ?>
          <div class="champion-interview-link">
            <a href="interview?id=<?= $tournamentId ?>" class="interview-link-btn">&#x1F3A4; 優勝インタビューを読む</a>
          </div>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

</section>

<?php if ($totalRounds > 0): ?>
<!-- Divider -->
<div class="tile-divider">
  <div class="tile-divider-line"></div>
  <div class="tile-divider-tiles">&#x1F000;&#x1F001;&#x1F002;&#x1F003;</div>
  <div class="tile-divider-line"></div>
</div>
<?php endif; ?>

<?php if ($totalRounds === 0 && !$isCompleted): ?>
  <div style="text-align:center;padding:40px 20px;color:var(--text-sub);font-size:0.9rem;">大会はまだ開始されていません。</div>
<?php endif; ?>

<!-- Progress Tracker -->
<?php if ($totalRounds > 0): ?>
<section class="progress-section">
  <div class="progress-track">
    <?php foreach ($roundNumbers as $i => $rn):
      $rSettings = $roundSettings[$rn] ?? [];
      $allDone = !empty($roundsData[$rn]) && empty(array_filter($roundsData[$rn], fn($t) => !$t['done']));
      $prevDone = $i > 0 && !empty($roundsData[$roundNumbers[$i - 1]]) && empty(array_filter($roundsData[$roundNumbers[$i - 1]], fn($t) => !$t['done']));
      $label = $rSettings['is_final'] ? '決勝' : $rn . '回戦';
      $count = $roundPlayerCounts[$rn] ?? 0;
      $isCurrentRound = !$allDone && ($i === 0 || $prevDone);
    ?>
      <?php if ($i > 0): ?><div class="step-line <?= $prevDone ? 'done' : '' ?>"></div><?php endif; ?>
      <div class="progress-step">
        <div class="step-circle <?= $allDone ? 'done' : ($isCurrentRound ? 'current' : '') ?>"><?= $allDone ? '&#10003;' : $rn ?></div>
        <div class="step-label"><?= h($label) ?></div>
        <div class="step-count"><?= $count ?>名</div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($finalRound && !empty($finalists)): ?>
<!-- Finals Showdown -->
<section class="finals-section reveal" id="finals-section">
  <div class="finals-stage">
    <div class="finals-corner tl"></div>
    <div class="finals-corner tr"></div>
    <div class="finals-corner bl"></div>
    <div class="finals-corner br"></div>
    <div class="finals-header">
      <h2 class="finals-title">決勝卓</h2>
      <div class="finals-subtitle">予選を勝ち抜いた<span><?= count($finalists) ?>名</span>が最強位の座を賭けて激突</div>
    </div>
    <div class="finals-grid" id="finals-grid">
      <?php foreach ($finalists as $i => $f): ?>
        <div class="finalist-card" data-delay="<?= 1.2 + $i * 0.3 ?>">
          <?php if (!empty($f['character_icon'])): ?>
            <img src="img/chara_deformed/<?= h($f['character_icon']) ?>" alt="" width="56" height="56" style="border-radius:50%;" loading="lazy">
          <?php endif; ?>
          <div class="finalist-name"><?= h($f['nickname'] ?? $f['name']) ?></div>
          <div class="finalist-score <?= $f['total'] >= 0 ? 'plus' : 'minus' ?>"><?= $f['total'] >= 0 ? '+' : '' ?><?= h((string) $f['total']) ?></div>
          <div class="finalist-trend"><?= h($f['trend']) ?></div>
        </div>
      <?php endforeach; ?>
      <div class="finals-vs">VS</div>
    </div>
  </div>
</section>

<div class="tile-divider">
  <div class="tile-divider-line"></div>
  <div class="tile-divider-tiles">&#x1F010;&#x1F011;&#x1F012;&#x1F013;</div>
  <div class="tile-divider-line"></div>
</div>
<?php endif; ?>

<!-- Round Details -->
<?php if ($totalRounds > 0): ?>
<section class="section section--decorated reveal">
  <div class="section-tiles" id="section-tiles-rounds"></div>
  <div class="section-header">
    <div class="section-title">&#x1F004; 対戦結果</div>
  </div>

  <div class="tabs">
    <?php foreach ($roundNumbers as $i => $rn):
      $rSettings = $roundSettings[$rn] ?? [];
      $label = $rSettings['is_final'] ? '決勝' : $rn . '回戦';
      $tableCount = count($roundsData[$rn] ?? []);
      $pCount = $roundPlayerCounts[$rn] ?? 0;
      $isLast = ($i === $totalRounds - 1);
    ?>
      <button class="tab-btn <?= $isLast ? 'active' : '' ?>" onclick="switchTab(<?= $i ?>)"><?= h($label) ?><br><small><?= $tableCount ?>卓 <?= $pCount ?>名</small></button>
    <?php endforeach; ?>
  </div>

  <?php foreach ($roundNumbers as $i => $rn): ?>
    <div class="tab-content <?= ($i === $totalRounds - 1) ? 'active' : '' ?>" id="tab<?= $i ?>"></div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php
  $hasAnyResults = false;
  foreach ($jsRoundAbove as $above) { if (!empty($above)) { $hasAnyResults = true; break; } }
?>
<?php if ($hasAnyResults): ?>
<div class="tile-divider">
  <div class="tile-divider-line"></div>
  <div class="tile-divider-tiles">&#x1F019;&#x1F01A;&#x1F01B;&#x1F01C;</div>
  <div class="tile-divider-line"></div>
</div>

<!-- Cumulative Standings -->
<section class="section section--decorated reveal" id="standings-section">
  <div class="section-tiles" id="section-tiles-standings"></div>
  <div class="section-header">
    <div class="section-title">&#x1F005; 総合ポイント</div>
  </div>
  <div id="standings"></div>
</section>
<?php endif; ?>

<?php
  $recordScore = $meta['record_score'] ?? '';
  $recordPlayer = $meta['record_player'] ?? '';
  if ($recordScore !== '' || $recordPlayer !== ''):
?>
<div class="tile-divider">
  <div class="tile-divider-line"></div>
  <div class="tile-divider-tiles">&#x1F006;&#x1F004;&#x1F005;&#x1F006;</div>
  <div class="tile-divider-line"></div>
</div>

<!-- Records -->
<section class="records reveal">
  <div class="records-tile-frame" id="records-tile-frame"></div>
  <div class="records-title">&#x1F000; トーナメントレコード &#x1F000;</div>
  <div class="record-highlight">
    <?php if ($recordScore !== ''): ?>
      <span class="record-label">大会最高得点</span>
      <span class="record-score" data-count="<?= h($recordScore) ?>">0</span>
    <?php endif; ?>
    <?php if ($recordPlayer !== ''): ?>
      <span class="record-player"><?= h($recordPlayer) ?></span>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<div style="text-align:center;padding:32px 0 16px;">
  <a href="tournaments" class="btn-cancel">&#x2190; 大会一覧に戻る</a>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
