
<div class="footer">
  <?php if (!empty($pageTurnstile) && isProduction()): ?>
  <div class="turnstile-footer" id="turnstile-container"></div>
  <?php endif; ?>
  <div class="footer-copyright">当サイトは非公式のファンサイトです。<br>使用しているゲーム画像の著作権は、Soul Games, Inc. および株式会社Yostarに帰属します。<br>&copy;2019 Soul Games, Inc. &copy;2019 Yostar, Inc. All Rights Reserved.</div>
</div>

<button type="button" class="back-to-top" id="back-to-top" aria-label="ページの先頭へ戻る" hidden>
  <svg viewBox="0 0 16 16" aria-hidden="true" focusable="false">
    <path d="M3 10 L8 5 L13 10" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
</button>

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
// Back-to-top: スクロール 400px 超で表示、クリックで先頭へ smooth scroll
(function(){
  var btn=document.getElementById('back-to-top');
  if(!btn) return;
  btn.hidden=false;
  var THRESHOLD=400;
  var ticking=false;
  function update(){
    ticking=false;
    var y=window.pageYOffset||document.documentElement.scrollTop;
    btn.classList.toggle('is-visible',y>THRESHOLD);
  }
  window.addEventListener('scroll',function(){
    if(!ticking){requestAnimationFrame(update);ticking=true;}
  },{passive:true});
  btn.addEventListener('click',function(){
    var reduce=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({top:0,behavior:reduce?'auto':'smooth'});
    var logo=document.querySelector('.site-logo');
    if(logo&&typeof logo.focus==='function')logo.focus({preventScroll:true});
  });
  update();
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
