/* ======================
   data.js
   Tournament data — standings, tables, results
   ====================== */

var MAX_BAR = 130;
var MEDALS = ['\u{1F947}','\u{1F948}','\u{1F949}'];

var standings = [
  {rank:1,name:'ホロホロ',total:171.2,r:[10.9,43.4,48.6,68.3],pending:false,elim:0},
  {rank:2,name:'あはん',total:130.4,r:[82.5,18.8,29.1],pending:false,elim:3},
  {rank:3,name:'するが',total:63.3,r:[-44.4,10.0,33.2,64.5],pending:false,elim:0},
  {rank:4,name:'ぎり',total:56.5,r:[10.2,58.5,-12.2],pending:false,elim:3},
  {rank:5,name:'がちゃ',total:55.8,r:[51.9,24.9,37.5,-58.5],pending:false,elim:0},
  {rank:6,name:'シーマ',total:55.5,r:[51.7,2.3,1.5],pending:false,elim:3},
  {rank:7,name:'みーた',total:47.8,r:[26.9,-10.3,31.2],pending:false,elim:3},
  {rank:8,name:'みか',total:43.7,r:[22.3,54.6,41.1,-74.3],pending:false,elim:0},
  {rank:9,name:'こいぬ',total:40.3,r:[40.3],pending:false,elim:1},
  {rank:10,name:'あき',total:-0.7,r:[-16.1,37.3,-21.9],pending:false,elim:3},
  {rank:11,name:'ぶる',total:-22.7,r:[11.9,-34.6],pending:false,elim:2},
  {rank:12,name:'そぼろ',total:-27.4,r:[12.2,-39.6],pending:false,elim:2},
  {rank:13,name:'ぱーらめんこ',total:-63.7,r:[-63.7],pending:false,elim:1},
  {rank:14,name:'りあ',total:-68.0,r:[-6.5,-33.3,-28.2],pending:false,elim:3},
  {rank:15,name:'けちゃこ',total:-71.2,r:[1.1,-72.3],pending:false,elim:2},
  {rank:16,name:'あーす',total:-72.8,r:[-72.8],pending:false,elim:1},
  {rank:17,name:'がう',total:-73.0,r:[28.7,3.8,-105.5],pending:false,elim:3},
  {rank:18,name:'なぎ',total:-76.0,r:[-76.0],pending:false,elim:1},
  {rank:19,name:'梅',total:-80.4,r:[-22.0,-58.4],pending:false,elim:2},
  {rank:20,name:'イラチ',total:-107.8,r:[-49.1,-4.3,-54.4],pending:false,elim:3}
];

// Round 1
var r1Tables = [
  {name:'1卓',sched:'',players:['みか','こいぬ','ぱーらめんこ','けちゃこ']},
  {name:'2卓',sched:'金曜 21:00',players:['ぶる','がちゃ','なぎ','そぼろ']},
  {name:'3卓',sched:'土曜 21:00',players:['ぎり','ホロホロ','あーす','シーマ']},
  {name:'4卓',sched:'日曜 13:00',players:['みーた','りあ','がう','イラチ']},
  {name:'5卓',sched:'日曜 22:00',players:['あき','あはん','梅','するが']}
];
var r1Above = [
  ['あはん',82.5],['がちゃ',51.9],['シーマ',51.7],['こいぬ',40.3],
  ['がう',28.7],['みーた',26.9],['みか',22.3],['そぼろ',12.2],
  ['ぶる',11.9],['ホロホロ',10.9],['ぎり',10.2],['けちゃこ',1.1],
  ['りあ',-6.5],['あき',-16.1],['梅',-22.0],['するが',-44.4]
];
var r1Below = [['イラチ',-49.1],['ぱーらめんこ',-63.7],['あーす',-72.8],['なぎ',-76.0]];

// Round 2
var r2Tables = [
  {name:'1卓',sched:'',players:['みか','ホロホロ','梅','そぼろ']},
  {name:'2卓',sched:'',players:['ぶる','シーマ','あき','イラチ']},
  {name:'3卓',sched:'',players:['ぎり','がう','するが','けちゃこ']},
  {name:'4卓',sched:'',players:['みーた','あはん','がちゃ','りあ']}
];
var r2Above = [
  ['ぎり',58.5],['みか',54.6],['ホロホロ',43.4],['あき',37.3],
  ['がちゃ',24.9],['あはん',18.8],['するが',10.0],['がう',3.8],
  ['シーマ',2.3],['イラチ',-4.3],['みーた',-10.3],['りあ',-33.3]
];
var r2Below = [['ぶる',-34.6],['そぼろ',-39.6],['梅',-58.4],['けちゃこ',-72.3]];

// Round 3
var r3Tables = [
  {name:'1卓',sched:'',players:['あき','ホロホロ','シーマ','りあ'],done:true},
  {name:'2卓',sched:'日曜夜',players:['みか','みーた','がう','するが'],done:true},
  {name:'3卓',sched:'',players:['あはん','イラチ','がちゃ','ぎり'],done:true}
];
var r3Above = [['ホロホロ',48.6],['みか',41.1],['がちゃ',37.5],['するが',33.2]];
var r3Below = [['みーた',31.2],['あはん',29.1],['シーマ',1.5],['ぎり',-12.2],['あき',-21.9],['りあ',-28.2],['イラチ',-54.4],['がう',-105.5]];

// Round 4 (Finals)
var r4Tables = [
  {name:'決勝卓',sched:'3月22日（日）20時30分より開始！',players:['ホロホロ','みか','がちゃ','するが'],done:true}
];
var r4Above = [['ホロホロ',68.3],['するが',64.5]];
var r4Below = [['がちゃ',-58.5],['みか',-74.3]];
