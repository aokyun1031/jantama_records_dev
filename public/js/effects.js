/* ======================
   effects.js
   Particles, scroll animations, counters, finals effects
   ====================== */

// Scroll reveal
var revealObs=new IntersectionObserver(function(entries){
  entries.forEach(function(e){
    if(e.isIntersecting){e.target.classList.add('visible');revealObs.unobserve(e.target)}
  });
},{threshold:0.1,rootMargin:'0px 0px -40px 0px'});
document.querySelectorAll('.reveal').forEach(function(el){revealObs.observe(el)});

// Standing items staggered reveal
var standingObs=new IntersectionObserver(function(entries){
  entries.forEach(function(e){
    if(e.isIntersecting){
      var item=e.target;
      var delay=parseFloat(item.dataset.delay)||0;
      var bw=parseFloat(item.dataset.bar)||0;
      setTimeout(function(){
        item.classList.add('visible');
        var bar=item.querySelector('.standing-bar');
        if(bar) setTimeout(function(){bar.style.width=bw+'%'},200);
      },delay*1000);
      standingObs.unobserve(item);
    }
  });
},{threshold:0.05,rootMargin:'0px 0px -20px 0px'});
document.querySelectorAll('.standing-item').forEach(function(el){standingObs.observe(el)});

// Score counter animation
var counterObs=new IntersectionObserver(function(entries){
  entries.forEach(function(e){
    if(e.isIntersecting){
      var el=e.target;
      var target=parseFloat(el.dataset.target);
      if(isNaN(target)) return;
      var duration=1200;
      var start=performance.now();
      var parent=el.closest('.standing-item');
      var baseDelay=parent?parseFloat(parent.dataset.delay||0)*1000+300:300;

      setTimeout(function(){
        function update(now){
          var t=Math.min((now-start)/duration,1);
          var eased=1-Math.pow(1-t,3);
          var val=target*eased;
          el.textContent=(val>=0?'+':'')+val.toFixed(1);
          if(t<1) requestAnimationFrame(update);
          else el.textContent=(target>=0?'+':'')+target.toFixed(1);
        }
        start=performance.now();
        requestAnimationFrame(update);
      },baseDelay);

      counterObs.unobserve(el);
    }
  });
},{threshold:0.1});
document.querySelectorAll('.standing-score[data-target]').forEach(function(el){counterObs.observe(el)});

// Stat counter animation
var statObs=new IntersectionObserver(function(entries){
  entries.forEach(function(e){
    if(e.isIntersecting){
      var el=e.target;
      var target=parseInt(el.dataset.count);
      if(isNaN(target)) return;
      var duration=1500;
      var start=performance.now();
      var hasComma=target>=1000;
      function update(now){
        var t=Math.min((now-start)/duration,1);
        var eased=1-Math.pow(1-t,3);
        var val=Math.round(target*eased);
        el.textContent=hasComma?val.toLocaleString():val;
        if(t<1) requestAnimationFrame(update);
        else el.textContent=hasComma?target.toLocaleString():target;
      }
      requestAnimationFrame(update);
      statObs.unobserve(el);
    }
  });
},{threshold:0.3});
document.querySelectorAll('[data-count]').forEach(function(el){statObs.observe(el)});


// Reveal finalist cards on scroll into view
var finalsObserver=new IntersectionObserver(function(entries){
  entries.forEach(function(e){
    if(e.isIntersecting){
      // Reveal finalist cards with staggered timing
      var cards=document.querySelectorAll('.finalist-card');
      cards.forEach(function(card){
        var delay=parseFloat(card.dataset.delay)||1;
        setTimeout(function(){
          card.classList.add('revealed');
        },delay*1000);
      });

      finalsObserver.unobserve(e.target);
    }
  });
},{threshold:0.2});

var finalsEl=document.getElementById('finals-section');
if(finalsEl) finalsObserver.observe(finalsEl);


