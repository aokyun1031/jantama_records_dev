<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>最強位戦 - 麻雀大会</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;600;700;900&family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root {
  --bg1:#ede7f6;--bg2:#fce4ec;--bg3:#e0f7fa;--bg4:#fff8e1;
  --card:rgba(255,255,255,0.72);--card-hover:rgba(255,255,255,0.88);
  --glass-border:rgba(255,255,255,0.95);
  --text:#2d2b55;--text-sub:#8280a8;--text-light:#b0aed0;
  --purple:#9b8ce8;--pink:#e88cad;--mint:#5cc8b0;--gold:#d4a84c;
  --blue:#7ca8e8;--coral:#e8907c;--lavender:#b8a0e8;
  --plus-bar:linear-gradient(90deg,#f8bbd0,#e88cad,#d47098);
  --minus-bar:linear-gradient(90deg,#b3d8f0,#7ca8e8,#5c88d0);
  --plus-text:#c0507a;--minus-text:#4870b8;
  --shadow:0 8px 32px rgba(100,80,160,0.10);
  --shadow-sm:0 2px 12px rgba(100,80,160,0.06);
  --radius:16px;--radius-sm:10px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
  font-family:'Noto Sans JP','Inter',sans-serif;
  color:var(--text);min-height:100vh;overflow-x:hidden;
  background:linear-gradient(135deg,var(--bg1) 0%,var(--bg2) 33%,var(--bg3) 66%,var(--bg4) 100%);
  background-size:400% 400%;
  animation:bgShift 20s ease infinite;
}
@keyframes bgShift{
  0%{background-position:0% 50%}25%{background-position:100% 50%}
  50%{background-position:100% 0%}75%{background-position:0% 100%}100%{background-position:0% 50%}
}

.particles{position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:0;overflow:hidden}
.particle{position:absolute;border-radius:50%;opacity:0;animation:particleFloat linear infinite}
@keyframes particleFloat{
  0%{opacity:0;transform:translateY(100vh) scale(0)}
  10%{opacity:0.4}90%{opacity:0.3}
  100%{opacity:0;transform:translateY(-20vh) scale(1) rotate(360deg)}
}

.main{position:relative;z-index:1;max-width:680px;margin:0 auto;padding:20px 16px 60px}

/* Hero */
.hero{text-align:center;padding:48px 20px 40px;margin-bottom:32px;position:relative;overflow:hidden}
.hero-tiles{
  position:absolute;top:0;left:0;width:100%;height:100%;
  font-size:28px;opacity:0.25;pointer-events:none;
  display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:12px;
  animation:tilesDrift 30s linear infinite;
}
@keyframes tilesDrift{0%{transform:translateX(-5%)}50%{transform:translateX(5%)}100%{transform:translateX(-5%)}}
.hero-badge{
  display:inline-block;background:linear-gradient(135deg,var(--lavender),var(--pink));
  color:#fff;font-size:0.7rem;font-weight:700;
  padding:4px 14px;border-radius:20px;margin-bottom:16px;letter-spacing:2px;
  animation:fadeDown 0.8s ease both;
  box-shadow:0 2px 12px rgba(184,160,232,0.3);
}
.hero-title{
  font-family:'Inter','Noto Sans JP',sans-serif;
  font-size:3rem;font-weight:900;letter-spacing:4px;
  background:linear-gradient(135deg,#9b8ce8,#e88cad,#d4a84c,#5cc8b0);
  background-size:300% 300%;
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
  animation:titleGrad 6s ease infinite, fadeUp 1s ease both;
  line-height:1.2;margin-bottom:12px;
  filter:drop-shadow(0 2px 8px rgba(155,140,232,0.2));
}
@keyframes titleGrad{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.hero-rules{font-size:0.85rem;color:var(--text-sub);font-weight:400;animation:fadeUp 1s ease 0.3s both}
.hero-rules span{
  display:inline-block;background:var(--card);backdrop-filter:blur(8px);
  padding:4px 12px;border-radius:8px;margin:3px;font-weight:600;
  border:1px solid rgba(255,255,255,0.6);transition:transform 0.2s,box-shadow 0.2s;
}
.hero-rules span:hover{transform:translateY(-1px);box-shadow:0 3px 10px rgba(100,80,160,0.1)}
.hero-record{margin-top:20px;animation:fadeUp 1s ease 0.6s both;display:flex;justify-content:center;gap:10px;flex-wrap:wrap}
.hero-record-inner{
  display:inline-flex;align-items:center;gap:8px;
  background:linear-gradient(135deg,rgba(212,168,76,0.15),rgba(212,168,76,0.05));
  border:1px solid rgba(212,168,76,0.3);padding:8px 20px;border-radius:12px;font-size:0.82rem;
  transition:transform 0.2s,box-shadow 0.2s;
}
.hero-record-inner:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(212,168,76,0.15)}
.hero-record-inner .label{color:var(--gold);font-weight:700}
.hero-record-inner .value{font-family:'Inter',sans-serif;font-weight:800;font-size:1.1rem;color:var(--gold)}
.hero-record-inner .player{color:var(--text-sub);font-weight:600}

/* Progress */
.progress-section{margin-bottom:36px;animation:fadeUp 1s ease 0.4s both}
.progress-track{
  display:flex;align-items:center;justify-content:center;
  padding:24px 12px;background:var(--card);backdrop-filter:blur(12px);
  border-radius:var(--radius);border:1px solid var(--glass-border);
  box-shadow:var(--shadow-sm);position:relative;overflow:hidden;
}
.progress-step{display:flex;flex-direction:column;align-items:center;gap:6px;position:relative;z-index:1;flex:0 0 auto}
.step-circle{
  width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:0.85rem;transition:all 0.5s;font-family:'Inter',sans-serif;
}
.step-circle.done{background:linear-gradient(135deg,var(--mint),#48b090);color:#fff;box-shadow:0 4px 16px rgba(92,200,176,0.3)}
.step-circle.active{
  background:linear-gradient(135deg,var(--pink),var(--coral));color:#fff;
  box-shadow:0 4px 16px rgba(232,140,173,0.4),0 0 0 4px rgba(232,140,173,0.15);animation:pulse 2s ease infinite;
}
.step-circle.upcoming{background:rgba(0,0,0,0.04);color:var(--text-light);border:2px dashed rgba(0,0,0,0.1)}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}
.step-label{font-size:0.7rem;font-weight:600;color:var(--text-sub);white-space:nowrap}
.step-count{font-size:0.65rem;color:var(--text-light);font-weight:400}
.step-line{height:3px;flex:1;min-width:24px;max-width:60px;border-radius:2px;margin:0 4px;margin-bottom:28px}
.step-line.done{background:linear-gradient(90deg,var(--mint),rgba(92,200,176,0.3))}
.step-line.active{background:linear-gradient(90deg,rgba(232,140,173,0.5),rgba(0,0,0,0.05));background-size:200% 100%;animation:lineShimmer 2s ease infinite}
.step-line.upcoming{background:rgba(0,0,0,0.05)}
@keyframes lineShimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}

/* Section */
.section{margin-bottom:32px}
.section-header{display:flex;align-items:center;gap:10px;margin-bottom:16px;padding:0 4px;padding-bottom:10px;border-bottom:2px solid rgba(155,140,232,0.12)}
.section-title{font-size:1.2rem;font-weight:800;letter-spacing:1px}
.section-badge{font-size:0.6rem;font-weight:700;padding:3px 10px;border-radius:12px;letter-spacing:1px}
.badge-live{background:linear-gradient(135deg,#fce4ec,#f8bbd0);color:#c0507a;animation:pulse 2s ease infinite}
.badge-done{background:linear-gradient(135deg,#e0f7fa,#b2ebf2);color:#00838f}

/* Standings */
.standing-item{
  position:relative;overflow:hidden;
  background:var(--card);backdrop-filter:blur(12px);
  border:1px solid var(--glass-border);border-radius:var(--radius-sm);
  padding:14px 16px;margin-bottom:8px;
  display:grid;grid-template-columns:32px 1fr auto;align-items:center;gap:10px;
  box-shadow:var(--shadow-sm);
  opacity:0;transform:translateY(16px);
  transition:all 0.4s cubic-bezier(0.4,0,0.2,1);
}
.standing-item.visible{opacity:1;transform:translateY(0)}
.standing-item:hover{background:var(--card-hover);transform:translateY(-2px) !important;box-shadow:var(--shadow)}
.standing-item.top-1{border-left:3px solid var(--gold)}
.standing-item.top-2{border-left:3px solid #a8b4c4}
.standing-item.top-3{border-left:3px solid #c8956c}
.standing-bar{
  position:absolute;top:0;left:0;height:100%;opacity:0.10;
  border-radius:var(--radius-sm);width:0;
  transition:width 1.5s cubic-bezier(0.25,0.8,0.25,1);
}
.standing-bar.plus{background:var(--plus-bar)}
.standing-bar.minus{background:var(--minus-bar)}
.standing-rank{font-family:'Inter',sans-serif;font-weight:900;font-size:1rem;text-align:center;position:relative;z-index:1}
.standing-info{position:relative;z-index:1}
.standing-name{font-weight:700;font-size:0.95rem;display:flex;align-items:center;gap:6px}
.standing-detail{font-size:0.65rem;color:var(--text-light);margin-top:2px;font-family:'Inter',sans-serif}
.standing-score{font-family:'Inter',sans-serif;font-weight:800;font-size:1.15rem;position:relative;z-index:1}
.standing-score.plus{color:var(--plus-text)}
.standing-score.minus{color:var(--minus-text)}
.badge-pending{
  font-size:0.55rem;font-weight:700;
  background:linear-gradient(135deg,#fff3e0,#ffe0b2);color:#e65100;
  padding:2px 7px;border-radius:6px;
}
.badge-elim{
  font-size:0.55rem;font-weight:700;
  background:linear-gradient(135deg,#f5f5f5,#e0e0e0);color:#888;
  padding:2px 7px;border-radius:6px;
}
.standing-item.eliminated{opacity:0.55}
.standing-item.eliminated .standing-score{color:var(--text-light)!important}
.standing-elim-divider{
  text-align:center;padding:12px 0 6px;color:var(--text-light);
  font-size:0.7rem;font-weight:700;letter-spacing:2px;
  display:flex;align-items:center;gap:12px;
}
.standing-elim-divider::before,.standing-elim-divider::after{
  content:'';flex:1;height:1px;
  background:linear-gradient(90deg,transparent,rgba(0,0,0,0.1),transparent);
}
.medal{font-size:1.1rem;line-height:1}
.standing-divider{
  text-align:center;padding:8px 0;color:var(--text-light);
  font-size:0.7rem;font-weight:600;letter-spacing:2px;
  display:flex;align-items:center;gap:12px;
}
.standing-divider::before,.standing-divider::after{
  content:'';flex:1;height:1px;
  background:linear-gradient(90deg,transparent,var(--text-light),transparent);opacity:0.3;
}

/* Tabs */
.tabs{display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap}
.tab-btn{
  flex:1;min-width:80px;padding:10px 8px;border:none;border-radius:var(--radius-sm);
  font-family:'Noto Sans JP',sans-serif;font-weight:700;font-size:0.8rem;
  cursor:pointer;transition:all 0.3s;
  background:var(--card);color:var(--text-sub);
  border:1px solid var(--glass-border);box-shadow:var(--shadow-sm);
}
.tab-btn:hover{background:var(--card-hover);transform:translateY(-1px)}
.tab-btn.active{
  background:linear-gradient(135deg,var(--purple),var(--pink));
  color:#fff;border-color:transparent;box-shadow:0 4px 16px rgba(155,140,232,0.3);
  transform:translateY(-1px);
}
.tab-btn small{font-weight:400;font-size:0.6rem;opacity:0.8}
.tab-content{display:none;animation:tabIn 0.4s ease}
.tab-content.active{display:block}
@keyframes tabIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* Table Cards */
.table-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-bottom:20px}
.table-card{
  background:var(--card);backdrop-filter:blur(8px);
  border:1px solid var(--glass-border);border-radius:var(--radius-sm);
  padding:12px;box-shadow:var(--shadow-sm);transition:transform 0.3s,box-shadow 0.3s;
}
.table-card:hover{transform:translateY(-2px);box-shadow:var(--shadow)}
.table-card-head{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:8px;padding-bottom:6px;border-bottom:2px solid rgba(155,140,232,0.15);
}
.table-card-name{font-weight:800;font-size:0.85rem;color:var(--purple)}
.table-card-sched{font-size:0.6rem;color:var(--text-light);font-weight:600}
.table-card-players{list-style:none;font-size:0.8rem;line-height:1.9}
.table-card-players li{display:flex;align-items:center;gap:4px}
.table-card-players li::before{content:'';width:4px;height:4px;border-radius:50%;background:var(--lavender);flex-shrink:0}
.table-done{border-color:rgba(92,200,176,0.3)}
.table-pending{border-color:rgba(255,152,0,0.3);background:linear-gradient(135deg,rgba(255,248,225,0.5),var(--card))}
.table-pending .table-card-name{color:#e65100}

/* Results */
.results-list{margin-bottom:16px}
.result-row{
  display:grid;grid-template-columns:28px 1fr auto;align-items:center;gap:8px;
  padding:8px 12px;border-radius:8px;font-size:0.85rem;transition:background 0.2s;
}
.result-row:hover{background:rgba(155,140,232,0.06)}
.result-rank{font-family:'Inter',sans-serif;font-weight:800;font-size:0.75rem;color:var(--text-light);text-align:center}
.result-name{font-weight:600}
.result-score{font-family:'Inter',sans-serif;font-weight:700;font-size:0.9rem;text-align:right}
.result-score.plus{color:var(--plus-text)}
.result-score.minus{color:var(--minus-text)}
.result-divider{height:1px;margin:10px 12px;background:linear-gradient(90deg,transparent,rgba(232,140,173,0.4),transparent)}
.elim-section{margin-top:8px;padding:12px;background:rgba(0,0,0,0.02);border-radius:var(--radius-sm)}
.elim-title{font-size:0.7rem;color:var(--text-light);font-weight:700;margin-bottom:8px;letter-spacing:1px}
.elim-row{opacity:0.55}
.results-sub{font-size:0.7rem;color:var(--text-light);font-weight:600;padding:4px 12px;margin-bottom:4px;letter-spacing:1px}

/* Records */
.records{
  background:var(--card);backdrop-filter:blur(12px);
  border:1px solid var(--glass-border);border-radius:var(--radius);
  padding:24px;box-shadow:var(--shadow);text-align:center;margin-top:40px;
}
.records-title{font-size:0.75rem;color:var(--text-light);font-weight:700;letter-spacing:3px;margin-bottom:16px}
.record-highlight{
  display:inline-flex;flex-direction:column;align-items:center;gap:4px;
  background:linear-gradient(135deg,rgba(212,168,76,0.1),rgba(212,168,76,0.03));
  border:1px solid rgba(212,168,76,0.2);border-radius:var(--radius-sm);padding:16px 32px;
  transition:transform 0.3s,box-shadow 0.3s;
}
.record-highlight:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(100,80,160,0.12)}
.record-label{font-size:0.7rem;color:var(--gold);font-weight:700}
.record-score{font-family:'Inter',sans-serif;font-size:2.2rem;font-weight:900;color:var(--gold);letter-spacing:1px}
.record-player{font-size:0.85rem;color:var(--text-sub);font-weight:600}
.records-stats{display:flex;justify-content:center;gap:32px;margin-top:20px;flex-wrap:wrap}
.stat{text-align:center}
.stat-num{font-family:'Inter',sans-serif;font-size:1.8rem;font-weight:900;color:var(--purple)}
.stat-label{font-size:0.65rem;color:var(--text-light);font-weight:600}
.footer{text-align:center;padding:32px 0 16px;color:var(--text-light);font-size:0.65rem}

/* Pending note */
.pending-note{
  text-align:center;padding:16px;color:var(--text-light);font-size:0.75rem;font-weight:600;
  background:linear-gradient(135deg,rgba(255,248,225,0.5),rgba(255,224,178,0.3));
  border-radius:var(--radius-sm);border:1px dashed rgba(255,152,0,0.3);
}
.note-small{text-align:right;margin-top:4px;font-size:0.6rem;color:var(--text-light)}

/* Table Detail Results */
.table-details{margin-top:20px}
.table-detail-block{
  background:var(--card);backdrop-filter:blur(12px);
  border:1px solid var(--glass-border);border-radius:var(--radius-sm);
  padding:14px 16px;margin-bottom:10px;box-shadow:var(--shadow-sm);
  transition:box-shadow 0.3s;
}
.table-detail-block:hover{box-shadow:var(--shadow)}
.table-detail-head{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:10px;padding-bottom:8px;
  border-bottom:2px solid rgba(155,140,232,0.15);
}
.table-detail-name{font-weight:800;font-size:0.9rem;color:var(--purple)}
.table-detail-badge{font-size:0.6rem;font-weight:700;padding:2px 8px;border-radius:8px}
.badge-top{background:linear-gradient(135deg,rgba(212,168,76,0.15),rgba(212,168,76,0.08));color:var(--gold);border:1px solid rgba(212,168,76,0.25)}
.table-detail-row{
  display:grid;grid-template-columns:24px 1fr auto;align-items:center;gap:8px;
  padding:6px 8px;border-radius:6px;font-size:0.85rem;
}
.table-detail-row:nth-child(1){background:linear-gradient(90deg,rgba(212,168,76,0.08),transparent)}
.table-detail-pos{font-family:'Inter',sans-serif;font-weight:800;font-size:0.75rem;color:var(--text-light);text-align:center}
.table-detail-pos.pos-1{color:var(--gold)}
.table-detail-player{font-weight:600;display:flex;align-items:center;gap:5px}
.table-detail-score{font-family:'Inter',sans-serif;font-weight:700;font-size:0.9rem}
.table-detail-score.plus{color:var(--plus-text)}
.table-detail-score.minus{color:var(--minus-text)}
.table-detail-pending{
  text-align:center;padding:12px;color:var(--text-light);font-size:0.75rem;font-weight:600;
  background:linear-gradient(135deg,rgba(255,248,225,0.5),rgba(255,224,178,0.3));
  border-radius:var(--radius-sm);border:1px dashed rgba(255,152,0,0.3);
}

.reveal{opacity:0;transform:translateY(24px);transition:all 0.7s cubic-bezier(0.4,0,0.2,1)}
.reveal.visible{opacity:1;transform:translateY(0)}

@media(max-width:480px){
  .hero-title{font-size:2.2rem;letter-spacing:2px}
  .standing-item{padding:12px;grid-template-columns:28px 1fr auto;gap:8px}
  .standing-score{font-size:1rem}
  .table-grid{grid-template-columns:repeat(auto-fill,minmax(130px,1fr))}
  .progress-track{padding:16px 8px}
  .step-circle{width:36px;height:36px;font-size:0.75rem}
  .step-line{min-width:16px;max-width:40px}
  .records-stats{gap:20px}
}
</style>
</head>
<body>

<!-- Floating Particles -->
<div class="particles" id="particles"></div>

<div class="main">

<!-- Hero -->
<section class="hero">
  <div class="hero-tiles">&#x1F000;&#x1F001;&#x1F002;&#x1F003;&#x1F004;&#x1F005;&#x1F006;&#x1F007;&#x1F008;&#x1F009;&#x1F00A;&#x1F00B;&#x1F00C;&#x1F00D;&#x1F00E;&#x1F00F;&#x1F010;&#x1F011;&#x1F012;&#x1F013;&#x1F014;&#x1F015;&#x1F016;&#x1F017;&#x1F018;&#x1F019;&#x1F01A;&#x1F01B;&#x1F01C;&#x1F01D;&#x1F01E;&#x1F01F;&#x1F020;&#x1F021;&#x1F000;&#x1F001;&#x1F002;&#x1F003;&#x1F004;&#x1F005;&#x1F006;&#x1F007;&#x1F008;&#x1F009;&#x1F00A;&#x1F00B;&#x1F00C;&#x1F00D;</div>
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

<!-- Progress Tracker -->
<section class="progress-section">
  <div class="progress-track">
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
      <div class="step-circle active">3</div>
      <div class="step-label">3回戦</div>
      <div class="step-count">12名</div>
    </div>
    <div class="step-line active"></div>
    <div class="progress-step">
      <div class="step-circle upcoming">?</div>
      <div class="step-label">決勝</div>
      <div class="step-count">—</div>
    </div>
  </div>
</section>

<!-- Round Details -->
<section class="section reveal">
  <div class="section-header">
    <div class="section-title">&#x1F4CB; 対戦結果</div>
  </div>

  <div class="tabs">
    <button class="tab-btn" onclick="switchTab(0)">1回戦<br><small>5卓 20名</small></button>
    <button class="tab-btn" onclick="switchTab(1)">2回戦<br><small>4卓 16名</small></button>
    <button class="tab-btn active" onclick="switchTab(2)">3回戦<br><small>3卓 12名</small></button>
  </div>

  <!-- Round 1 -->
  <div class="tab-content" id="tab0"></div>
  <!-- Round 2 -->
  <div class="tab-content" id="tab1"></div>
  <!-- Round 3 -->
  <div class="tab-content active" id="tab2"></div>
</section>

<!-- Cumulative Standings -->
<section class="section reveal" id="standings-section">
  <div class="section-header">
    <div class="section-title">&#x1F4CA; 総合ポイント</div>
    <!-- <span class="section-badge badge-live">LIVE</span> -->
  </div>
  <div id="standings"></div>
  <div class="note-small">※ 3回戦 3卓は未対戦のため暫定順位</div>
</section>

<!-- Records -->
<section class="records reveal">
  <div class="records-title">トーナメントレコード</div>
  <div class="record-highlight">
    <span class="record-label">大会最高得点</span>
    <span class="record-score" data-count="65400">0</span>
    <span class="record-player">するが</span>
  </div>
  <div class="records-stats">
    <div class="stat"><div class="stat-num" data-count="20">0</div><div class="stat-label">参加者</div></div>
    <div class="stat"><div class="stat-num" data-count="12">0</div><div class="stat-label">残り</div></div>
    <div class="stat"><div class="stat-num" data-count="3">0</div><div class="stat-label">回戦目</div></div>
  </div>
</section>

<div class="footer">最強位戦 - 麻雀トーナメント</div>

</div>

<script>
// ===== DATA =====
var MAX_BAR = 130;
var MEDALS = ['\u{1F947}','\u{1F948}','\u{1F949}'];

var standings = [
  // 現役（3回戦進出）
  {rank:1,name:'みか',total:118.0,r:[22.3,54.6,41.1],pending:false,elim:0},
  {rank:2,name:'ホロホロ',total:102.9,r:[10.9,43.4,48.6],pending:false,elim:0},
  {rank:3,name:'あはん',total:101.3,r:[82.5,18.8],pending:true,elim:0},
  {rank:4,name:'がちゃ',total:76.8,r:[51.9,24.9],pending:true,elim:0},
  {rank:5,name:'ぎり',total:68.7,r:[10.2,58.5],pending:true,elim:0},
  {rank:6,name:'シーマ',total:55.5,r:[51.7,2.3,1.5],pending:false,elim:0},
  {rank:7,name:'みーた',total:47.8,r:[26.9,-10.3,31.2],pending:false,elim:0},
  {rank:8,name:'あき',total:-0.7,r:[-16.1,37.3,-21.9],pending:false,elim:0},
  {rank:9,name:'するが',total:-1.2,r:[-44.4,10.0,33.2],pending:false,elim:0},
  {rank:10,name:'イラチ',total:-53.4,r:[-49.1,-4.3],pending:true,elim:0},
  {rank:11,name:'りあ',total:-68.0,r:[-6.5,-33.3,-28.2],pending:false,elim:0},
  {rank:12,name:'がう',total:-73.0,r:[28.7,3.8,-105.5],pending:false,elim:0},
  // 2回戦敗退
  {rank:13,name:'ぶる',total:-22.7,r:[11.9,-34.6],pending:false,elim:2},
  {rank:14,name:'そぼろ',total:-27.4,r:[12.2,-39.6],pending:false,elim:2},
  {rank:15,name:'けちゃこ',total:-71.2,r:[1.1,-72.3],pending:false,elim:2},
  {rank:16,name:'梅',total:-80.4,r:[-22.0,-58.4],pending:false,elim:2},
  // 1回戦敗退
  {rank:17,name:'こいぬ',total:40.3,r:[40.3],pending:false,elim:1},
  {rank:18,name:'ぱーらめんこ',total:-63.7,r:[-63.7],pending:false,elim:1},
  {rank:19,name:'あーす',total:-72.8,r:[-72.8],pending:false,elim:1},
  {rank:20,name:'なぎ',total:-76.0,r:[-76.0],pending:false,elim:1}
];

var r1Tables = [
  {name:'1卓',sched:'',players:['みか','こいぬ','ぱーらめんこ','けちゃこ']},
  {name:'2卓',sched:'金曜 21:00',players:['ぶる','がちゃ','なぎ','そぼろ']},
  {name:'3卓',sched:'土曜 21:00',players:['ぎり','ホロホロ','あーす','シーマ']},
  {name:'4卓',sched:'日曜 13:00',players:['みーた','りあ','がう','イラチ']},
  {name:'5卓',sched:'日曜 22:00',players:['あき','あはん','梅','するが']}
];
var r1Above = [
  ['あはん',82.5],['がちゃ',51.9],['シーマ',51.7],['こいぬ',40.3],
  ['がう',28.7],['みーた',26.9],['みか',22.3],['そぼろ',12.2],
  ['ぶる',11.9],['ホロホロ',10.9],['ぎり',10.2],['けちゃこ',1.1],
  ['りあ',-6.5],['あき',-16.1],['梅',-22.0],['するが',-44.4]
];
var r1Below = [['イラチ',-49.1],['ぱーらめんこ',-63.7],['あーす',-72.8],['なぎ',-76.0]];

var r2Tables = [
  {name:'1卓',sched:'',players:['みか','ホロホロ','梅','そぼろ']},
  {name:'2卓',sched:'',players:['ぶる','シーマ','あき','イラチ']},
  {name:'3卓',sched:'',players:['ぎり','がう','するが','けちゃこ']},
  {name:'4卓',sched:'',players:['みーた','あはん','がちゃ','りあ']}
];
var r2Above = [
  ['ぎり',58.5],['みか',54.6],['ホロホロ',43.4],['あき',37.3],
  ['がちゃ',24.9],['あはん',18.8],['するが',10.0],['がう',3.8],
  ['シーマ',2.3],['イラチ',-4.3],['みーた',-10.3],['りあ',-33.3]
];
var r2Below = [['ぶる',-34.6],['そぼろ',-39.6],['梅',-58.4],['けちゃこ',-72.3]];

var r3Tables = [
  {name:'1卓',sched:'',players:['あき','ホロホロ','シーマ','りあ'],done:true},
  {name:'2卓',sched:'日曜夜',players:['みか','みーた','がう','するが'],done:true},
  {name:'3卓',sched:'',players:['あはん','イラチ','がちゃ','ぎり'],done:false}
];
var r3Above = [['ホロホロ',48.6],['みか',41.1],['するが',33.2],['みーた',31.2]];
var r3Below = [['シーマ',1.5],['あき',-21.9],['りあ',-28.2],['がう',-105.5]];

// ===== HELPERS =====
function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML}
function cls(s){return s>=0?'plus':'minus'}
function fmt(s){return (s>=0?'+':'')+s.toFixed(1)}
function barW(s){return Math.min(Math.abs(s)/MAX_BAR*100,100)}

// ===== PARTICLES =====
(function(){
  var c=['#d0b8f0','#f0b8c8','#b8e8d8','#f0d8a0','#a8c8f0','#f0a8b8'];
  var el=document.getElementById('particles');
  for(var i=0;i<18;i++){
    var d=document.createElement('div');
    d.className='particle';
    var sz=6+Math.random()*14;
    d.style.cssText='width:'+sz+'px;height:'+sz+'px;left:'+Math.random()*100+'%;background:'+c[i%c.length]+';animation-delay:'+(Math.random()*20)+'s;animation-duration:'+(12+Math.random()*16)+'s;bottom:-'+sz+'px';
    el.appendChild(d);
  }
})();

// ===== RENDER STANDINGS =====
(function(){
  var box=document.getElementById('standings');
  var html='';
  var shownDivider=false;
  var shownElimDivider=false;
  for(var i=0;i<standings.length;i++){
    var p=standings[i];
    // 敗退者セクション区切り
    if(p.elim>0 && !shownElimDivider){
      shownElimDivider=true;
      html+='<div class="standing-elim-divider">\u25BC 敗退者</div>';
    }
    // ±0 ライン
    if(p.elim===0 && p.total<0 && !shownDivider){
      shownDivider=true;
      html+='<div class="standing-divider">\u00B1 0</div>';
    }
    var c=cls(p.total);
    var bw=barW(p.total);
    // ポイント推移テキスト
    var detail=p.r.map(function(v){return (v>=0?'+':'')+v.toFixed(1)}).join(' \u2192 ');
    if(p.elim===1) detail+=' \u2192 1回戦敗退';
    else if(p.elim===2) detail+=' \u2192 2回戦敗退';
    var rankHtml=p.rank<=3?'<span class="medal">'+MEDALS[p.rank-1]+'</span>':''+p.rank;
    // バッジ
    var badgeHtml='';
    if(p.pending) badgeHtml='<span class="badge-pending">3回戦未</span>';
    else if(p.elim===1) badgeHtml='<span class="badge-elim">1回戦敗退</span>';
    else if(p.elim===2) badgeHtml='<span class="badge-elim">2回戦敗退</span>';

    var topCls=p.rank<=3?' top-'+p.rank:'';
    var elimCls=p.elim>0?' eliminated':'';
    html+='<div class="standing-item'+topCls+elimCls+'" data-delay="'+(i*0.08)+'" data-bar="'+bw+'">'
      +'<div class="standing-bar '+c+'"></div>'
      +'<div class="standing-rank">'+rankHtml+'</div>'
      +'<div class="standing-info">'
        +'<div class="standing-name">'+esc(p.name)+' '+badgeHtml+'</div>'
        +'<div class="standing-detail">'+detail+'</div>'
      +'</div>'
      +'<div class="standing-score '+c+'" data-target="'+p.total+'">'+fmt(p.total)+'</div>'
    +'</div>';
  }
  box.innerHTML=html;
})();

// ===== RENDER ROUNDS =====
function renderTables(tables,opts){
  var html='<div class="table-grid">';
  for(var i=0;i<tables.length;i++){
    var t=tables[i];
    var cardCls='table-card';
    var schedHtml='';
    if(opts&&opts.showDone){
      cardCls+= t.done?' table-done':' table-pending';
      schedHtml=t.done?'\u2713 完了':'\u23F3 未対戦';
    } else if(t.sched){
      schedHtml=t.sched;
    }
    html+='<div class="'+cardCls+'"><div class="table-card-head">'
      +'<span class="table-card-name">'+esc(t.name)+'</span>'
      +(schedHtml?'<span class="table-card-sched">'+schedHtml+'</span>':'')
      +'</div><ul class="table-card-players">';
    for(var j=0;j<t.players.length;j++){
      html+='<li>'+esc(t.players[j])+'</li>';
    }
    html+='</ul></div>';
  }
  return html+'</div>';
}

function renderResults(results,startRank){
  var html='';
  for(var i=0;i<results.length;i++){
    var r=results[i];
    html+='<div class="result-row">'
      +'<div class="result-rank">'+(startRank+i)+'</div>'
      +'<div class="result-name">'+esc(r[0])+'</div>'
      +'<div class="result-score '+cls(r[1])+'">'+fmt(r[1])+'</div>'
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
      +'<div class="result-name">'+esc(r[0])+'</div>'
      +'<div class="result-score '+cls(r[1])+'">'+fmt(r[1])+'</div>'
    +'</div>';
  }
  return html+'</div>';
}

// Build score lookup from results arrays
function buildScoreMap(above,below){
  var map={};
  for(var i=0;i<above.length;i++) map[above[i][0]]=above[i][1];
  for(var i=0;i<below.length;i++) map[below[i][0]]=below[i][1];
  return map;
}

// Render per-table detail results
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
      html+='<span class="table-detail-badge" style="background:rgba(255,152,0,0.1);color:#e65100;border:1px solid rgba(255,152,0,0.3)">\u23F3 未対戦</span>';
    }
    html+='</div>';
    if(isPending){
      html+='<div class="table-detail-pending">'+t.players.map(function(p){return esc(p)}).join('・')+' の対戦待ち</div>';
    } else {
      // Sort players by score descending
      var sorted=t.players.map(function(p){return {name:p,score:scoreMap[p]||0}});
      sorted.sort(function(a,b){return b.score-a.score});
      for(var j=0;j<sorted.length;j++){
        var p=sorted[j];
        var posCls=j===0?'pos-1':'';
        var trophy=j===0?'\u{1F451} ':'';
        html+='<div class="table-detail-row">'
          +'<div class="table-detail-pos '+posCls+'">'+posLabels[j]+'</div>'
          +'<div class="table-detail-player">'+trophy+esc(p.name)+'</div>'
          +'<div class="table-detail-score '+cls(p.score)+'">'+fmt(p.score)+'</div>'
        +'</div>';
      }
    }
    html+='</div>';
  }
  return html+'</div>';
}

var r1Scores=buildScoreMap(r1Above,r1Below);
var r2Scores=buildScoreMap(r2Above,r2Below);
var r3Scores=buildScoreMap(r3Above,r3Below);


// Round 1
document.getElementById('tab0').innerHTML=
  renderTables(r1Tables,null)
  +renderTableDetails(r1Tables,r1Scores,null)
  +'<div class="results-list"><div class="results-sub">全体順位</div>'+renderResults(r1Above,1)+'</div>'
  +renderElim(r1Below,17);

// Round 2
document.getElementById('tab1').innerHTML=
  renderTables(r2Tables,null)
  +renderTableDetails(r2Tables,r2Scores,null)
  +'<div class="results-list"><div class="results-sub">全体順位</div>'+renderResults(r2Above,1)+'</div>'
  +renderElim(r2Below,13);

// Round 3
document.getElementById('tab2').innerHTML=
  renderTables(r3Tables,{showDone:true})
  +renderTableDetails(r3Tables,r3Scores,{showDone:true})
  +'<div class="results-list">'
    +'<div class="results-sub">1卓・2卓 結果</div>'
    +renderResults(r3Above,1)
    +'<div class="result-divider"></div>'
    +renderResults(r3Below,5)
  +'</div>'

// ===== TAB SWITCHING =====
function switchTab(idx){
  var btns=document.querySelectorAll('.tab-btn');
  var tabs=document.querySelectorAll('.tab-content');
  for(var i=0;i<btns.length;i++){
    btns[i].classList.toggle('active',i===idx);
    tabs[i].classList.toggle('active',i===idx);
  }
}

// ===== SCROLL ANIMATIONS =====
var revealObs=new IntersectionObserver(function(entries){
  entries.forEach(function(e){
    if(e.isIntersecting){e.target.classList.add('visible');revealObs.unobserve(e.target)}
  });
},{threshold:0.1,rootMargin:'0px 0px -40px 0px'});
document.querySelectorAll('.reveal').forEach(function(el){revealObs.observe(el)});

var standingObs=new IntersectionObserver(function(entries){
  entries.forEach(function(e){
    if(e.isIntersecting){
      var item=e.target;
      var delay=parseFloat(item.dataset.delay)||0;
      var bw=parseFloat(item.dataset.bar)||0;
      setTimeout(function(){
        item.classList.add('visible');
        var bar=item.querySelector('.standing-bar');
        if(bar) setTimeout(function(){bar.style.width=bw+'%'},200);
      },delay*1000);
      standingObs.unobserve(item);
    }
  });
},{threshold:0.05,rootMargin:'0px 0px -20px 0px'});
document.querySelectorAll('.standing-item').forEach(function(el){standingObs.observe(el)});

// Score counter animation
var counterObs=new IntersectionObserver(function(entries){
  entries.forEach(function(e){
    if(e.isIntersecting){
      var el=e.target;
      var target=parseFloat(el.dataset.target);
      if(isNaN(target)) return;
      var duration=1200;
      var start=performance.now();
      var parent=el.closest('.standing-item');
      var baseDelay=parent?parseFloat(parent.dataset.delay||0)*1000+300:300;

      setTimeout(function(){
        function update(now){
          var t=Math.min((now-start)/duration,1);
          var eased=1-Math.pow(1-t,3);
          var val=target*eased;
          el.textContent=(val>=0?'+':'')+val.toFixed(1);
          if(t<1) requestAnimationFrame(update);
          else el.textContent=(target>=0?'+':'')+target.toFixed(1);
        }
        start=performance.now();
        requestAnimationFrame(update);
      },baseDelay);

      counterObs.unobserve(el);
    }
  });
},{threshold:0.1});
document.querySelectorAll('.standing-score[data-target]').forEach(function(el){counterObs.observe(el)});

// Stat counter animation
var statObs=new IntersectionObserver(function(entries){
  entries.forEach(function(e){
    if(e.isIntersecting){
      var el=e.target;
      var target=parseInt(el.dataset.count);
      if(isNaN(target)) return;
      var duration=1500;
      var start=performance.now();
      var hasComma=target>=1000;
      function update(now){
        var t=Math.min((now-start)/duration,1);
        var eased=1-Math.pow(1-t,3);
        var val=Math.round(target*eased);
        el.textContent=hasComma?val.toLocaleString():val;
        if(t<1) requestAnimationFrame(update);
        else el.textContent=hasComma?target.toLocaleString():target;
      }
      requestAnimationFrame(update);
      statObs.unobserve(el);
    }
  });
},{threshold:0.3});
document.querySelectorAll('[data-count]').forEach(function(el){statObs.observe(el)});
</script>
</body>
</html>
