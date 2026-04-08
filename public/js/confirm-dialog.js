(function() {
  document.querySelectorAll('[data-confirm]').forEach(function(el) {
    var event = el.tagName === 'FORM' ? 'submit' : 'click';
    el.addEventListener(event, function(e) {
      if (!confirm(el.dataset.confirm)) {
        e.preventDefault();
      }
    });
  });
})();
