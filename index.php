<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>最強位戦 - 麻雀大会</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;600;700;900&family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/components.css">
<link rel="stylesheet" href="css/finals.css">
<link rel="stylesheet" href="css/mahjong-deco.css">
<link rel="stylesheet" href="css/theme-dark.css" id="theme-dark">
<link rel="stylesheet" href="css/theme-toggle.css">

<!-- ★追加: カウントダウン用のスタイル -->
<style>
  .finals-countdown-container {
    margin: 10px auto 20px auto;
    padding: 8px 24px;
    background: linear-gradient(135deg, #ff3366, #ff9933);
    border-radius: 50px;
    display: inline-block;
    box-shadow: 0 4px 15px rgba(255, 51, 102, 0.4);
    animation: pulse-countdown 2s infinite;
    position: relative;
    z-index: 10;
  }
  
  .finals-countdown-text {
    color: #ffffff;
    font-size: 1.2rem;
    font-weight: 800;
    letter-spacing: 1px;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
    font-family: 'Noto Sans JP', sans-serif;
  }
  
  .finals-countdown-text .days {
    font-size: 2rem;
    margin: 0 6px;
    color: #fff;
    font-weight: 900;
  }
  
  .finals-countdown-text.today {
    font-size: 1.5rem;
    letter-spacing: 2px;
  }
  
  /* 終了後のスタイル */
  .finals-countdown-container.finished-container {
    background: #444;
    box-shadow: none;
    animation: none;
    border: 1px solid #666;
  }
  
  .finals-countdown-text.finished {
    color: #bbb;
    text-shadow: none;
    font-weight: 600;
  }

  @keyframes pulse-countdown {
    0% { transform: scale(1); box-shadow: 0 4px 15px rgba(255, 51, 102, 0.4); }
    50% { transform: scale(1.05); box-shadow: 0 4px 25px rgba(255, 51, 102, 0.7); }
    100% { transform: scale(1); box-shadow: 0 4px 15px rgba(255, 51, 102, 0.4); }
  }
</style>
</head>
<body>

<!-- Theme Toggle -->
<div class="theme-toggle" id="theme-toggle">
  <span class="theme-toggle-icon theme-toggle-sun">&#x2600;</span>
  <div class="theme-toggle-track" id="theme-track">
    <div class="theme-toggle-thumb"></div>
  </div>
  <span class="theme-toggle-icon theme-toggle-moon">&#x1F319;</span>
</div>

<!-- Floating Particles -->
<div class="particles" id="particles"></div>

<!-- Floating Tile Scatter (page-level) -->
<div class="tile-scatter" id="tile-scatter"></div>

<div class="main">

<!-- Hero -->
<section class="hero">
  <div class="hero-tiles">&#x1F000;&#x1F001;&#x1F002;&#x1F003;&#x1F004;&#x1F005;&#x1F006;&#x1F007;&#x1F008;&#x1F009;&#x1F00A;&#x1F00B;&#x1F00C;&#x1F00D;&#x1F00E;&#x1F00F;&#x1F010;&#x1F011;&#x1F012;&#x1F013;&#x1F014;&#x1F015;&#x1F016;&#x1F017;&#x1F018;&#x1F019;&#x1F01A;&#x1F01B;&#x1F01C;&#x1F01D;&#x1F01E;&#x1F01F;&#x1F020;&#x1F021;&#x1F000;&#x1F001;&#x1F002;&#x1F003;&#x1F004;&#x1F005;&#x1F006;&#x1F007;&#x1F008;&#x1F009;&#x1F00A;&#x1F00B;&#x1F00C;&#x1F00D;</div>
  <span class="hero-tile-accent top-left">&#x1F004;</span>
  <span class="hero-tile-accent top-right">&#x1F005;</span>
  <span class="hero-tile-accent bot-left">&#x1F000;</span>
  <span class="hero-tile-accent bot-right">&#x1F006;</span>
  <div class="hero-badge">麻雀トーナメント</div>
  <h1 class="hero-title">最強位戦</h1>
  <div class="hero-rules">
    <span>赤4</span><span>60秒</span><span>トビ無</span>
  </div>
  <div class="hero-record">
    <div class="hero-record-inner">
      <span class="label">&#x1F3C5; 大会最高得点</span>
      <span class="value">65,400</span>
      <span class="player">するが</span>
    </div>
  </div>
</section>

<!-- Divider -->
<div class="tile-divider">
  <div class="tile-divider-line"></div>
  <div class="tile-divider-tiles">&#x1F000;&#x1F001;&#x1F002;&#x1F003;</div>
  <div class="tile-divider-line"></div>
</div>

<!-- Progress Tracker -->
<section class="progress-section">
  <div class="progress-track">
    <div class="progress-tile-bg">&#x1F007;&#x1F008;&#x1F009;&#x1F00A;&#x1F00B;</div>
    <div class="progress-step">
      <div class="step-circle done">&#10003;</div>
      <div class="step-label">1回戦</div>
      <div class="step-count">20名</div>
    </div>
    <div class="step-line done"></div>
    <div class="progress-step">
      <div class="step-circle done">&#10003;</div>
      <div class="step-label">2回戦</div>
      <div class="step-count">16名</div>
    </div>
    <div class="step-line done"></div>
    <div class="progress-step">
      <div class="step-circle done">&#10003;</div>
      <div class="step-label">3回戦</div>
      <div class="step-count">12名</div>
    </div>
    <div class="step-line done"></div>
    <div class="progress-step">
      <div class="step-circle active">決</div>
      <div class="step-label">決勝</div>
      <div class="step-count">4名</div>
    </div>
  </div>
</section>

<!-- Finals Showdown -->
<section class="finals-section reveal" id="finals-section">
  <div class="finals-stage">
    <div class="finals-corner tl"></div>
    <div class="finals-corner tr"></div>
    <div class="finals-corner bl"></div>
    <div class="finals-corner br"></div>
    <div class="finals-embers" id="finals-embers"></div>
    <div class="confetti-container" id="confetti-container"></div>
    <div class="energy-lines" id="energy-lines"></div>

    <div class="finals-header">
      <div class="finals-pretitle">THE FINAL TABLE</div>
      <h2 class="finals-title">決勝卓</h2>
      
      <!-- ★追加: カウントダウン表示 -->
      <div class="finals-countdown-container" id="countdown-container">
        <div class="finals-countdown-text" id="countdown-text"></div>
      </div>
      
      <div class="finals-subtitle">予選を勝ち抜いた<span>4名</span>が最強位の座を賭けて激突</div>
      <div class="finals-sparkle-line"></div>
    </div>

    <div class="finals-grid" id="finals-grid">
      <div class="finalist-card" data-delay="1.2">
        <div class="finalist-seed">1ST SEED</div>
        <div class="finalist-avatar">&#x1F47A;</div>
        <div class="finalist-name">みか</div>
        <div class="finalist-score plus">+118.0</div>
        <div class="finalist-trend">+22.3 → +54.6 → +41.1</div>
      </div>
      <div class="finalist-card" data-delay="1.5">
        <div class="finalist-seed">2ND SEED</div>
        <div class="finalist-avatar">&#x1F525;</div>
        <div class="finalist-name">がちゃ</div>
        <div class="finalist-score plus">+114.3</div>
        <div class="finalist-trend">+51.9 → +24.9 → +37.5</div>
      </div>
      <div class="finalist-card" data-delay="1.8">
        <div class="finalist-seed">3RD SEED</div>
        <div class="finalist-avatar">&#x26A1;</div>
        <div class="finalist-name">ホロホロ</div>
        <div class="finalist-score plus">+102.9</div>
        <div class="finalist-trend">+10.9 → +43.4 → +48.6</div>
      </div>
      <div class="finalist-card" data-delay="2.1">
        <div class="finalist-seed">4TH SEED</div>
        <div class="finalist-avatar">&#x1F300;</div>
        <div class="finalist-name">するが</div>
        <div class="finalist-score minus">-1.2</div>
        <div class="finalist-trend">-44.4 → +10.0 → +33.2</div>
      </div>
      <div class="finals-vs">VS</div>
    </div>

    <div class="finals-bottom">
      <div class="finals-status">
        <div class="finals-dot"></div>
        決勝戦 ― 3月22日（日）20時30分より開始！
      </div>
    </div>
  </div>
</section>

<!-- Divider -->
<div class="tile-divider">
  <div class="tile-divider-line"></div>
  <div class="tile-divider-tiles">&#x1F010;&#x1F011;&#x1F012;&#x1F013;</div>
  <div class="tile-divider-line"></div>
</div>

<!-- Round Details -->
<section class="section section--decorated reveal">
  <div class="section-tiles" id="section-tiles-rounds"></div>
  <div class="section-header">
    <div class="section-title">&#x1F004; 対戦結果</div>
  </div>

  <div class="tabs">
    <button class="tab-btn" onclick="switchTab(0)">1回戦<br><small>5卓 20名</small></button>
    <button class="tab-btn" onclick="switchTab(1)">2回戦<br><small>4卓 16名</small></button>
    <button class="tab-btn active" onclick="switchTab(2)">3回戦<br><small>3卓 12名</small></button>
  </div>

  <div class="tab-content" id="tab0"></div>
  <div class="tab-content" id="tab1"></div>
  <div class="tab-content active" id="tab2"></div>
</section>

<!-- Divider -->
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

<!-- Divider -->
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
    <span class="record-label">大会最高得点</span>
    <span class="record-score" data-count="65400">0</span>
    <span class="record-player">するが</span>
  </div>
  <div class="records-stats">
    <div class="stat"><div class="stat-num" data-count="20">0</div><div class="stat-label">参加者</div></div>
    <div class="stat"><div class="stat-num" data-count="4">0</div><div class="stat-label">残り</div></div>
    <div class="stat"><div class="stat-num" data-count="3">0</div><div class="stat-label">回戦目</div></div>
  </div>
</section>

<div class="footer-tiles">&#x1F007;&#x1F008;&#x1F009;&#x1F00A;&#x1F00B;&#x1F00C;&#x1F00D;&#x1F00E;&#x1F00F;</div>
<div class="footer">最強位戦 - 麻雀トーナメント</div>

</div>

<script src="js/data.js"></script>
<script src="js/render.js"></script>
<script src="js/effects.js"></script>
<script src="js/theme-toggle.js"></script>

<!-- ★追加: カウントダウン制御用のスクリプト -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  const countdownContainer = document.getElementById('countdown-container');
  const countdownText = document.getElementById('countdown-text');
  
  if (!countdownContainer || !countdownText) return;

  const now = new Date();
  
  // 今日の日付 (時間を0時0分0秒にリセットして日付のみで比較)
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  
  // 決勝日: 今年の3月22日 (月は0始まりのため2=3月)
  const finalDay = new Date(now.getFullYear(), 2, 22); 
  
  // 差分を計算して日数に変換
  const diffTime = finalDay - today;
  const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));
  
  if (diffDays > 0) {
    // 開催前
    countdownText.innerHTML = `開催まであと<span class="days">${diffDays}</span>日！`;
  } else if (diffDays === 0) {
    // 当日
    countdownText.innerHTML = `本日開催！`;
    countdownText.classList.add('today');
  } else {
    // 終了後（23日以降）
    countdownText.innerHTML = `大会は終了しました`;
    countdownContainer.classList.add('finished-container');
    countdownText.classList.add('finished');
  }
});
</script>
</body>
</html>