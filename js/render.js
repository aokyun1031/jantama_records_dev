/* ======================
   render.js
   DOM rendering — standings, tables, results, tabs
   ====================== */

// Helpers
function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML}
function cls(s){return s>=0?'plus':'minus'}
function fmt(s){return (s>=0?'+':'')+s.toFixed(1)}
function barW(s){return Math.min(Math.abs(s)/MAX_BAR*100,100)}

// Render standings list
(function(){
  var box=document.getElementById('standings');
  var html='';
  var shownDivider=false;
  for(var i=0;i<standings.length;i++){
    var p=standings[i];
    if(p.elim===0 && p.total<0 && !shownDivider){
      shownDivider=true;
      html+='<div class="standing-divider">\u00B1 0</div>';
    }
    var c=cls(p.total);
    var bw=barW(p.total);
    var detail=p.r.map(function(v){return (v>=0?'+':'')+v.toFixed(1)}).join(' \u2192 ');
    if(p.elim===1) detail+=' \u2192 1回戦敗退';
    else if(p.elim===2) detail+=' \u2192 2回戦敗退';
    else if(p.elim===3) detail+=' \u2192 3回戦敗退';
    var rankHtml=p.rank<=3?'<span class="medal">'+MEDALS[p.rank-1]+'</span>':''+p.rank;
    var badgeHtml='';
    if(p.pending) badgeHtml='<span class="badge-pending">3回戦未</span>';
    else if(p.elim===1) badgeHtml='<span class="badge-elim">1回戦敗退</span>';
    else if(p.elim===2) badgeHtml='<span class="badge-elim">2回戦敗退</span>';
    else if(p.elim===3) badgeHtml='<span class="badge-elim">3回戦敗退</span>';

    var topCls=p.rank<=3?' top-'+p.rank:'';
    var elimCls=p.elim>0?' eliminated':'';
    html+='<div class="standing-item'+topCls+elimCls+'" data-delay="'+(i*0.08)+'" data-bar="'+bw+'">'
      +'<div class="standing-bar '+c+'"></div>'
      +'<div class="standing-rank">'+rankHtml+'</div>'
      +'<div class="standing-info">'
        +'<div class="standing-name">'+esc(p.name)+' '+badgeHtml+'</div>'
        +'<div class="standing-detail">'+detail+'</div>'
      +'</div>'
      +'<div class="standing-score '+c+'" data-target="'+p.total+'">'+fmt(p.total)+'</div>'
    +'</div>';
  }
  box.innerHTML=html;
})();

// Table cards
function renderTables(tables,opts){
  var html='<div class="table-grid">';
  for(var i=0;i<tables.length;i++){
    var t=tables[i];
    var cardCls='table-card';
    var schedHtml='';
    if(opts&&opts.showDone){
      cardCls+= t.done?' table-done':' table-pending';
      schedHtml=t.done?'\u2713 完了':'\u23F3 未対戦';
    } else if(t.sched){
      schedHtml=t.sched;
    }
    html+='<div class="'+cardCls+'"><div class="table-card-head">'
      +'<span class="table-card-name">'+esc(t.name)+'</span>'
      +(schedHtml?'<span class="table-card-sched">'+schedHtml+'</span>':'')
      +'</div><ul class="table-card-players">';
    for(var j=0;j<t.players.length;j++){
      html+='<li>'+esc(t.players[j])+'</li>';
    }
    html+='</ul></div>';
  }
  return html+'</div>';
}

// Result rows
function renderResults(results,startRank){
  var html='';
  for(var i=0;i<results.length;i++){
    var r=results[i];
    html+='<div class="result-row">'
      +'<div class="result-rank">'+(startRank+i)+'</div>'
      +'<div class="result-name">'+esc(r[0])+'</div>'
      +'<div class="result-score '+cls(r[1])+'">'+fmt(r[1])+'</div>'
    +'</div>';
  }
  return html;
}

// Eliminated rows
function renderElim(results,startRank){
  var html='<div class="elim-section"><div class="elim-title">\u25BC 敗退</div>';
  for(var i=0;i<results.length;i++){
    var r=results[i];
    html+='<div class="result-row elim-row">'
      +'<div class="result-rank">'+(startRank+i)+'</div>'
      +'<div class="result-name">'+esc(r[0])+'</div>'
      +'<div class="result-score '+cls(r[1])+'">'+fmt(r[1])+'</div>'
    +'</div>';
  }
  return html+'</div>';
}

// Score lookup map
function buildScoreMap(above,below){
  var map={};
  for(var i=0;i<above.length;i++) map[above[i][0]]=above[i][1];
  for(var i=0;i<below.length;i++) map[below[i][0]]=below[i][1];
  return map;
}

// Per-table detail results
function renderTableDetails(tables,scoreMap,opts){
  var html='<div class="table-details">';
  var posLabels=['1st','2nd','3rd','4th'];
  for(var i=0;i<tables.length;i++){
    var t=tables[i];
    var isPending=opts&&opts.showDone&&!t.done;
    html+='<div class="table-detail-block">';
    html+='<div class="table-detail-head">'
      +'<span class="table-detail-name">'+esc(t.name)+' 結果</span>';
    if(isPending){
      html+='<span class="table-detail-badge" style="background:rgba(255,152,0,0.1);color:#e65100;border:1px solid rgba(255,152,0,0.3)">\u23F3 未対戦</span>';
    }
    html+='</div>';
    if(isPending){
      html+='<div class="table-detail-pending">'+t.players.map(function(p){return esc(p)}).join('\u30FB')+' の対戦待ち</div>';
    } else {
      var sorted=t.players.map(function(p){return {name:p,score:scoreMap[p]||0}});
      sorted.sort(function(a,b){return b.score-a.score});
      for(var j=0;j<sorted.length;j++){
        var p=sorted[j];
        var posCls=j===0?'pos-1':'';
        var trophy=j===0?'\u{1F451} ':'';
        html+='<div class="table-detail-row">'
          +'<div class="table-detail-pos '+posCls+'">'+posLabels[j]+'</div>'
          +'<div class="table-detail-player">'+trophy+esc(p.name)+'</div>'
          +'<div class="table-detail-score '+cls(p.score)+'">'+fmt(p.score)+'</div>'
        +'</div>';
      }
    }
    html+='</div>';
  }
  return html+'</div>';
}

// Build score maps
var r1Scores=buildScoreMap(r1Above,r1Below);
var r2Scores=buildScoreMap(r2Above,r2Below);
var r3Scores=buildScoreMap(r3Above,r3Below);

// Populate round tabs
document.getElementById('tab0').innerHTML=
  renderTables(r1Tables,null)
  +renderTableDetails(r1Tables,r1Scores,null)
  +'<div class="results-list"><div class="results-sub">全体順位</div>'+renderResults(r1Above,1)+'</div>'
  +renderElim(r1Below,17);

document.getElementById('tab1').innerHTML=
  renderTables(r2Tables,null)
  +renderTableDetails(r2Tables,r2Scores,null)
  +'<div class="results-list"><div class="results-sub">全体順位</div>'+renderResults(r2Above,1)+'</div>'
  +renderElim(r2Below,13);

document.getElementById('tab2').innerHTML=
  renderTables(r3Tables,{showDone:true})
  +renderTableDetails(r3Tables,r3Scores,{showDone:true})
  +'<div class="results-list"><div class="results-sub">全体順位</div>'+renderResults(r3Above,1)+'</div>'
  +renderElim(r3Below,5);

// Tab switching
function switchTab(idx){
  var btns=document.querySelectorAll('.tab-btn');
  var tabs=document.querySelectorAll('.tab-content');
  for(var i=0;i<btns.length;i++){
    btns[i].classList.toggle('active',i===idx);
    tabs[i].classList.toggle('active',i===idx);
  }
}
