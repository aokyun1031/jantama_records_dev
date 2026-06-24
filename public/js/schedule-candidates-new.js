(function() {
  var data = window.__scheduleCandidatesData || {};
  var candidates = (data.candidates || []).slice();
  var list = document.getElementById('sc-candidate-list');
  var btnAdd = document.getElementById('sc-btn-add');
  var form = document.getElementById('sc-form');

  function createRow(idx, item) {
    var row = document.createElement('div');
    row.className = 'sc-candidate-row';
    row.innerHTML =
      '<input type="date" name="played_date[]" class="sc-input-date" value="' + esc(item.played_date) + '">' +
      '<input type="text" name="played_time[]" class="sc-input-time" maxlength="5" placeholder="昼/夜/19:00" value="' + esc(item.played_time) + '">' +
      '<button type="button" class="sc-btn-quick" data-val="昼">昼</button>' +
      '<button type="button" class="sc-btn-quick" data-val="夜">夜</button>' +
      '<button type="button" class="sc-btn-remove" data-idx="' + idx + '" title="削除">&times;</button>';

    var timeInput = row.querySelector('.sc-input-time');
    row.querySelectorAll('.sc-btn-quick').forEach(function(btn) {
      btn.addEventListener('click', function() {
        timeInput.value = btn.getAttribute('data-val');
      });
    });
    row.querySelector('.sc-btn-remove').addEventListener('click', function() {
      syncFromDom();
      candidates.splice(idx, 1);
      render();
    });
    return row;
  }

  function render() {
    list.innerHTML = '';
    for (var i = 0; i < candidates.length; i++) {
      list.appendChild(createRow(i, candidates[i]));
    }
  }

  function syncFromDom() {
    var dateInputs = list.querySelectorAll('.sc-input-date');
    var timeInputs = list.querySelectorAll('.sc-input-time');
    for (var i = 0; i < dateInputs.length; i++) {
      candidates[i] = { played_date: dateInputs[i].value, played_time: timeInputs[i].value };
    }
  }

  btnAdd.addEventListener('click', function() {
    syncFromDom();
    candidates.push({ played_date: '', played_time: '' });
    render();
  });

  if (form) {
    form.addEventListener('submit', function() {
      syncFromDom();
    });
  }

  if (candidates.length === 0) candidates.push({ played_date: '', played_time: '' });
  render();

  function esc(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }
})();
