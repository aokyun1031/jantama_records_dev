/* ======================
   landing-pop.js
   public/index.php（トップページ）専用：フェードイン・数字カウントアップ・
   カルーセル(無限ループ+prev/next/ドット)
   ====================== */
(function () {
  'use strict';

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Loader: window.load でフェードアウト + 時間経過でステータス更新
  var loader = document.getElementById('lp3-loader');
  if (loader) {
    var hidden = false;
    var statusEl = document.getElementById('lp3-loader-status');
    var statusTimers = [];
    if (statusEl) {
      statusTimers.push(setTimeout(function () { statusEl.textContent = 'データを取得しています…'; }, 2000));
      statusTimers.push(setTimeout(function () { statusEl.textContent = 'もう少しお待ちください…'; }, 4000));
    }
    var hideLoader = function () {
      if (hidden) return;
      hidden = true;
      statusTimers.forEach(function (t) { clearTimeout(t); });
      loader.classList.add('is-done');
      setTimeout(function () { if (loader.parentNode) loader.parentNode.removeChild(loader); }, 800);
    };
    if (document.readyState === 'complete') {
      hideLoader();
    } else {
      window.addEventListener('load', hideLoader, { once: true });
    }
    // 安全装置: 最大6秒で強制解除
    setTimeout(hideLoader, 6000);
  }

  // Scroll reveal
  if ('IntersectionObserver' in window && !reduce) {
    var revealObs = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add('lp3-in');
          revealObs.unobserve(e.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });
    document.querySelectorAll('.lp3-reveal').forEach(function (el) { revealObs.observe(el); });
  } else {
    document.querySelectorAll('.lp3-reveal').forEach(function (el) { el.classList.add('lp3-in'); });
  }

  // 背景アニメ: viewport外のバンドは animation-play-state を paused に
  if ('IntersectionObserver' in window) {
    var bandVisObs = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        e.target.classList.toggle('is-offscreen', !e.isIntersecting);
      });
    }, { rootMargin: '200px 0px 200px 0px' });
    document.querySelectorAll('.lp3-band').forEach(function (el) {
      el.classList.add('is-offscreen');
      bandVisObs.observe(el);
    });
  }

  // Counter animation
  function countUp(el) {
    var target = parseFloat(el.getAttribute('data-count'));
    if (isNaN(target)) return;
    var dur = 1400;
    var start = performance.now();
    function frame(now) {
      var p = Math.min((now - start) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.floor(target * eased).toString();
      if (p < 1) {
        requestAnimationFrame(frame);
      } else {
        el.textContent = target.toString();
      }
    }
    requestAnimationFrame(frame);
  }

  if ('IntersectionObserver' in window && !reduce) {
    var countObs = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          countUp(e.target);
          countObs.unobserve(e.target);
        }
      });
    }, { threshold: 0.35 });
    document.querySelectorAll('.lp3-count').forEach(function (el) {
      el.textContent = '0';
      countObs.observe(el);
    });
  }

  // 無限ループ対応の横スクローラ（ドラッグ / スワイプ / prev-next / ドット）
  function setupLoopScroller(scroller, opts) {
    opts = opts || {};
    var origCards = Array.prototype.slice.call(scroller.children);
    var n = origCards.length;
    if (n <= 1) return;

    // ---- 1. Clone cards before + after originals for infinite loop ----
    function makeClone(c) {
      var clone = c.cloneNode(true);
      clone.setAttribute('aria-hidden', 'true');
      clone.classList.add('lp3-clone');
      // フォーカス巡回に入らないように
      Array.prototype.forEach.call(clone.querySelectorAll('a,button,input,select,textarea'), function (el) {
        el.setAttribute('tabindex', '-1');
      });
      if (clone.matches && clone.matches('a,button')) clone.setAttribute('tabindex', '-1');
      return clone;
    }
    var beforeClones = origCards.map(makeClone);
    beforeClones.forEach(function (c) { scroller.insertBefore(c, origCards[0]); });
    var afterClones = origCards.map(makeClone);
    afterClones.forEach(function (c) { scroller.appendChild(c); });

    // ---- 2. Measure (after DOM changes) ----
    var origStart = origCards[0].offsetLeft;
    var setWidth = afterClones[0].offsetLeft - origStart;
    var cardStep = n >= 2 ? (origCards[1].offsetLeft - origCards[0].offsetLeft) : origCards[0].offsetWidth;

    // ---- 3. Start at first original ----
    scroller.scrollLeft = origStart;

    // ---- 4. Teleport (loop wrap) ----
    var teleporting = false;
    var dragState = null;
    function teleport(delta) {
      teleporting = true;
      scroller.scrollLeft += delta;
      if (dragState) dragState.startScroll += delta;
      requestAnimationFrame(function () { teleporting = false; });
    }
    function checkTeleport() {
      if (teleporting) return;
      var sl = scroller.scrollLeft;
      if (sl < origStart - 1) {
        teleport(setWidth);
      } else if (sl >= origStart + setWidth - 1) {
        teleport(-setWidth);
      }
    }
    scroller.addEventListener('scroll', checkTeleport, { passive: true });

    // ---- 5. Mouse drag scroll (coordinated with teleport) ----
    var suppressClick = false;
    var down = false, moved = false, startX = 0;
    scroller.style.cursor = 'grab';
    scroller.addEventListener('mousedown', function (e) {
      if (e.button !== 0) return;
      down = true; moved = false;
      startX = e.pageX;
      dragState = { startScroll: scroller.scrollLeft };
      scroller.style.cursor = 'grabbing';
      scroller.classList.add('is-dragging');
    });
    window.addEventListener('mousemove', function (e) {
      if (!down || !dragState) return;
      var dx = e.pageX - startX;
      if (Math.abs(dx) > 4) moved = true;
      if (moved) scroller.scrollLeft = dragState.startScroll - dx;
    });
    window.addEventListener('mouseup', function () {
      if (!down) return;
      down = false;
      scroller.style.cursor = 'grab';
      scroller.classList.remove('is-dragging');
      dragState = null;
      if (moved) {
        suppressClick = true;
        setTimeout(function () { suppressClick = false; }, 50);
      }
    });
    scroller.addEventListener('click', function (e) {
      if (suppressClick) { e.preventDefault(); e.stopPropagation(); }
    }, true);

    // ---- 6. Nav UI (prev / dots / next) ----
    var nav = document.createElement('div');
    nav.className = 'lp3-scroller-nav';

    var makeArrow = function (dir) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'lp3-nav-btn lp3-nav-' + dir;
      b.setAttribute('aria-label', dir === 'prev' ? '前のカードへ' : '次のカードへ');
      var path = dir === 'prev' ? 'M10 3 L5 8 L10 13' : 'M6 3 L11 8 L6 13';
      b.innerHTML = '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false">'
        + '<path d="' + path + '" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
      return b;
    };
    var prev = makeArrow('prev');
    var next = makeArrow('next');

    var dots = document.createElement('div');
    dots.className = 'lp3-dots' + (opts.compact ? ' is-compact' : '');
    dots.setAttribute('role', 'tablist');
    dots.setAttribute('aria-label', 'スライド位置');
    var btns = origCards.map(function (card, i) {
      var b = document.createElement('button');
      b.className = 'lp3-dot';
      b.type = 'button';
      b.setAttribute('role', 'tab');
      b.setAttribute('aria-label', n + '枚中 ' + (i + 1) + '枚目を表示');
      b.addEventListener('click', function () {
        scroller.scrollTo({ left: card.offsetLeft, behavior: 'smooth' });
      });
      dots.appendChild(b);
      return b;
    });
    nav.appendChild(prev);
    nav.appendChild(dots);
    nav.appendChild(next);
    scroller.parentNode.insertBefore(nav, scroller.nextSibling);

    // ---- 7. Active dot tracking (mod n to map clones→originals) ----
    function currentLogicalIdx() {
      var rect = scroller.getBoundingClientRect();
      var allCards = Array.prototype.slice.call(scroller.children);
      var bestIdx = 0;
      var bestDist = Infinity;
      for (var i = 0; i < allCards.length; i++) {
        var cardRect = allCards[i].getBoundingClientRect();
        var dist = Math.abs(cardRect.left - rect.left);
        if (dist < bestDist) {
          bestDist = dist;
          bestIdx = i;
        }
      }
      return bestIdx % n;
    }
    function updateActive() {
      var idx = currentLogicalIdx();
      btns.forEach(function (b, i) {
        var active = i === idx;
        b.classList.toggle('is-active', active);
        if (active) b.setAttribute('aria-current', 'true');
        else b.removeAttribute('aria-current');
      });
    }
    scroller.addEventListener('scroll', updateActive, { passive: true });
    window.addEventListener('resize', updateActive);
    updateActive();

    // ---- 8. prev/next: 常に有効、境界は teleport が自動でラップ ----
    prev.addEventListener('click', function () {
      scroller.scrollBy({ left: -cardStep, behavior: 'smooth' });
    });
    next.addEventListener('click', function () {
      scroller.scrollBy({ left: cardStep, behavior: 'smooth' });
    });
  }

  var liveScroller = document.querySelector('.lp3-live-scroller');
  if (liveScroller) setupLoopScroller(liveScroller);
  var rosterScroller = document.querySelector('.lp3-roster-scroller');
  if (rosterScroller) setupLoopScroller(rosterScroller, { compact: true });
})();
