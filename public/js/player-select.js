(function() {
  var grid = document.getElementById('player-grid');
  if (!grid) return;
  var countEl = document.getElementById('selected-count');
  var btnAll = document.getElementById('btn-select-all');
  var btnNone = document.getElementById('btn-deselect-all');
  var hasLocked = grid.querySelector('.player-select-option.locked') !== null;
  var selector = hasLocked
    ? '.player-select-option:not(.locked) input[type="checkbox"]'
    : '.player-select-option input[type="checkbox"]';

  function updateCount() {
    var checked = grid.querySelectorAll('input[type="checkbox"]:checked').length;
    countEl.textContent = checked + '人選択中';
  }

  grid.addEventListener('change', updateCount);
  updateCount();

  btnAll.addEventListener('click', function() {
    var options = grid.querySelectorAll(selector);
    for (var i = 0; i < options.length; i++) options[i].checked = true;
    updateCount();
  });

  btnNone.addEventListener('click', function() {
    var options = grid.querySelectorAll(selector);
    for (var i = 0; i < options.length; i++) options[i].checked = false;
    updateCount();
  });
})();
