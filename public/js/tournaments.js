(function() {
  var filterForm = document.getElementById('event-filter-form');
  if (!filterForm) return;

  var submitBtn = filterForm.querySelector('.event-filter-submit');
  var submitCount = filterForm.querySelector('.event-filter-submit-count');
  var clearEl = filterForm.querySelector('.event-filter-clear');
  var checkboxes = filterForm.querySelectorAll('input[name="event_types[]"]');
  var initial = {};
  checkboxes.forEach(function(cb) { initial[cb.value] = cb.checked; });

  function updatePending() {
    var pending = 0;
    checkboxes.forEach(function(cb) {
      if (cb.checked !== initial[cb.value]) pending++;
    });
    filterForm.classList.toggle('has-pending', pending > 0);
    if (submitBtn) {
      submitBtn.hidden = pending === 0;
      submitBtn.setAttribute('data-pending-count', String(pending));
    }
    if (submitCount) {
      submitCount.textContent = pending > 0 ? ' (' + pending + ')' : '';
    }
    if (clearEl) clearEl.hidden = pending > 0;
  }

  checkboxes.forEach(function(cb) {
    cb.addEventListener('change', function() {
      var chip = cb.closest('.event-chip');
      if (chip) chip.classList.toggle('is-selected', cb.checked);
      updatePending();
    });
  });
  updatePending();
})();
