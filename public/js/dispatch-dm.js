(function () {
  var content = document.querySelector('[data-csrf-token]');
  if (!content) return;
  var csrfToken = content.dataset.csrfToken || '';
  var pending = content.dataset.dmDispatchPending === '1';

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

  function dispatch(tournamentId, playerId, btn) {
    var origText = btn ? btn.textContent : '';
    if (btn) {
      btn.disabled = true;
      btn.textContent = '送信中…';
    }

    var body = new URLSearchParams();
    body.append('csrf_token', csrfToken);
    body.append('tournament_id', String(tournamentId));
    if (playerId) body.append('player_id', String(playerId));

    return fetch('/dispatch_dm', {
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
        // 状態反映のためページリロード
        setTimeout(function () { window.location.reload(); }, 1500);
      })
      .catch(function () {
        showToast('通信エラー', true);
        if (btn) { btn.disabled = false; btn.textContent = origText; }
      });
  }

  // 個別ボタン / 全員ボタン共通ハンドラ
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-dispatch-tournament]');
    if (!btn) return;
    e.preventDefault();
    var tid = parseInt(btn.dataset.dispatchTournament, 10);
    var pid = btn.dataset.dispatchPlayer ? parseInt(btn.dataset.dispatchPlayer, 10) : 0;
    if (!tid) return;
    if (!confirm(pid ? 'この選手にDMを送信しますか？' : '未送信選手 全員にDMを送信しますか？')) return;
    dispatch(tid, pid, btn);
  });

  // 大会作成直後 → 自動発火（confirm スキップ）
  if (pending) {
    var tid = parseInt(content.dataset.tournamentId, 10);
    if (tid) {
      dispatch(tid, 0, null);
    }
  }
})();
