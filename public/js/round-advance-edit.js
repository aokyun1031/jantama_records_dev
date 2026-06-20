(function() {
  document.querySelectorAll('.round-advance-form').forEach(function(form) {
    var playerMode = parseInt(form.dataset.playerMode, 10) || 4;
    var totalPlayers = parseInt(form.dataset.totalPlayers, 10) || 0;
    var select = form.querySelector('.round-advance-select');
    if (!select) return;

    function rebuild() {
      var checked = form.querySelector('input[name="advance_mode"]:checked');
      var mode = checked ? checked.value : 'per_table';
      var oldVal = select.options.length ? parseInt(select.value, 10) : parseInt(form.dataset.current, 10);
      select.innerHTML = '';

      var values = [];
      if (mode === 'overall') {
        for (var v = 1; v <= totalPlayers - 1; v++) values.push(v);
      } else {
        for (var i = 1; i <= playerMode - 1; i++) values.push(i);
      }

      values.forEach(function(v) {
        var opt = document.createElement('option');
        opt.value = v;
        var label = '上位' + v + '名';
        if (mode === 'overall') {
          var elim = totalPlayers - v;
          if (elim > 0) label += '勝ち抜け（下位' + elim + '名敗退）';
        }
        opt.textContent = label;
        select.appendChild(opt);
      });

      select.value = values.indexOf(oldVal) !== -1 ? oldVal : (values[0] || 0);
    }

    form.querySelectorAll('input[name="advance_mode"]').forEach(function(radio) {
      radio.addEventListener('change', rebuild);
    });
    rebuild();
  });
})();
