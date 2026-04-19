<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';

$isTopPage = true;

// --- データ取得 ---
['data' => $tournaments] = fetchData(fn() => Tournament::allWithDetails());
['data' => $stats] = fetchData(fn() => HallOfFame::siteStats());
['data' => $championCounts] = fetchData(fn() => HallOfFame::championCounts(3));
['data' => $pointLeaders] = fetchData(fn() => HallOfFame::totalPointLeaders(10));
['data' => $highestScore] = fetchData(fn() => HallOfFame::highestRoundScoreAllTime());
['data' => $mostTops] = fetchData(fn() => HallOfFame::mostTopFinishesAllTime());
['data' => $latestByEvent] = fetchData(fn() => HallOfFame::latestByEventType());
['data' => $latestInterview] = fetchData(fn() => HallOfFame::latestInterview());

$tournaments = $tournaments ?? [];
$stats = $stats ?? [
    'total_tournaments' => 0, 'completed_tournaments' => 0, 'total_players' => 0,
    'total_tables' => 0, 'done_tables' => 0, 'total_rounds' => 0,
];
$championCounts = $championCounts ?? [];
$pointLeaders = $pointLeaders ?? [];
$latestByEvent = $latestByEvent ?? [];

$completedTournaments = array_values(array_filter($tournaments, fn($t) => $t['status'] === TournamentStatus::Completed->value));
$activeTournaments = array_values(array_filter($tournaments, fn($t) => $t['status'] === TournamentStatus::InProgress->value));
$preparingTournaments = array_values(array_filter($tournaments, fn($t) => $t['status'] === TournamentStatus::Preparing->value));

$latestCompleted = $completedTournaments[0] ?? null;
$latestChampion = $latestCompleted ? Standing::champion((int) $latestCompleted['id']) : null;

// 進行中大会の詳細
$liveCards = [];
foreach ($activeTournaments as $t) {
    $tid = (int) $t['id'];
    ['data' => $standings] = fetchData(fn() => Standing::all($tid));
    ['data' => $currentRound] = fetchData(fn() => HallOfFame::currentRound($tid));
    $alive = array_values(array_filter($standings ?? [], fn($s) => (int) $s['eliminated_round'] === 0));
    $liveCards[] = [
        'tournament' => $t,
        'current_round' => $currentRound,
        'top3' => array_slice($alive, 0, 3),
        'alive_count' => count($alive),
    ];
}

// イベント種別ごとの開催数
$eventCounts = [];
foreach ($tournaments as $t) {
    $et = $t['event_type'] ?? '';
    if ($et === '') continue;
    $eventCounts[$et] = ($eventCounts[$et] ?? 0) + 1;
}

// --- OGP 動的化 ---
$ogpDesc = '開催大会 ' . (int) $stats['total_tournaments'] . ' ・ 登録選手 ' . (int) $stats['total_players'] . '名 ・ 総半荘数 ' . (int) $stats['total_rounds'];
if ($latestChampion && $latestCompleted) {
    $champName = $latestChampion['nickname'] ?? $latestChampion['name'];
    $ogpDesc = '最新王者「' . $champName . '」- ' . $latestCompleted['name'] . ' / ' . $ogpDesc;
}

$pageTitle = SITE_NAME . ' - 麻雀トーナメント戦績ダッシュボード';
$pageDescription = $ogpDesc;
$pageOgp = [
    'title' => SITE_NAME . ' - 麻雀トーナメント戦績ダッシュボード',
    'description' => $ogpDesc,
    'url' => 'https://jantama-records.onrender.com/',
];

$pageStyle = <<<'CSS'
/* Landing v2 — ARCADE BOARD concept */
/* 既存の .main を全幅化 */
.main { max-width: 100%; padding: 0; margin: 0; }

.lp2 {
  --lp-panel: rgba(255,255,255,0.035);
  --lp-panel-strong: rgba(255,255,255,0.06);
  --lp-ring: rgba(var(--gold-rgb),0.22);
  --lp-ring-hot: rgba(var(--gold-rgb),0.5);
  --lp-live-rgb: 232,80,112;
  --lp-grid-line: rgba(var(--gold-rgb),0.08);
  --lp-mono: 'Inter','Noto Sans JP',sans-serif;
  --lp-jp: 'Noto Sans JP',sans-serif;
  --lp-heading-letter: 0.08em;
  font-feature-settings: 'tnum','ss01';
}
.lp2 a { text-decoration: none; color: inherit; }
.lp2-wrap { max-width: 1280px; margin: 0 auto; padding: 64px 20px 32px; }
.lp2-section + .lp2-section { margin-top: 56px; }

/* Eyebrow labels */
.lp2-eyebrow {
  display: inline-flex; align-items: center; gap: 10px;
  font-family: var(--lp-mono);
  font-size: 0.7rem; font-weight: 800;
  letter-spacing: 0.28em; text-transform: uppercase;
  color: var(--gold);
}
.lp2-eyebrow::before {
  content: ''; width: 22px; height: 2px; background: var(--gold);
  box-shadow: 0 0 12px rgba(var(--gold-rgb),0.7);
}
.lp2-heading {
  font-family: var(--lp-jp);
  font-size: clamp(1.3rem, 3.2vw, 1.8rem);
  font-weight: 900; color: var(--text);
  letter-spacing: var(--lp-heading-letter);
  margin: 8px 0 28px;
}

/* ==========================
   HERO THEATER
   ========================== */
.lp2-theater {
  position: relative;
  padding: 48px 32px;
  background:
    radial-gradient(ellipse at top right, rgba(var(--gold-rgb),0.14), transparent 60%),
    radial-gradient(ellipse at bottom left, rgba(var(--accent-rgb),0.10), transparent 55%),
    var(--lp-panel);
  border: 1px solid var(--lp-ring);
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 24px 60px rgba(0,0,0,0.25);
}
.lp2-theater::before {
  content: '';
  position: absolute; inset: 0;
  background-image:
    linear-gradient(var(--lp-grid-line) 1px, transparent 1px),
    linear-gradient(90deg, var(--lp-grid-line) 1px, transparent 1px);
  background-size: 56px 56px;
  opacity: 0.5;
  pointer-events: none;
  mask-image: radial-gradient(ellipse at center, black 40%, transparent 85%);
}
.lp2-theater-inner {
  position: relative; z-index: 1;
  display: grid; grid-template-columns: minmax(180px, 260px) 1fr;
  gap: 40px; align-items: center;
}
.lp2-hero-avatar {
  position: relative;
  width: 100%; aspect-ratio: 1;
  display: flex; align-items: center; justify-content: center;
}
.lp2-hero-avatar::before {
  content: '';
  position: absolute; inset: 0;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(var(--gold-rgb),0.35) 0%, transparent 70%);
  filter: blur(20px);
  animation: lp2Pulse 4s ease infinite;
}
.lp2-hero-avatar img, .lp2-hero-avatar .lp2-fallback {
  position: relative;
  width: 86%; height: 86%;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid var(--gold);
  box-shadow: 0 0 40px rgba(var(--gold-rgb),0.35), inset 0 0 20px rgba(0,0,0,0.3);
}
.lp2-fallback {
  display: flex; align-items: center; justify-content: center;
  font-family: var(--lp-mono); font-weight: 900; font-size: 3rem;
  color: var(--gold); background: var(--lp-panel);
}
.lp2-hero-badge {
  display: inline-block;
  font-family: var(--lp-mono);
  font-size: 0.68rem; font-weight: 800;
  letter-spacing: 0.3em; text-transform: uppercase;
  color: var(--gold);
  padding: 6px 14px;
  border: 1px solid var(--gold);
  border-radius: 2px;
  margin-bottom: 14px;
}
.lp2-hero-name {
  font-family: var(--lp-jp);
  font-size: clamp(2rem, 5vw, 3.2rem);
  font-weight: 900; line-height: 1.1;
  color: var(--text);
  text-shadow: 0 2px 24px rgba(var(--gold-rgb),0.2);
  margin-bottom: 14px;
}
.lp2-hero-meta {
  display: flex; flex-wrap: wrap; gap: 10px;
  margin-bottom: 18px;
}
.lp2-chip {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 0.78rem; font-weight: 700;
  color: var(--text-sub);
  padding: 5px 12px;
  border: 1px solid var(--lp-ring);
  border-radius: 2px;
  background: var(--lp-panel);
}
.lp2-chip.is-gold { color: var(--gold); border-color: var(--lp-ring-hot); }
.lp2-hero-score {
  display: inline-flex; align-items: baseline; gap: 6px;
  font-family: var(--lp-mono);
  color: var(--gold);
  margin-bottom: 22px;
}
.lp2-hero-score-num {
  font-size: clamp(2.6rem, 6vw, 4rem);
  font-weight: 900; line-height: 1;
  text-shadow: 0 0 30px rgba(var(--gold-rgb),0.5);
}
.lp2-hero-score-unit { font-size: 1rem; font-weight: 800; letter-spacing: 0.1em; }
.lp2-hero-actions { display: flex; gap: 12px; flex-wrap: wrap; }

/* Brand fallback (no completed tournaments yet) */
.lp2-hero-brand .lp2-hero-name {
  background: linear-gradient(135deg, #ffd700, #ffec80, #d4a84c);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* ==========================
   COMMAND BAR
   ========================== */
.lp2-commandbar {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 1px;
  background: var(--lp-ring);
  border: 1px solid var(--lp-ring);
  border-radius: 14px;
  overflow: hidden;
}
.lp2-cb-cell {
  padding: 18px 14px;
  background: var(--lp-panel);
  display: flex; flex-direction: column; gap: 4px;
  transition: background 0.2s;
}
.lp2-cb-cell:hover { background: var(--lp-panel-strong); }
.lp2-cb-label {
  font-family: var(--lp-mono);
  font-size: 0.62rem; font-weight: 700;
  letter-spacing: 0.2em; text-transform: uppercase;
  color: var(--text-sub);
}
.lp2-cb-value {
  font-family: var(--lp-mono);
  font-size: 1.6rem; font-weight: 900;
  color: var(--gold);
  line-height: 1;
  font-variant-numeric: tabular-nums;
}

/* ==========================
   LIVE BAND
   ========================== */
.lp2-live-grid {
  display: grid; gap: 16px;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
}
.lp2-live-card {
  position: relative;
  padding: 22px 24px;
  background:
    linear-gradient(135deg, rgba(var(--lp-live-rgb),0.08), transparent 50%),
    var(--lp-panel);
  border: 1px solid rgba(var(--lp-live-rgb),0.3);
  border-radius: 14px;
  transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
  display: block;
}
.lp2-live-card:hover {
  transform: translateY(-3px);
  border-color: rgba(var(--lp-live-rgb),0.55);
  box-shadow: 0 16px 40px rgba(var(--lp-live-rgb),0.12);
}
.lp2-live-pulse {
  display: inline-flex; align-items: center; gap: 8px;
  font-family: var(--lp-mono);
  font-size: 0.7rem; font-weight: 900;
  letter-spacing: 0.25em;
  color: rgb(var(--lp-live-rgb));
  margin-bottom: 12px;
}
.lp2-live-pulse::before {
  content: ''; width: 8px; height: 8px; border-radius: 50%;
  background: rgb(var(--lp-live-rgb));
  box-shadow: 0 0 12px rgba(var(--lp-live-rgb),0.9);
  animation: lp2Blink 1.2s ease infinite;
}
.lp2-live-name {
  font-family: var(--lp-jp);
  font-size: 1.15rem; font-weight: 900;
  color: var(--text);
  margin-bottom: 6px;
  line-height: 1.35;
}
.lp2-live-chips {
  display: flex; flex-wrap: wrap; gap: 6px;
  margin-bottom: 14px;
}
.lp2-live-progress {
  display: flex; justify-content: space-between; align-items: baseline;
  padding: 10px 14px;
  background: var(--lp-panel-strong);
  border-radius: 8px;
  margin-bottom: 14px;
  font-family: var(--lp-mono);
}
.lp2-live-progress-round {
  color: var(--gold); font-weight: 900; font-size: 1.6rem; line-height: 1;
}
.lp2-live-progress-sub {
  font-size: 0.7rem; font-weight: 700; letter-spacing: 0.14em;
  color: var(--text-sub); text-transform: uppercase;
}
.lp2-live-alive {
  font-size: 0.8rem; color: var(--text-sub);
}
.lp2-live-alive strong { color: var(--text); font-weight: 800; }
.lp2-live-top {
  margin-top: 14px; padding-top: 14px;
  border-top: 1px dashed rgba(var(--gold-rgb),0.18);
  display: flex; flex-direction: column; gap: 8px;
}
.lp2-live-top-row {
  display: grid; grid-template-columns: 22px 32px 1fr auto; align-items: center; gap: 10px;
  font-size: 0.9rem;
}
.lp2-live-top-rank {
  font-family: var(--lp-mono); font-weight: 900; color: var(--text-sub);
  font-size: 0.85rem;
}
.lp2-live-top-row:first-child .lp2-live-top-rank { color: var(--gold); }
.lp2-live-top-icon { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
.lp2-live-top-name { color: var(--text); font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lp2-live-top-pt { font-family: var(--lp-mono); font-weight: 900; color: var(--gold); font-variant-numeric: tabular-nums; }

.lp2-live-empty {
  padding: 40px 24px;
  text-align: center;
  background: var(--lp-panel);
  border: 1px dashed var(--lp-ring);
  border-radius: 14px;
  color: var(--text-sub);
}
.lp2-live-empty strong {
  display: block; color: var(--text); font-size: 1.1rem; margin-bottom: 8px;
}

/* ==========================
   VAULT (Hall of Fame bento)
   ========================== */
.lp2-vault {
  display: grid; gap: 16px;
  grid-template-columns: repeat(12, 1fr);
}
.lp2-vault-cell {
  background: var(--lp-panel);
  border: 1px solid var(--lp-ring);
  border-radius: 14px;
  padding: 20px 22px;
  position: relative;
  overflow: hidden;
  transition: border-color 0.2s, transform 0.2s;
}
.lp2-vault-cell:hover { border-color: var(--lp-ring-hot); transform: translateY(-2px); }
.lp2-vault-cell.is-wide { grid-column: span 6; }
.lp2-vault-cell.is-half { grid-column: span 6; }
.lp2-vault-cell.is-third { grid-column: span 4; }
.lp2-vault-cell.is-full { grid-column: span 12; }
.lp2-vault-label {
  font-family: var(--lp-mono);
  font-size: 0.65rem; font-weight: 800;
  letter-spacing: 0.25em; text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 6px;
}
.lp2-vault-title {
  font-family: var(--lp-jp);
  font-weight: 900; font-size: 1rem;
  color: var(--text);
  margin-bottom: 16px;
}

/* Champion leaderboard (wide) */
.lp2-champion-list { display: flex; flex-direction: column; gap: 10px; }
.lp2-champion-row {
  display: grid; grid-template-columns: 40px 48px 1fr auto; align-items: center;
  gap: 14px;
  padding: 10px 14px;
  background: var(--lp-panel-strong);
  border-radius: 10px;
  transition: transform 0.15s;
}
.lp2-champion-row:hover { transform: translateX(4px); }
.lp2-champion-row.rank-1 { background: linear-gradient(135deg, rgba(var(--gold-rgb),0.2), rgba(var(--gold-rgb),0.05)); border: 1px solid var(--lp-ring-hot); }
.lp2-champion-row.rank-2 { background: linear-gradient(135deg, rgba(192,192,210,0.12), rgba(192,192,210,0.03)); }
.lp2-champion-row.rank-3 { background: linear-gradient(135deg, rgba(180,130,80,0.12), rgba(180,130,80,0.03)); }
.lp2-rank {
  font-family: var(--lp-mono); font-weight: 900;
  font-size: 1.3rem; line-height: 1; color: var(--text-sub);
  text-align: center;
}
.lp2-champion-row.rank-1 .lp2-rank { color: var(--gold); font-size: 1.6rem; }
.lp2-avatar { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; }
.lp2-avatar-placeholder {
  width: 48px; height: 48px; border-radius: 50%;
  background: var(--lp-panel-strong);
  display: flex; align-items: center; justify-content: center;
  color: var(--text-light); font-size: 0.55rem; font-weight: 900; text-align: center;
}
.lp2-champion-name { font-weight: 800; color: var(--text); font-size: 1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lp2-champion-count {
  font-family: var(--lp-mono); font-weight: 900;
  color: var(--gold); font-size: 1.3rem;
  font-variant-numeric: tabular-nums;
}
.lp2-champion-count small { font-size: 0.7rem; color: var(--text-sub); font-weight: 700; margin-left: 4px; }

/* Record highlight cards */
.lp2-record {
  display: flex; align-items: center; gap: 14px;
}
.lp2-record .lp2-avatar { width: 56px; height: 56px; }
.lp2-record-num {
  font-family: var(--lp-mono);
  font-size: 2.4rem; font-weight: 900;
  color: var(--gold); line-height: 1;
  font-variant-numeric: tabular-nums;
  text-shadow: 0 0 24px rgba(var(--gold-rgb),0.35);
}
.lp2-record-num small { font-size: 0.75rem; color: var(--text-sub); font-weight: 700; letter-spacing: 0.15em; margin-left: 4px; }
.lp2-record-info { min-width: 0; }
.lp2-record-name { font-weight: 900; color: var(--text); font-size: 1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lp2-record-sub { font-size: 0.75rem; color: var(--text-sub); margin-top: 2px; }

.lp2-vault-empty { text-align: center; color: var(--text-light); font-size: 0.85rem; padding: 20px 0; }

/* ==========================
   SERIES HANGAR
   ========================== */
.lp2-series {
  display: grid; gap: 14px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}
.lp2-series-tile {
  position: relative;
  padding: 22px 20px;
  background: var(--lp-panel);
  border: 1px solid var(--lp-ring);
  border-radius: 12px;
  overflow: hidden;
  transition: transform 0.2s, border-color 0.2s;
}
.lp2-series-tile:hover { transform: translateY(-3px); border-color: var(--lp-ring-hot); }
.lp2-series-tile::before {
  content: attr(data-code);
  position: absolute; top: 10px; right: 14px;
  font-family: var(--lp-mono);
  font-size: 2.8rem; font-weight: 900;
  color: var(--gold);
  opacity: 0.12;
  letter-spacing: -0.05em;
  pointer-events: none;
}
.lp2-series-count {
  font-family: var(--lp-mono);
  font-size: 0.7rem; font-weight: 800;
  letter-spacing: 0.2em; color: var(--gold);
}
.lp2-series-label {
  font-family: var(--lp-jp);
  font-size: 1.2rem; font-weight: 900;
  color: var(--text);
  margin: 6px 0 14px;
}
.lp2-series-latest-title {
  font-size: 0.65rem; letter-spacing: 0.18em; color: var(--text-sub);
  text-transform: uppercase; font-weight: 700;
  margin-bottom: 4px;
}
.lp2-series-latest-name {
  font-size: 0.92rem; color: var(--text); font-weight: 700;
  overflow: hidden; text-overflow: ellipsis; display: -webkit-box;
  -webkit-line-clamp: 2; -webkit-box-orient: vertical;
  margin-bottom: 8px;
}
.lp2-series-winner {
  font-size: 0.78rem; color: var(--gold); font-weight: 800;
}
.lp2-series-tile.is-empty {
  border-style: dashed;
  opacity: 0.5;
}
.lp2-series-empty-note { color: var(--text-light); font-size: 0.8rem; }

/* ==========================
   ROSTER (leaderboard)
   ========================== */
.lp2-roster {
  background: var(--lp-panel);
  border: 1px solid var(--lp-ring);
  border-radius: 14px;
  overflow: hidden;
}
.lp2-roster-head {
  display: grid; grid-template-columns: 50px 56px 1fr 90px 80px;
  gap: 12px; align-items: center;
  padding: 14px 22px;
  font-family: var(--lp-mono);
  font-size: 0.62rem; font-weight: 800;
  letter-spacing: 0.2em; text-transform: uppercase;
  color: var(--text-sub);
  border-bottom: 1px solid var(--lp-ring);
}
.lp2-roster-row {
  display: grid; grid-template-columns: 50px 56px 1fr 90px 80px;
  gap: 12px; align-items: center;
  padding: 12px 22px;
  transition: background 0.15s;
  border-bottom: 1px solid rgba(var(--gold-rgb),0.05);
}
.lp2-roster-row:last-child { border-bottom: none; }
.lp2-roster-row:hover { background: var(--lp-panel-strong); }
.lp2-roster-rank {
  font-family: var(--lp-mono); font-weight: 900; font-size: 1.1rem;
  color: var(--text-sub);
  font-variant-numeric: tabular-nums;
}
.lp2-roster-row.is-top .lp2-roster-rank { color: var(--gold); font-size: 1.3rem; }
.lp2-roster-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
.lp2-roster-name { font-weight: 700; color: var(--text); font-size: 0.95rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lp2-roster-pt {
  font-family: var(--lp-mono); font-weight: 900; color: var(--gold);
  text-align: right;
  font-variant-numeric: tabular-nums;
}
.lp2-roster-pt.is-neg { color: var(--minus-text); }
.lp2-roster-games {
  font-family: var(--lp-mono); font-size: 0.8rem;
  text-align: right; color: var(--text-sub);
  font-variant-numeric: tabular-nums;
}

/* ==========================
   ARCHIVE
   ========================== */
.lp2-archive-grid {
  display: grid; gap: 10px;
}
.lp2-archive-row {
  display: grid;
  grid-template-columns: 80px 1fr 110px 100px 80px;
  gap: 14px; align-items: center;
  padding: 12px 18px;
  background: var(--lp-panel);
  border: 1px solid var(--lp-ring);
  border-radius: 10px;
  transition: transform 0.15s, border-color 0.15s;
}
.lp2-archive-row:hover { transform: translateX(4px); border-color: var(--lp-ring-hot); }
.lp2-archive-date {
  font-family: var(--lp-mono); font-size: 0.78rem;
  color: var(--text-sub); font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.lp2-archive-name {
  font-weight: 700; color: var(--text); font-size: 0.95rem;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.lp2-archive-event {
  font-family: var(--lp-mono); font-size: 0.7rem;
  letter-spacing: 0.1em; color: var(--gold);
  text-align: center;
  padding: 3px 8px;
  border: 1px solid var(--lp-ring);
  border-radius: 2px;
  white-space: nowrap;
}
.lp2-archive-winner {
  font-size: 0.82rem; color: var(--text-sub);
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.lp2-archive-winner strong { color: var(--gold); font-weight: 800; }
.lp2-archive-status {
  font-size: 0.62rem; font-weight: 800;
  padding: 3px 8px; border-radius: 10px;
  text-align: center; white-space: nowrap;
  letter-spacing: 0.1em;
}
.lp2-archive-status.preparing { background: rgba(var(--gold-rgb),0.15); color: var(--gold); }
.lp2-archive-status.active { background: rgba(var(--lp-live-rgb),0.15); color: rgb(var(--lp-live-rgb)); }
.lp2-archive-status.completed { background: rgba(255,255,255,0.05); color: var(--text-sub); }

/* ==========================
   SPOTLIGHT (Interview)
   ========================== */
.lp2-spotlight {
  position: relative;
  padding: 40px 36px;
  background:
    radial-gradient(ellipse at top left, rgba(var(--accent-rgb),0.12), transparent 60%),
    var(--lp-panel);
  border: 1px solid var(--lp-ring);
  border-radius: 16px;
  overflow: hidden;
}
.lp2-spotlight::before {
  content: '“';
  position: absolute; top: -30px; right: 20px;
  font-family: 'Inter',serif;
  font-size: 14rem; font-weight: 900;
  color: var(--gold);
  opacity: 0.12;
  line-height: 1;
  pointer-events: none;
}
.lp2-spotlight-grid {
  display: grid; grid-template-columns: 1fr auto; gap: 24px; align-items: center;
  position: relative;
}
.lp2-spotlight-name {
  font-family: var(--lp-jp);
  font-size: clamp(1.3rem, 3vw, 1.8rem);
  font-weight: 900; color: var(--text);
  margin: 8px 0;
}
.lp2-spotlight-meta {
  font-size: 0.85rem; color: var(--text-sub);
}
.lp2-spotlight-meta strong { color: var(--gold); font-family: var(--lp-mono); font-weight: 900; }

/* ==========================
   OUTRO
   ========================== */
.lp2-outro {
  text-align: center;
  padding: 48px 20px 8px;
}
.lp2-outro-title {
  font-family: var(--lp-jp);
  font-size: 1.3rem; font-weight: 900;
  color: var(--text);
  letter-spacing: 0.04em;
  margin-bottom: 24px;
}

/* ==========================
   BUTTONS
   ========================== */
.lp2-btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 12px 22px;
  font-family: var(--lp-jp);
  font-size: 0.85rem; font-weight: 800;
  letter-spacing: 0.06em;
  border-radius: 4px;
  transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
  cursor: pointer;
}
.lp2-btn:hover { transform: translateY(-2px); }
.lp2-btn-primary {
  background: linear-gradient(135deg, var(--gold), #c49a3c);
  color: #1a1430;
  box-shadow: 0 6px 20px rgba(var(--gold-rgb),0.3);
}
.lp2-btn-primary:hover { box-shadow: 0 10px 30px rgba(var(--gold-rgb),0.45); }
.lp2-btn-ghost {
  background: transparent;
  color: var(--text);
  border: 1px solid var(--lp-ring);
}
.lp2-btn-ghost:hover { border-color: var(--lp-ring-hot); background: var(--lp-panel); }
.lp2-btn-small { padding: 8px 14px; font-size: 0.78rem; }
.lp2-btn-arrow::after { content: '→'; transition: transform 0.2s; }
.lp2-btn-arrow:hover::after { transform: translateX(4px); }

/* ==========================
   ANIMATIONS
   ========================== */
@keyframes lp2Pulse { 0%,100% { transform: scale(1); opacity: 0.7; } 50% { transform: scale(1.08); opacity: 1; } }
@keyframes lp2Blink { 0%,100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.4; transform: scale(0.85); } }

/* ==========================
   RESPONSIVE
   ========================== */
@media (max-width: 960px) {
  .lp2-theater { padding: 32px 20px; }
  .lp2-theater-inner { grid-template-columns: 1fr; gap: 24px; text-align: center; }
  .lp2-hero-avatar { max-width: 200px; margin: 0 auto; }
  .lp2-hero-meta { justify-content: center; }
  .lp2-hero-actions { justify-content: center; }
  .lp2-commandbar { grid-template-columns: repeat(3, 1fr); }
  .lp2-vault-cell.is-wide, .lp2-vault-cell.is-half, .lp2-vault-cell.is-third { grid-column: span 12; }
  .lp2-roster-head, .lp2-roster-row { grid-template-columns: 40px 44px 1fr 80px 60px; padding: 10px 14px; gap: 8px; }
  .lp2-archive-row { grid-template-columns: 70px 1fr 70px; padding: 10px 12px; }
  .lp2-archive-event, .lp2-archive-winner { display: none; }
  .lp2-spotlight-grid { grid-template-columns: 1fr; text-align: left; }
}
@media (max-width: 560px) {
  .lp2-wrap { padding: 48px 12px 32px; }
  .lp2-commandbar { grid-template-columns: repeat(2, 1fr); }
  .lp2-cb-value { font-size: 1.3rem; }
}

/* ライトテーマ時のローカル上書き */
html:not([data-theme="dark"]) body .lp2 {
  --lp-panel: rgba(255,255,255,0.72);
  --lp-panel-strong: rgba(255,255,255,0.92);
  --lp-ring: rgba(var(--gold-rgb),0.25);
  --lp-ring-hot: rgba(var(--gold-rgb),0.55);
  --lp-grid-line: rgba(var(--gold-rgb),0.12);
}
CSS;

require __DIR__ . '/../templates/header.php';
?>

<div class="lp2">
<div class="lp2-wrap">

<!-- ======================== -->
<!-- HERO THEATER -->
<!-- ======================== -->
<section class="lp2-section lp2-theater">
  <div class="lp2-theater-inner">
    <?php if ($latestChampion && $latestCompleted): ?>
      <div class="lp2-hero-avatar">
        <?php if (!empty($latestChampion['character_icon'])): ?>
          <img src="img/chara_deformed/<?= h($latestChampion['character_icon']) ?>" alt="" width="260" height="260" loading="eager">
        <?php else: ?>
          <span class="lp2-fallback">NO<br>IMG</span>
        <?php endif; ?>
      </div>
      <div class="lp2-hero-body">
        <span class="lp2-hero-badge">Reigning Champion</span>
        <?php
          $champEvent = EventType::tryFrom($latestCompleted['event_type'] ?? '');
          $champName = $latestChampion['nickname'] ?? $latestChampion['name'];
        ?>
        <h1 class="lp2-hero-name"><?= h($champName) ?></h1>
        <div class="lp2-hero-meta">
          <?php if ($champEvent): ?>
            <span class="lp2-chip is-gold"><?= h($champEvent->label()) ?></span>
          <?php endif; ?>
          <span class="lp2-chip"><?= h($latestCompleted['name']) ?></span>
          <span class="lp2-chip"><?= (int) $latestCompleted['player_count'] ?>名参加</span>
        </div>
        <div class="lp2-hero-score">
          <span class="lp2-hero-score-num"><?= number_format((float) $latestChampion['total'], 1) ?></span>
          <span class="lp2-hero-score-unit">PT</span>
        </div>
        <div class="lp2-hero-actions">
          <a href="tournament_view?id=<?= (int) $latestCompleted['id'] ?>" class="lp2-btn lp2-btn-primary lp2-btn-arrow">大会結果を見る</a>
          <a href="player?id=<?= (int) $latestChampion['player_id'] ?>" class="lp2-btn lp2-btn-ghost">選手プロフィール</a>
        </div>
      </div>
    <?php else: ?>
      <div class="lp2-hero-avatar">
        <img src="img/logo.png" alt="" width="260" height="260" loading="eager" style="border-radius:50%;padding:20px;">
      </div>
      <div class="lp2-hero-body lp2-hero-brand">
        <span class="lp2-hero-badge">Tournament Archive</span>
        <h1 class="lp2-hero-name"><?= h(SITE_NAME) ?></h1>
        <p style="color:var(--text-sub);font-size:0.95rem;line-height:1.8;margin-bottom:20px;">
          雀魂で開催する麻雀トーナメントの<br>
          対局結果・戦績・選手情報を一望できるダッシュボード。
        </p>
        <div class="lp2-hero-actions">
          <a href="tournaments" class="lp2-btn lp2-btn-primary lp2-btn-arrow">大会一覧を見る</a>
          <a href="players" class="lp2-btn lp2-btn-ghost">選手一覧を見る</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ======================== -->
<!-- COMMAND BAR -->
<!-- ======================== -->
<section class="lp2-section lp2-commandbar">
  <div class="lp2-cb-cell">
    <span class="lp2-cb-label">Tournaments</span>
    <span class="lp2-cb-value"><?= (int) $stats['total_tournaments'] ?></span>
  </div>
  <div class="lp2-cb-cell">
    <span class="lp2-cb-label">Completed</span>
    <span class="lp2-cb-value"><?= (int) $stats['completed_tournaments'] ?></span>
  </div>
  <div class="lp2-cb-cell">
    <span class="lp2-cb-label">Players</span>
    <span class="lp2-cb-value"><?= (int) $stats['total_players'] ?></span>
  </div>
  <div class="lp2-cb-cell">
    <span class="lp2-cb-label">Tables</span>
    <span class="lp2-cb-value"><?= (int) $stats['done_tables'] ?></span>
  </div>
  <div class="lp2-cb-cell">
    <span class="lp2-cb-label">Hanchan</span>
    <span class="lp2-cb-value"><?= (int) $stats['total_rounds'] ?></span>
  </div>
  <div class="lp2-cb-cell">
    <span class="lp2-cb-label">Live</span>
    <span class="lp2-cb-value"><?= count($activeTournaments) ?></span>
  </div>
</section>

<!-- ======================== -->
<!-- LIVE BAND -->
<!-- ======================== -->
<section class="lp2-section">
  <span class="lp2-eyebrow">Live Now</span>
  <h2 class="lp2-heading">開催中の大会</h2>
  <?php if (!empty($liveCards)): ?>
    <div class="lp2-live-grid">
      <?php foreach ($liveCards as $lc):
        $lt = $lc['tournament'];
        $lEvent = EventType::tryFrom($lt['event_type'] ?? '');
      ?>
        <a href="tournament_view?id=<?= (int) $lt['id'] ?>" class="lp2-live-card">
          <span class="lp2-live-pulse">LIVE NOW</span>
          <div class="lp2-live-name"><?= h($lt['name']) ?></div>
          <div class="lp2-live-chips">
            <?php if ($lEvent): ?>
              <span class="lp2-chip is-gold"><?= h($lEvent->label()) ?></span>
            <?php endif; ?>
            <span class="lp2-chip"><?= (int) $lt['player_count'] ?>名参加</span>
          </div>
          <?php if ($lc['current_round']): ?>
            <div class="lp2-live-progress">
              <div>
                <div class="lp2-live-progress-sub">Current Round</div>
                <div class="lp2-live-progress-round">第 <?= (int) $lc['current_round'] ?> 回戦</div>
              </div>
              <div class="lp2-live-alive">
                勝ち残り <strong><?= (int) $lc['alive_count'] ?></strong> 名
              </div>
            </div>
          <?php endif; ?>
          <?php if (!empty($lc['top3'])): ?>
            <div class="lp2-live-top">
              <?php foreach ($lc['top3'] as $i => $s): ?>
                <div class="lp2-live-top-row">
                  <span class="lp2-live-top-rank">#<?= $i + 1 ?></span>
                  <?php if (!empty($s['character_icon'])): ?>
                    <img src="img/chara_deformed/<?= h($s['character_icon']) ?>" alt="" class="lp2-live-top-icon" width="32" height="32" loading="lazy">
                  <?php else: ?>
                    <span class="lp2-avatar-placeholder" style="width:32px;height:32px;">NO</span>
                  <?php endif; ?>
                  <span class="lp2-live-top-name"><?= h($s['nickname'] ?? $s['name']) ?></span>
                  <span class="lp2-live-top-pt"><?= ((float) $s['total'] >= 0 ? '+' : '') . number_format((float) $s['total'], 1) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php elseif (!empty($preparingTournaments)): ?>
    <?php $pt = $preparingTournaments[0]; $pEvent = EventType::tryFrom($pt['event_type'] ?? ''); ?>
    <div class="lp2-live-empty">
      <strong>次回大会 準備中</strong>
      <?php if ($pEvent): ?><span class="lp2-chip is-gold" style="margin-right:6px;"><?= h($pEvent->label()) ?></span><?php endif; ?>
      <a href="tournament?id=<?= (int) $pt['id'] ?>" style="color:var(--gold);font-weight:800;"><?= h($pt['name']) ?> →</a>
    </div>
  <?php else: ?>
    <div class="lp2-live-empty">
      <strong>現在開催中の大会はありません</strong>
      <a href="tournaments" class="lp2-btn lp2-btn-ghost lp2-btn-small lp2-btn-arrow" style="margin-top:12px;">大会一覧へ</a>
    </div>
  <?php endif; ?>
</section>

<!-- ======================== -->
<!-- VAULT (Hall of Fame) -->
<!-- ======================== -->
<section class="lp2-section">
  <span class="lp2-eyebrow">Hall of Fame</span>
  <h2 class="lp2-heading">殿堂 - 横断レコード</h2>
  <div class="lp2-vault">

    <!-- 通算優勝数 Top3 -->
    <div class="lp2-vault-cell is-wide">
      <div class="lp2-vault-label">Career Wins</div>
      <div class="lp2-vault-title">通算優勝数 Top3</div>
      <?php if (!empty($championCounts)): ?>
        <div class="lp2-champion-list">
          <?php foreach ($championCounts as $i => $ch): $rank = $i + 1; ?>
            <a href="player?id=<?= (int) $ch['player_id'] ?>" class="lp2-champion-row rank-<?= $rank ?>">
              <span class="lp2-rank">#<?= $rank ?></span>
              <?php if (!empty($ch['character_icon'])): ?>
                <img src="img/chara_deformed/<?= h($ch['character_icon']) ?>" alt="" class="lp2-avatar" width="48" height="48" loading="lazy">
              <?php else: ?>
                <span class="lp2-avatar-placeholder">NO<br>IMG</span>
              <?php endif; ?>
              <span class="lp2-champion-name"><?= h($ch['player_name']) ?></span>
              <span class="lp2-champion-count"><?= (int) $ch['win_count'] ?><small>WIN<?= ((int) $ch['win_count']) > 1 ? 'S' : '' ?></small></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="lp2-vault-empty">完了大会がまだありません</div>
      <?php endif; ?>
    </div>

    <!-- 歴代最高スコア -->
    <div class="lp2-vault-cell is-half">
      <div class="lp2-vault-label">Highest Score</div>
      <div class="lp2-vault-title">歴代最高ラウンド得点</div>
      <?php if ($highestScore): ?>
        <a href="player?id=<?= (int) $highestScore['player_id'] ?>" class="lp2-record">
          <?php if (!empty($highestScore['character_icon'])): ?>
            <img src="img/chara_deformed/<?= h($highestScore['character_icon']) ?>" alt="" class="lp2-avatar" width="56" height="56" loading="lazy">
          <?php else: ?>
            <span class="lp2-avatar-placeholder" style="width:56px;height:56px;">NO<br>IMG</span>
          <?php endif; ?>
          <div class="lp2-record-info">
            <span class="lp2-record-num"><?= ((float) $highestScore['score'] >= 0 ? '+' : '') . number_format((float) $highestScore['score'], 1) ?><small>PT</small></span>
            <div class="lp2-record-name"><?= h($highestScore['player_name']) ?></div>
            <div class="lp2-record-sub"><?= h($highestScore['tournament_name']) ?> ・ 第<?= (int) $highestScore['round_number'] ?>回戦</div>
          </div>
        </a>
      <?php else: ?>
        <div class="lp2-vault-empty">記録なし</div>
      <?php endif; ?>
    </div>

    <!-- 歴代最多トップ -->
    <div class="lp2-vault-cell is-half">
      <div class="lp2-vault-label">Most Table Tops</div>
      <div class="lp2-vault-title">歴代最多卓1位</div>
      <?php if ($mostTops && !empty($mostTops['winners'])):
        $tw = $mostTops['winners'][0];
      ?>
        <a href="player?id=<?= (int) $tw['player_id'] ?>" class="lp2-record">
          <?php if (!empty($tw['character_icon'])): ?>
            <img src="img/chara_deformed/<?= h($tw['character_icon']) ?>" alt="" class="lp2-avatar" width="56" height="56" loading="lazy">
          <?php else: ?>
            <span class="lp2-avatar-placeholder" style="width:56px;height:56px;">NO<br>IMG</span>
          <?php endif; ?>
          <div class="lp2-record-info">
            <span class="lp2-record-num"><?= (int) $mostTops['top_count'] ?><small>TOPS</small></span>
            <div class="lp2-record-name"><?= h($tw['player_name']) ?><?= count($mostTops['winners']) > 1 ? ' 他' . (count($mostTops['winners']) - 1) . '名' : '' ?></div>
            <div class="lp2-record-sub">全大会横断</div>
          </div>
        </a>
      <?php else: ?>
        <div class="lp2-vault-empty">記録なし</div>
      <?php endif; ?>
    </div>

  </div>
</section>

<!-- ======================== -->
<!-- SERIES HANGAR -->
<!-- ======================== -->
<section class="lp2-section">
  <span class="lp2-eyebrow">Series</span>
  <h2 class="lp2-heading">大会シリーズ</h2>
  <div class="lp2-series">
    <?php
    $seriesOrder = [
      EventType::Saikyoi->value => 'S',
      EventType::Hooh->value => 'H',
      EventType::Masters->value => 'M',
      EventType::Hyakudanisen->value => '100',
      EventType::Petit->value => 'P',
    ];
    foreach ($seriesOrder as $etv => $code):
      $eventEnum = EventType::tryFrom($etv);
      if (!$eventEnum) continue;
      $latest = $latestByEvent[$etv] ?? null;
      $count = $eventCounts[$etv] ?? 0;
      $tsLatest = $latest ? TournamentStatus::tryFrom($latest['status']) : null;
      $viewable = $latest && $latest['status'] !== TournamentStatus::Preparing->value;
      $href = $latest ? ($viewable ? "tournament_view?id=" . (int) $latest['id'] : "tournament?id=" . (int) $latest['id']) : 'tournaments';
    ?>
      <a href="<?= $href ?>" class="lp2-series-tile<?= $count === 0 ? ' is-empty' : '' ?>" data-code="<?= h((string) $code) ?>">
        <span class="lp2-series-count">開催 <?= (int) $count ?> 回</span>
        <div class="lp2-series-label"><?= h($eventEnum->label()) ?></div>
        <?php if ($latest): ?>
          <div class="lp2-series-latest-title">Latest</div>
          <div class="lp2-series-latest-name"><?= h($latest['name']) ?></div>
          <?php if (!empty($latest['winner_name']) && $latest['status'] === TournamentStatus::Completed->value): ?>
            <div class="lp2-series-winner">👑 <?= h($latest['winner_name']) ?></div>
          <?php elseif ($tsLatest): ?>
            <div class="lp2-series-winner" style="color:var(--text-sub);">
              <?= h($tsLatest->label()) ?>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="lp2-series-empty-note">開催予定なし</div>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- ======================== -->
<!-- ROSTER (Player leaderboard) -->
<!-- ======================== -->
<?php if (!empty($pointLeaders)): ?>
<section class="lp2-section">
  <span class="lp2-eyebrow">Roster</span>
  <h2 class="lp2-heading">選手リーダーボード（累計pt）</h2>
  <div class="lp2-roster">
    <div class="lp2-roster-head">
      <span>Rank</span>
      <span></span>
      <span>Player</span>
      <span style="text-align:right;">Total PT</span>
      <span style="text-align:right;">Events</span>
    </div>
    <?php foreach ($pointLeaders as $i => $pl):
      $rank = $i + 1;
      $totalPt = (float) $pl['total_pt'];
    ?>
      <a href="player?id=<?= (int) $pl['player_id'] ?>" class="lp2-roster-row<?= $rank <= 3 ? ' is-top' : '' ?>">
        <span class="lp2-roster-rank">#<?= $rank ?></span>
        <?php if (!empty($pl['character_icon'])): ?>
          <img src="img/chara_deformed/<?= h($pl['character_icon']) ?>" alt="" class="lp2-roster-avatar" width="40" height="40" loading="lazy">
        <?php else: ?>
          <span class="lp2-avatar-placeholder" style="width:40px;height:40px;font-size:0.5rem;">NO<br>IMG</span>
        <?php endif; ?>
        <span class="lp2-roster-name"><?= h($pl['player_name']) ?></span>
        <span class="lp2-roster-pt<?= $totalPt < 0 ? ' is-neg' : '' ?>"><?= ($totalPt >= 0 ? '+' : '') . number_format($totalPt, 1) ?></span>
        <span class="lp2-roster-games"><?= (int) $pl['tournament_count'] ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <div style="text-align:right;margin-top:14px;">
    <a href="players" class="lp2-btn lp2-btn-ghost lp2-btn-small lp2-btn-arrow">選手一覧を見る</a>
  </div>
</section>
<?php endif; ?>

<!-- ======================== -->
<!-- ARCHIVE -->
<!-- ======================== -->
<?php if (!empty($tournaments)): ?>
<section class="lp2-section">
  <span class="lp2-eyebrow">Archive</span>
  <h2 class="lp2-heading">大会アーカイブ</h2>
  <div class="lp2-archive-grid">
    <?php foreach (array_slice($tournaments, 0, 8) as $t):
      $tsEnum = TournamentStatus::tryFrom($t['status']);
      $etEnum = EventType::tryFrom($t['event_type'] ?? '');
      $isViewable = $t['status'] !== TournamentStatus::Preparing->value;
      $href = $isViewable ? "tournament_view?id=" . (int) $t['id'] : "tournament?id=" . (int) $t['id'];
      $dateStr = !empty($t['start_date']) ? date('Y.m.d', strtotime($t['start_date'])) : '';
    ?>
      <a href="<?= $href ?>" class="lp2-archive-row">
        <span class="lp2-archive-date"><?= h($dateStr) ?></span>
        <span class="lp2-archive-name"><?= h($t['name']) ?></span>
        <span class="lp2-archive-event"><?= h($etEnum?->label() ?? '-') ?></span>
        <span class="lp2-archive-winner">
          <?php if ($t['status'] === TournamentStatus::Completed->value && !empty($t['winner_name'])): ?>
            👑 <strong><?= h($t['winner_name']) ?></strong>
          <?php else: ?>
            <?= (int) $t['player_count'] ?>名参加
          <?php endif; ?>
        </span>
        <span class="lp2-archive-status <?= $tsEnum?->cssClass() ?? '' ?>"><?= h($tsEnum?->label() ?? '') ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if (count($tournaments) > 8): ?>
    <div style="text-align:right;margin-top:14px;">
      <a href="tournaments" class="lp2-btn lp2-btn-ghost lp2-btn-small lp2-btn-arrow">すべての大会</a>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<!-- ======================== -->
<!-- SPOTLIGHT (Interview) -->
<!-- ======================== -->
<?php if ($latestInterview): ?>
<section class="lp2-section lp2-spotlight">
  <div class="lp2-spotlight-grid">
    <div>
      <span class="lp2-eyebrow">Champion Interview</span>
      <div class="lp2-spotlight-name"><?= h($latestInterview['tournament_name']) ?></div>
      <div class="lp2-spotlight-meta">
        優勝者インタビュー <strong><?= (int) $latestInterview['qa_count'] ?></strong> 問
      </div>
    </div>
    <a href="interview?id=<?= (int) $latestInterview['tournament_id'] ?>" class="lp2-btn lp2-btn-primary lp2-btn-arrow">インタビューを読む</a>
  </div>
</section>
<?php endif; ?>

<!-- ======================== -->
<!-- OUTRO -->
<!-- ======================== -->
<section class="lp2-section lp2-outro">
  <div class="lp2-outro-title">Explore The Archive</div>
  <div class="lp2-hero-actions" style="justify-content:center;">
    <a href="tournaments" class="lp2-btn lp2-btn-primary lp2-btn-arrow">大会一覧へ</a>
    <a href="players" class="lp2-btn lp2-btn-ghost">選手一覧へ</a>
  </div>
</section>

</div><!-- /.lp2-wrap -->
</div><!-- /.lp2 -->

<?php require __DIR__ . '/../templates/footer.php'; ?>
