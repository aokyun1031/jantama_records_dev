/* ======================
   sakura.js
   index.php 専用：桜の花びらを定期的に生成して container に追加
   落下後は DOM から除去してメモリを圧迫しない
   参考: web-dev.tech/front-end/javascript/cherry-blossom-petal-falling-effect/
   ====================== */
(function () {
  'use strict';

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduce) return;

  var container = document.querySelector('.sakura-container');
  if (!container) return;

  // モバイルは端末性能を考慮して生成間隔を伸ばし、花びら数を抑える
  var isMobile = window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
  var isSmall  = window.matchMedia && window.matchMedia('(max-width: 480px)').matches;

  var MIN_SIZE = 10;
  var MAX_SIZE = isSmall ? 16 : (isMobile ? 18 : 20);
  var SPAWN_INTERVAL_MS = isSmall ? 700 : (isMobile ? 550 : 350);
  var MIN_DURATION_S = isMobile ? 9 : 8;
  var MAX_DURATION_S = isMobile ? 15 : 14;
  var INITIAL_COUNT = isSmall ? 3 : (isMobile ? 4 : 6);

  var COLORS = ['#ffc0cb', '#ffb8d6', '#ffd4e5', '#ffa6c8', '#ffe0ec'];

  function rand(min, max) { return Math.random() * (max - min) + min; }

  function createPetal() {
    if (document.hidden) return;

    var size = rand(MIN_SIZE, MAX_SIZE);
    var duration = rand(MIN_DURATION_S, MAX_DURATION_S);
    var delay = rand(0, 2);
    // モバイルは画面幅が狭いので横揺れも控えめに
    var swayMax = isSmall ? 60 : (isMobile ? 90 : 140);
    var sway = rand(30, swayMax) * (Math.random() < 0.5 ? -1 : 1);
    var opacity = rand(0.55, 0.95);
    var color = COLORS[Math.floor(Math.random() * COLORS.length)];

    var petal = document.createElement('span');
    petal.className = 'sakura-petal';
    petal.style.width = size + 'px';
    petal.style.height = size + 'px';
    petal.style.left = rand(-5, 100) + 'vw';
    petal.style.animationDuration = duration + 's';
    petal.style.animationDelay = delay + 's';
    petal.style.setProperty('--sway', sway + 'px');
    petal.style.setProperty('--petal-op', opacity.toString());
    petal.style.setProperty('--petal-color', color);

    container.appendChild(petal);

    // アニメ終了後に DOM から除去（duration + delay + 余裕 1s）
    setTimeout(function () {
      if (petal.parentNode) petal.parentNode.removeChild(petal);
    }, (duration + delay + 1) * 1000);
  }

  // 初期に少し撒いておく（一斉スタート感を回避）
  for (var i = 0; i < INITIAL_COUNT; i++) {
    setTimeout(createPetal, i * 200);
  }

  var spawnTimer = setInterval(createPetal, SPAWN_INTERVAL_MS);

  // タブ非表示中は生成停止（浪費回避）
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      clearInterval(spawnTimer);
      spawnTimer = null;
    } else if (!spawnTimer) {
      spawnTimer = setInterval(createPetal, SPAWN_INTERVAL_MS);
    }
  });
})();
