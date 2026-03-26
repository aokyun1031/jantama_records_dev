/* ======================
   theme-toggle.js
   Toggle dark/light theme, persist to localStorage,
   rebuild particles with matching colors
   ====================== */

(function(){
  var STORAGE_KEY='saikyo-theme';
  var toggle=document.getElementById('theme-toggle');
  var sheet=document.getElementById('theme-dark');

  // Particle color palettes
  var darkColors=['#d4a84c','#b8943c','#e8c878','#a08040','#c8a858','#dbb860'];
  var lightColors=['#d0b8f0','#f0b8c8','#b8e8d8','#f0d8a0','#a8c8f0','#f0a8b8'];

  function rebuildParticles(isDark){
    var el=document.getElementById('particles');
    el.innerHTML='';
    var c=isDark?darkColors:lightColors;
    var count=window.innerWidth<=768?8:14;
    for(var i=0;i<count;i++){
      var d=document.createElement('div');
      d.className='particle';
      var sz=6+Math.random()*14;
      d.style.cssText='width:'+sz+'px;height:'+sz+'px;left:'+Math.random()*100+'%;background:'+c[i%c.length]+';animation-delay:'+(Math.random()*20)+'s;animation-duration:'+(12+Math.random()*16)+'s;bottom:-'+sz+'px';
      el.appendChild(d);
    }
  }

  function applyTheme(isDark){
    sheet.disabled=!isDark;
    toggle.classList.toggle('light',!isDark);
    rebuildParticles(isDark);
  }

  // Load saved preference (default: light)
  var saved=localStorage.getItem(STORAGE_KEY);
  var isDark=saved===null?false:saved==='dark';
  applyTheme(isDark);

  // Click handler
  toggle.addEventListener('click',function(){
    isDark=!isDark;
    applyTheme(isDark);
    localStorage.setItem(STORAGE_KEY,isDark?'dark':'light');
  });
})();
