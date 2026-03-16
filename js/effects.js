/* ======================
   effects.js
   Particles, scroll animations, counters, finals effects
   ====================== */

// Background particles
(function(){
  var c=['#d0b8f0','#f0b8c8','#b8e8d8','#f0d8a0','#a8c8f0','#f0a8b8'];
  var el=document.getElementById('particles');
  for(var i=0;i<18;i++){
    var d=document.createElement('div');
    d.className='particle';
    var sz=6+Math.random()*14;
    d.style.cssText='width:'+sz+'px;height:'+sz+'px;left:'+Math.random()*100+'%;background:'+c[i%c.length]+';animation-delay:'+(Math.random()*20)+'s;animation-duration:'+(12+Math.random()*16)+'s;bottom:-'+sz+'px';
    el.appendChild(d);
  }
})();

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

// ===== FINALS EFFECTS =====

// Ember particles
(function(){
  var container=document.getElementById('finals-embers');
  if(!container) return;
  for(var i=0;i<20;i++){
    var e=document.createElement('div');
    e.className='ember';
    var drift=(Math.random()-0.5)*80;
    e.style.cssText='left:'+Math.random()*100+'%;bottom:-10px;'+
      '--drift:'+drift+'px;'+
      'animation-delay:'+(Math.random()*6)+'s;'+
      'animation-duration:'+(3+Math.random()*4)+'s;'+
      'width:'+(2+Math.random()*3)+'px;height:'+(2+Math.random()*3)+'px';
    container.appendChild(e);
  }
})();

// Energy lines radiating from center
(function(){
  var container=document.getElementById('energy-lines');
  if(!container) return;
  for(var i=0;i<12;i++){
    var l=document.createElement('div');
    l.className='energy-line';
    l.style.cssText='transform:rotate('+(i*30)+'deg);animation-delay:'+(i*0.4)+'s';
    container.appendChild(l);
  }
})();

// Confetti burst on scroll into view
var finalsObserver=new IntersectionObserver(function(entries){
  entries.forEach(function(e){
    if(e.isIntersecting){
      var container=document.getElementById('confetti-container');
      var colors=['#ffd700','#ffec80','#e88cad','#9b8ce8','#5cc8b0','#ff6b6b','#4ecdc4','#ffe66d'];
      for(var i=0;i<60;i++){
        (function(idx){
          setTimeout(function(){
            var c=document.createElement('div');
            c.className='confetti';
            c.style.cssText='left:'+((Math.random()*80)+10)+'%;top:'+(Math.random()*30)+'%;'+
              'width:'+(4+Math.random()*6)+'px;height:'+(6+Math.random()*10)+'px;'+
              'background:'+colors[idx%colors.length]+';'+
              'border-radius:'+(Math.random()>0.5?'50%':'2px')+';'+
              'animation-duration:'+(2+Math.random()*2)+'s;'+
              'animation-delay:0s';
            container.appendChild(c);
            setTimeout(function(){c.remove()},4000);
          },idx*40);
        })(i);
      }

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
