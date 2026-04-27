(function() {
  var data = window.__tableData;
  if (!data) return;
  var form = document.getElementById('table-form');
  if (!form) return;

  var gameCount = data.gameCount;
  var startPt = data.startPt;
  var returnPt = data.returnPt;
  var pMode = data.pMode;
  var pCount = data.pCount;
  var hasSub = data.hasSub;
  var isDev = data.isDev;
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
