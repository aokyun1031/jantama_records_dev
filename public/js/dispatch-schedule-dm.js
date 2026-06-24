(function () {
  var content = document.querySelector('[data-csrf-token]');
  if (!content) return;
  var csrfToken = content.dataset.csrfToken || '';

  var toast = null;
  function showToast(msg, isError) {
    if (!toast) {
      toast = document.createElement('div');
      toast.className = 'copy-toast';
      document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.style.background = isError ? 'var(--danger)' : '';
    toast.classList.add('visible');
    clearTimeout(toast._t);
    toast._t = setTimeout(function () {
      toast.classList.remove('visible');
    }, 2400);
  }

  function dispatch(tournamentId, roundNumber, playerId, btn) {
    var origText = btn ? btn.textContent : '';
    if (btn) {
      btn.disabled = true;
      btn.textContent = '送信中…';
    }

    var body = new URLSearchParams();
    body.append('csrf_token', csrfToken);
    body.append('tournament_id', String(tournamentId));
    body.append('round_number', String(roundNumber));
    if (playerId) body.append('player_id', String(playerId));

    return fetch('/dispatch_schedule_dm', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
      credentials: 'same-origin',
    })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
      .then(function (data) {
        if (!data || !data.ok) {
          showToast('送信失敗: ' + (data && data.error ? data.error : '不明エラー'), true);
          if (btn) { btn.disabled = false; btn.textContent = origText; }
          return;
        }
        var msg = 'DM送信完了: 成功' + (data.sent || 0) + '名';
        if (data.failed) msg += ' / 失敗' + data.failed + '名';
        if (data.no_discord_id) msg += ' / 未登録' + data.no_discord_id + '名';
        showToast(msg, (data.failed || 0) > 0);
        setTimeout(function () { window.location.reload(); }, 2600);
      })
      .catch(function () {
        showToast('通信エラー', true);
        if (btn) { btn.disabled = false; btn.textContent = origText; }
      });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-dispatch-schedule-tournament]');
    if (!btn) return;
    e.preventDefault();
    var tid = parseInt(btn.dataset.dispatchScheduleTournament, 10);
    var rnd = parseInt(btn.dataset.dispatchScheduleRound, 10);
    var pid = btn.dataset.dispatchSchedulePlayer ? parseInt(btn.dataset.dispatchSchedulePlayer, 10) : 0;
    if (!tid || !rnd) return;
    if (!confirm(pid ? 'この選手にDMを送信しますか？' : '未回答選手を含む全選手にDMを送信しますか？')) return;
    dispatch(tid, rnd, pid, btn);
  });

  // 候補日程保存直後 → 自動発火（confirm スキップ、全選手対象）
  if (content.dataset.dispatchSchedulePending === '1') {
    var pendingTid = parseInt(content.dataset.tournamentId, 10);
    var pendingRnd = parseInt(content.dataset.roundNumber, 10);
    if (pendingTid && pendingRnd) {
      dispatch(pendingTid, pendingRnd, 0, null);
    }
  }
})();
