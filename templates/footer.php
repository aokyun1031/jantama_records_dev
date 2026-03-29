
<div class="footer">
  <!-- <div class="footer-site">&copy; 2026 最強位戦</div> -->
  <div class="footer-copyright">当サイトは非公式のファンサイトです。使用しているゲーム画像の著作権は、Soul Games, Inc. および株式会社Yostarに帰属します。<br>&copy;2019 Soul Games, Inc. &copy;2019 Yostar, Inc. All Rights Reserved.</div>
</div>

</div><!-- /.main -->

<?php if (!empty($pageInlineScript)): ?>
<script>
<?= $pageInlineScript ?>
</script>
<?php endif; ?>
<?php foreach (($pageScripts ?? []) as $script): ?>
<script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>
<script src="js/theme-toggle.js"></script>
</body>
</html>
