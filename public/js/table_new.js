(function() {
  var data = window.__tableNewData || {};
  var players = data.players || [];
  var playerMode = data.playerMode || 4;
  var nextRound = data.nextRound || 1;
  var prevGroups = data.prevGroups || [];
  var standings = data.standings || {};
  var tables = [];
  var dragSrc = null;

  var advancePreview = document.getElementById('advance-preview');
  var selectAdvance = document.getElementById('select-advance');
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
    var oldVal = parseInt(selectAdvance.value);
    selectAdvance.innerHTML = '';

    var values = [];
    if (mode === 'overall') {
      for (var k = 1; k <= 4; k++) {
        var v = k * playerMode;
        if (v > totalPlayers - 1) break;
        values.push(v);
      }
    } else {
      for (var i = 1; i <= playerMode - 1; i++) values.push(i);
    }

    values.forEach(function(v) {
      var opt = document.createElement('option');
      opt.value = v;
      opt.textContent = '上位' + v + '名';
      selectAdvance.appendChild(opt);
    });

    var defaultVal;
    if (mode === 'overall') {
      defaultVal = values.length >= 2 ? values[1] : (values[0] || 0);
    } else {
      defaultVal = 2;
    }
    selectAdvance.value = values.indexOf(oldVal) !== -1 ? oldVal : defaultVal;
  }

  function updateAdvancePreview() {
    if (!advancePreview) return;
    var tableCount = tables.length;
    if (tableCount === 0) {
      advancePreview.style.display = 'none';
      return;
    }
    var advance = selectAdvance ? parseInt(selectAdvance.value) : 2;
    var mode = getAdvanceMode();
    var steps = [];
    var hasWarn = false;
    if (mode === 'overall') {
      // 全体モード: 次ラウンド以降の設定は未定なので、このラウンドの結果のみ表示
      var cur = advance;
      var tc = Math.ceil(cur / playerMode);
      var subs = tc * playerMode - cur;
      if (subs > 0) hasWarn = true;
      steps.push({ players: cur, tables: tc, subs: subs, final: tc === 1 });
    } else {
      // 各卓モード: 同じ配分で決勝まで追う
      var cur = tableCount * advance;
      for (var round = 0; round < 10; round++) {
        var tc = Math.ceil(cur / playerMode);
        var subs = tc * playerMode - cur;
        var isFinal = tc === 1;
        steps.push({ players: cur, tables: tc, subs: subs, final: isFinal });
        if (subs > 0) hasWarn = true;
        if (isFinal) break;
        cur = tc * advance;
      }
    }
    // 描画
    var html = '';
    for (var i = 0; i < steps.length; i++) {
      var s = steps[i];
      var label = s.final ? '決勝' : (nextRound + i) + '回戦後';
      html += '<div class="tn-advance-step">';
      if (i > 0) html += '<span class="tn-advance-arrow">→</span>';
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
    var rankModeEl = document.querySelector('input[name="ranking_mode"]:checked');
    var rankMode = rankModeEl ? rankModeEl.value : 'none';
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
    document.getElementById('generate-summary').style.display = '';

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
