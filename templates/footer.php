
<div class="footer">
  <?php if (!empty($pageTurnstile) && isProduction()): ?>
  <div class="turnstile-footer" id="turnstile-container"></div>
  <?php endif; ?>
  <div class="footer-copyright">当サイトは非公式のファンサイトです。<br>使用しているゲーム画像の著作権は、Soul Games, Inc. および株式会社Yostarに帰属します。<br>&copy;2019 Soul Games, Inc. &copy;2019 Yostar, Inc. All Rights Reserved.</div>
</div>

</div><!-- /.main -->

<?php // $pageInlineScript はエスケープなしで出力される。json_encode には必ず JSON_HEX_TAG を使用すること。 ?>
<?php if (!empty($pageInlineScript)): ?>
<script nonce="<?= cspNonce() ?>">
<?= $pageInlineScript ?>
</script>
<?php endif; ?>
<?php foreach (($pageScripts ?? []) as $script): ?>
<script src="<?= asset($script) ?>" nonce="<?= cspNonce() ?>" defer></script>
<?php endforeach; ?>
<script nonce="<?= cspNonce() ?>">
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
<script src="<?= asset('js/confirm-dialog.js') ?>" nonce="<?= cspNonce() ?>"></script>
<?php if (!empty($pageTurnstile) && isProduction()): ?>
<script nonce="<?= cspNonce() ?>">
(function(){
  document.documentElement.classList.add('ts-pending');
  var token='';
  function syncToken(){
    document.querySelectorAll('form').forEach(function(f){
      var inp=f.querySelector('input[name="cf-turnstile-response"]');
      if(!inp){inp=document.createElement('input');inp.type='hidden';inp.name='cf-turnstile-response';f.appendChild(inp);}
      inp.value=token;
    });
  }
  document.addEventListener('submit',function(e){
    if(document.documentElement.classList.contains('ts-pending'))e.preventDefault();
  },true);
  window.onTurnstileLoad=function(){
    turnstile.render('#turnstile-container',{
      sitekey:<?= json_encode(turnstileSiteKey(), JSON_HEX_TAG) ?>,
      callback:function(t){token=t;syncToken();document.documentElement.classList.remove('ts-pending');},
      'expired-callback':function(){token='';syncToken();document.documentElement.classList.add('ts-pending');},
      'error-callback':function(){return true;}
    });
  };
})();
</script>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=onTurnstileLoad" async defer></script>
<?php endif; ?>
<script defer src="https://static.cloudflareinsights.com/beacon.min.js" data-cf-beacon='{"token": "1fcd94888ded45fcb070b401177e91fc"}'></script>
</body>
</html>
