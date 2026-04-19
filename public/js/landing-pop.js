/* ======================
   landing-pop.js
   landing2.php 専用：フェードイン・数字カウントアップ
   ====================== */
(function () {
  'use strict';

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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
})();
