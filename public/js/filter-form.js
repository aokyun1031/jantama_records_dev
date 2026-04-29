/*
 * 共通フィルタフォーム挙動。
 *
 * `.event-filter-form` 内のチェックボックス/テキスト入力/セレクトの変更を検出し、
 * 初期値からの差分件数を「適用」ボタンに反映する。送信は通常の form submit。
 *
 * 適用先:
 *   - tournaments.php（チップのみ）
 *   - tables.php（チップ + 検索 + ステータス）
 */
(function () {
  document.querySelectorAll('.event-filter-form').forEach(initForm);

  function initForm(form) {
    var submitBtn = form.querySelector('.event-filter-submit');
    var submitCount = form.querySelector('.event-filter-submit-count');
    var clearEl = form.querySelector('.event-filter-clear');
    var checkboxes = form.querySelectorAll('input[type="checkbox"]');
    var textInputs = form.querySelectorAll('input[type="search"], input[type="text"]');
    var selects = form.querySelectorAll('select');

    var initialChecks = [];
    var initialTexts = [];
    var initialSelects = [];
    checkboxes.forEach(function (cb) { initialChecks.push(cb.checked); });
    textInputs.forEach(function (el) { initialTexts.push(el.value); });
    selects.forEach(function (el) { initialSelects.push(el.value); });

    function pendingCount() {
      var pending = 0;
      checkboxes.forEach(function (cb, i) { if (cb.checked !== initialChecks[i]) pending++; });
      textInputs.forEach(function (el, i) { if (el.value !== initialTexts[i]) pending++; });
      selects.forEach(function (el, i) { if (el.value !== initialSelects[i]) pending++; });
      return pending;
    }

    function update() {
      var pending = pendingCount();
      form.classList.toggle('has-pending', pending > 0);
      if (submitBtn) {
        submitBtn.hidden = pending === 0;
        submitBtn.setAttribute('data-pending-count', String(pending));
      }
      if (submitCount) {
        submitCount.textContent = pending > 0 ? ' (' + pending + ')' : '';
      }
      if (clearEl) clearEl.hidden = pending > 0;
    }

    checkboxes.forEach(function (cb) {
      cb.addEventListener('change', function () {
        var chip = cb.closest('.event-chip');
        if (chip) chip.classList.toggle('is-selected', cb.checked);
        update();
      });
    });
    textInputs.forEach(function (el) { el.addEventListener('input', update); });
    selects.forEach(function (el) { el.addEventListener('change', update); });

    update();
  }
})();
