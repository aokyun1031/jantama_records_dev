/* ======================
   theme-toggle.js
   Toggle dark/light theme, persist to localStorage
   ====================== */

(function(){
  var STORAGE_KEY='saikyo-theme';
  var toggle=document.getElementById('theme-toggle');
  var sheet=document.getElementById('theme-dark');

  function applyTheme(isDark){
    sheet.disabled=!isDark;
    toggle.classList.toggle('light',!isDark);
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
