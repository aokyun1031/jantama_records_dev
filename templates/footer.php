
<div class="footer">
  <!-- <div class="footer-site">&copy; 2026 最強位戦</div> -->
  <div class="footer-copyright">当サイトは非公式のファンサイトです。使用しているゲーム画像の著作権は、Soul Games, Inc. および株式会社Yostarに帰属します。<br>&copy;2019 Soul Games, Inc. &copy;2019 Yostar, Inc. All Rights Reserved.</div>
</div>

</div><!-- /.main -->

<?php // $pageInlineScript はエスケープなしで出力される。json_encode には必ず JSON_HEX_TAG を使用すること。 ?>
<?php if (!empty($pageInlineScript)): ?>
<script>
<?= $pageInlineScript ?>
</script>
<?php endif; ?>
<?php foreach (($pageScripts ?? []) as $script): ?>
<script src="<?= asset($script) ?>" defer></script>
<?php endforeach; ?>
<script>
(function(){
  var btn=document.getElementById('hamburger');
  var panel=document.getElementById('nav-panel');
  var overlay=document.getElementById('nav-overlay');
  if(!btn||!panel||!overlay) return;
  function toggle(){
    var open=panel.classList.toggle('open');
    btn.classList.toggle('open',open);
    overlay.classList.toggle('open',open);
  }
  btn.addEventListener('click',toggle);
  overlay.addEventListener('click',toggle);
})();
</script>
<script src="<?= asset('js/theme-toggle.js') ?>" defer></script>
</body>
</html>
