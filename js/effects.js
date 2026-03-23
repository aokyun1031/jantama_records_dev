/* ======================
   effects.js
   Particles, scroll animations, counters, finals effects
   ====================== */

// Background particles — initial build handled by theme-toggle.js
// (particles are rebuilt on every theme switch with matching colors)

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
  var isMobile=window.innerWidth<=768;
  for(var i=0,max=isMobile?0:10;i<max;i++){
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
  var isMobile=window.innerWidth<=768;
  for(var i=0,max=isMobile?0:6;i<max;i++){
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
      var confettiCount=window.innerWidth<=768?0:25;
      for(var i=0;i<confettiCount;i++){
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

// ===== MAHJONG DECORATIONS =====
(function(){
  // All mahjong tile characters
  var tiles=[
    '\u{1F000}','\u{1F001}','\u{1F002}','\u{1F003}','\u{1F004}','\u{1F005}','\u{1F006}',
    '\u{1F007}','\u{1F008}','\u{1F009}','\u{1F00A}','\u{1F00B}','\u{1F00C}','\u{1F00D}',
    '\u{1F00E}','\u{1F00F}','\u{1F010}','\u{1F011}','\u{1F012}','\u{1F013}','\u{1F014}',
    '\u{1F015}','\u{1F016}','\u{1F017}','\u{1F018}','\u{1F019}','\u{1F01A}','\u{1F01B}',
    '\u{1F01C}','\u{1F01D}','\u{1F01E}','\u{1F01F}','\u{1F020}','\u{1F021}'
  ];
  function pick(){return tiles[Math.floor(Math.random()*tiles.length)]}

  // Section background scattered tiles
  function fillSectionTiles(id,count){
    var el=document.getElementById(id);
    if(!el) return;
    for(var i=0;i<count;i++){
      var s=document.createElement('span');
      s.textContent=pick();
      s.style.cssText='top:'+Math.random()*90+'%;left:'+Math.random()*90+'%;'+
        'transform:rotate('+(Math.random()*360)+'deg);'+
        'animation-delay:'+(Math.random()*20)+'s;'+
        'animation-duration:'+(15+Math.random()*20)+'s';
      el.appendChild(s);
    }
  }
  var isMobileDeco=window.innerWidth<=768;
  fillSectionTiles('section-tiles-rounds',isMobileDeco?0:8);
  fillSectionTiles('section-tiles-standings',isMobileDeco?0:8);

  // Records tile frame
  var frame=document.getElementById('records-tile-frame');
  if(frame){
    var positions=[
      {t:'8%',l:'5%',r:'-20deg'},{t:'15%',l:'88%',r:'15deg'},
      {t:'70%',l:'3%',r:'25deg'},{t:'75%',l:'92%',r:'-12deg'},
      {t:'40%',l:'2%',r:'10deg'},{t:'45%',l:'93%',r:'-30deg'},
      {t:'5%',l:'45%',r:'5deg'},{t:'88%',l:'50%',r:'-8deg'},
      {t:'25%',l:'8%',r:'-35deg'},{t:'60%',l:'90%',r:'20deg'}
    ];
    for(var i=0;i<positions.length;i++){
      var s=document.createElement('span');
      s.textContent=pick();
      var p=positions[i];
      s.style.cssText='top:'+p.t+';left:'+p.l+';transform:rotate('+p.r+')';
      frame.appendChild(s);
    }
  }

  // Page-level floating tile scatter
  var scatter=document.getElementById('tile-scatter');
  if(scatter&&!isMobileDeco){
    for(var i=0;i<6;i++){
      var s=document.createElement('span');
      s.textContent=pick();
      s.style.cssText='left:'+Math.random()*95+'%;top:'+(100+Math.random()*20)+'%;'+
        'animation-delay:'+(Math.random()*30)+'s;'+
        'animation-duration:'+(25+Math.random()*20)+'s;'+
        'font-size:'+(1.2+Math.random()*1.2)+'rem';
      scatter.appendChild(s);
    }
  }
})();

// Champion sparkles effect
(function(){
  var container=document.getElementById('champion-sparkles');
  if(!container) return;
  for(var i=0;i<50;i++){
    var sparkle=document.createElement('div');
    sparkle.className='champion-sparkle';
    sparkle.style.cssText='left:'+Math.random()*100+'%;top:'+Math.random()*100+'%;'+
      'animation-delay:'+(Math.random()*3)+'s;'+
      'animation-duration:'+(2+Math.random()*2)+'s;'+
      'width:'+(2+Math.random()*3)+'px;height:'+(2+Math.random()*3)+'px';
    container.appendChild(sparkle);
  }
})();
