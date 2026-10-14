<?php
declare(strict_types=1);
require __DIR__.'/lib.php';
function pub404(){http_response_code(404);echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Panel no disponible</title><style>body{font-family:system-ui;background:#f5f5f5;color:#111013;display:grid;place-items:center;height:100vh;margin:0}.c{background:#fff;border:1px solid #e1e6ef;border-radius:14px;padding:36px 44px;text-align:center;box-shadow:0 4px 18px rgba(20,30,60,.08)}h1{font-size:20px;margin:0 0 8px}p{color:#94775c;margin:0}</style></head><body><div class="c"><div style="font-size:34px">⏱</div><h1>Panel no disponible</h1><p>Este panel no existe o no está publicado.</p></div></body></html>';exit;}
 $slug=strtolower(preg_replace('/[^a-zA-Z0-9\-]/','',$_GET['slug']??''));
 $st=db()->prepare('SELECT * FROM projects WHERE slug=?');$st->execute([$slug]);$P=$st->fetch();
if(!$P||(int)$P['is_public']!==1)pub404();
 $period=$_GET['p']??'month';
if(!in_array($period,['month','lastmonth','all'],true))$period='month';
 $now=new DateTime('today');$from=null;$to=null;
if($period==='month'){$from=$now->format('Y-m-01');$to=$now->format('Y-m-t');}
if($period==='lastmonth'){$lm=new DateTime('first day of last month');$from=$lm->format('Y-m-01');$to=$lm->format('Y-m-t');}
function fh($min){$n=round($min/60,1);return str_replace('.',',',preg_replace('/\.0$/','',number_format($n,1,'.',''))).' h';}
function fdur($min){$min=(int)$min;if($min<60)return $min.' min';$h=intdiv($min,60);$m=$min%60;return $m?"$h h $m min":"$h h";}
function pcol($p){if($p>=90&&$p<=110)return'#3fb96f';if($p>=70&&$p<=125)return'#e0a13a';return'#e05a5a';}
 $pdo=db();
 $ps=$pdo->prepare('SELECT pl.tag_id,pl.hours,t.name FROM plans pl JOIN tags t ON t.id=pl.tag_id WHERE pl.project_id=? AND pl.hours>0');
 $ps->execute([(int)$P['id']]);$plans=$ps->fetchAll();
 $sql='SELECT e.`date`,e.minutes,e.description,t.name tag FROM entries e JOIN tags t ON t.id=e.tag_id WHERE e.project_id=?';
 $pr=[(int)$P['id']];
if($from){$sql.=' AND e.`date` BETWEEN ? AND ?';$pr[]=$from;$pr[]=$to;}
 $sql.=' ORDER BY e.`date` DESC,e.id DESC';
 $es=$pdo->prepare($sql);$es->execute($pr);$entries=$es->fetchAll();
 $real=0;$realByTag=[];$byDay=[];
foreach($entries as $e){
  $real+=(int)$e['minutes'];
  $realByTag[$e['tag']]=($realByTag[$e['tag']]??0)+(int)$e['minutes'];
  $byDay[$e['date']]=($byDay[$e['date']]??0)+(int)$e['minutes'];
}
 $plan=0;$planByTag=[];
foreach($plans as $pl){$plan+=(float)$pl['hours']*60;$planByTag[$pl['name']]=(float)$pl['hours']*60;}
 $pct=$plan>0?round($real/$plan*100):null;
 $days=[];
if($from){
  $d=new DateTime($from);$end=new DateTime($to);while($d<=$end){$iso=$d->format('Y-m-d');$days[$iso]=$byDay[$iso]??0;$d->modify('+1 day');}
}else{$days=$byDay;}
 $dmax=max(1,...(array_values($days)?:[1]));
 $allTags=array_unique(array_merge(array_keys($planByTag),array_keys($realByTag)));
 $bmax=1;
foreach($allTags as $t)$bmax=max($bmax,$planByTag[$t]??0,($realByTag[$t]??0));
 $lname=['month'=>'Este mes','lastmonth'=>'Mes pasado','all'=>'Todo'][$period];
?>
<!doctype html><html lang="es" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($P['name'])?> · Andy Sparti</title>
<script>/* aplica el tema guardado antes de pintar (evita destello) */
(function(){try{var t=localStorage.getItem('tt_pub_theme');if(t!=='dark'&&t!=='light')t='light';document.documentElement.dataset.theme=t;}catch(e){}})();
</script><style>
/* ============ FUENTE DE MARCA ============ */
/* AJUSTA estas dos rutas a la ubicación real de tu fuente de marca en tu servidor */
@font-face{font-family:'Mabgate';src:url('/wp-content/uploads/2025/10/Mabgate-Regular.otf') format('opentype');font-weight:400;font-style:normal;font-display:swap}

/* ============ PALETA (light = tu marca · dark = derivada) ============ */
:root{
  --bg:#f5f5f5; --card:#ffffff; --card2:#f6f6f6; --border:#e1e6ef; --text:#111013;
  --muted:#94775c; --accent:#94775c;
  --ok:#3fb96f; --warn:#e0a13a; --bad:#e05a5a;
  --shadow:0 2px 8px rgba(20,30,60,.06);
  color-scheme:light;
}
html[data-theme="dark"]{
  --bg:#141114; --card:#1d1a1c; --card2:#282426; --border:#383234; --text:#efeae4;
  --muted:#b39779; --accent:#94775c;
  --shadow:0 2px 10px rgba(0,0,0,.35);
  color-scheme:dark;
}

body{font-family:system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--text);font-size:15px;line-height:1.45;margin:0;transition:background .25s,color .25s}
h1,h2,h3{font-family:'Mabgate',system-ui,-apple-system,sans-serif}
.wrap{max-width:860px;margin:0 auto;padding:22px 18px}

/* ============ CABECERA DE MARCA ============ */
.themerow{display:flex;justify-content:flex-end;margin-bottom:8px}
.theme-toggle{background:var(--card);border:1px solid var(--border);color:var(--text);border-radius:20px;padding:6px 14px;font-size:14px;cursor:pointer;transition:background .25s,border-color .25s}
.brandbar{background:var(--card);border:none;border-radius:10px;padding:12px 14px;width:100%;box-sizing:border-box;display:flex;align-items:center;justify-content:space-between;gap:16px;margin:0 0 16px;transition:background .25s}
/* Si quieres la cabecera SIEMPRE blanca, sustituye var(--card) por #ffffff en .brandbar */
.dash-title{font-family:'Mabgate',system-ui,-apple-system,sans-serif;font-weight:700;text-transform:uppercase;font-size:3rem;line-height:1;letter-spacing:.5px;color:var(--accent);padding-top: 11px;}


.logo{width:133px;height:66px;display:block;flex-shrink:0}
.logo-dark{display:none}
html[data-theme="dark"] .logo-dark{display:block}
html[data-theme="dark"] .logo-light{display:none}
@media(max-width:640px){.dash-title{font-size:1.6rem}.logo{width:100px;height:50px}}

/* ============ CONTENIDO ============ */
.head{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px}
.dot{width:13px;height:13px;border-radius:50%;display:inline-block;flex-shrink:0}
h1{font-size:22px;margin:0}
.sub{color:var(--muted);font-size:13px;margin:0 0 16px}
.tabs{display:flex;gap:8px;margin-bottom:18px}
.tabs a{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:5px 14px;font-size:13px;color:var(--muted);text-decoration:none;transition:background .25s,border-color .25s}
.tabs a.on{background:var(--accent);border-color:var(--accent);color:#fff;font-weight:600}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px;box-shadow:var(--shadow);transition:background .25s,border-color .25s}
.card h3{margin:0 0 12px;font-size:14px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted)}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}
@media(max-width:640px){.stats{grid-template-columns:repeat(2,1fr)}}
.stat{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px 14px;transition:background .25s,border-color .25s}
.stat .n{font-size:22px;font-weight:700}
.stat .l{font-size:12px;color:var(--muted)}
.bar-row{display:grid;grid-template-columns:110px 1fr 70px;gap:8px;align-items:center;margin:5px 0}
.bar-track{background:var(--card2);border-radius:5px;height:15px;overflow:hidden}
.bar{height:100%;border-radius:5px;min-width:2px}
.bar.plan{background:var(--accent);opacity:.4}
.bl{font-size:12px;color:var(--muted);font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bv{font-size:12px;text-align:right;color:var(--muted);font-variant-numeric:tabular-nums}
.daychart{display:flex;gap:3px;align-items:flex-end;height:130px;overflow-x:auto;padding-top:4px}
.dcol{flex:1;min-width:12px;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;height:100%}
.dfill{width:70%;border-radius:4px 4px 0 0;background:var(--accent);min-height:0}
.dlab{font-size:9px;color:var(--muted);margin-top:4px}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;color:var(--muted);font-size:11px;text-transform:uppercase;padding:6px 8px;border-bottom:1px solid var(--border)}
td{padding:7px 8px;border-bottom:1px solid var(--card2);font-variant-numeric:tabular-nums}
.num{text-align:right}

.tchip{display:inline-block;background:var(--card2);border:1px solid var(--border);border-radius:20px;padding:1px 9px;font-size:12px;color:var(--muted)}
.desc-cell{max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
@media(max-width:767px){
  .desc-cell{max-width:100%;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;white-space:normal}
}
footer{text-align:center;color:var(--muted);font-size:11px;padding:22px}
.pct{margin-left:auto;font-weight:700}
.prow{padding:8px 0;border-bottom:1px solid var(--card2)}
.prow:last-child{border:none}
.prow-head{display:flex;align-items:center;gap:8px;font-weight:600;font-size:14px;margin-bottom:4px}
.empty{color:var(--muted);font-size:13px;text-align:center;padding:14px 0}
</style></head><body><div class="wrap">

<div class="themerow"><button id="themeBtn" class="theme-toggle" onclick="toggleTheme()">🌙</button></div>

<div class="brandbar">
  <span class="dash-title">Dashboard</span>
  <img class="logo logo-light" src="https://www.andysparti.com/wp-content/uploads/2025/02/AndySparti-Logo-Pos.svg" alt="Andy Sparti">
  <img class="logo logo-dark" src="https://www.andysparti.com/wp-content/uploads/2025/02/AndySparti-Logo-Neg-1.svg" alt="Andy Sparti">
</div>

<div class="head"><span class="dot" style="background:<?=htmlspecialchars($P['color'])?>"></span><h1><?=htmlspecialchars($P['name'])?></h1>
</div>
<p class="sub">Informe de horas y cumplimiento · período: <b><?=$lname?></b></p>
<div class="tabs">
<a href="?p=month" class="<?=$period==='month'?'on':''?>">Este mes</a>
<a href="?p=lastmonth" class="<?=$period==='lastmonth'?'on':''?>">Mes pasado</a>
<a href="?p=all" class="<?=$period==='all'?'on':''?>">Todo</a>
</div>
<div class="stats">
<div class="stat"><div class="n"><?=fh($real)?></div><div class="l">Horas realizadas</div></div>
<div class="stat"><div class="n"><?=fh($plan)?></div><div class="l">Horas planificadas</div></div>
<div class="stat"><div class="n" style="color:<?=$pct===null?'var(--muted)':pcol($pct)?>"><?=$pct===null?'—':$pct.'%'?></div><div class="l">Cumplimiento</div></div>
<div class="stat"><div class="n"><?=count($entries)?></div><div class="l">Entradas</div></div>
</div>
<div class="card"><h3>Planificado vs. realizado por tipología</h3>
<?php if($allTags): foreach($allTags as $t): $pl=$planByTag[$t]??0;$re=$realByTag[$t]??0;$pc=$pl?round($re/$pl*100):null;?>
<div class="prow"><div class="prow-head"><?=htmlspecialchars($t)?><?php if($pc!==null):?><span class="pct" style="color:<?=pcol($pc)?>"><?=$pc?>%</span><?php endif;?></div>
<div class="bar-row"><span class="bl">Plan</span><div class="bar-track"><div class="bar plan" style="width:<?=$pl/$bmax*100?>%"></div></div><span class="bv"><?=fh($pl)?></span></div>
<div class="bar-row"><span class="bl">Real</span><div class="bar-track"><div class="bar" style="width:<?=$re/$bmax*100?>%;background:<?=htmlspecialchars($P['color'])?>"></div></div><span class="bv"><?=fh($re)?></span></div>
</div><?php endforeach; else: ?><div class="empty">Sin datos en este período.</div><?php endif; ?>
</div>
<div class="card"><h3>Horas por día</h3>
<?php if($days): ?><div class="daychart"><?php foreach($days as $iso=>$v):$lab=(new DateTime($iso))->format('j');?>
<div class="dcol" title="<?=$iso?> — <?=fdur($v)?>"><div class="dfill" style="height:<?=$v/$dmax*80?>%;<?=$v?'':'background:var(--card2)'?>"></div><span class="dlab"><?=$v||((int)$lab%7===((int)(new DateTime($from??$iso))->format('j'))%7)?$lab:''?></span></div>
<?php endforeach;?></div><?php else: ?><div class="empty">Sin datos.</div><?php endif; ?>
</div>
<div class="card"><h3>Registro de tareas</h3>
<?php if($entries): ?><table><thead><tr><th>Fecha</th><th>Tipología</th><th>Descripción</th><th class="num">Duración</th></tr></thead><tbody>
<?php foreach($entries as $e):?>
<tr><td><?=(new DateTime($e['date']))->format('d/m/y')?></td><td><span class="tchip"><?=htmlspecialchars($e['tag'])?></span></td>

<td><div class="desc-cell"><?=htmlspecialchars($e['description']?:'—')?></div></td>

<td class="num"><?=fdur($e['minutes'])?></td></tr>
<?php endforeach;?></tbody></table><?php else: ?><div class="empty">Sin tareas registradas en este período.</div><?php endif; ?>
</div>
</div><footer>Generado con TimeTrack</footer>

<script>
(function(){
  function actual(){return document.documentElement.dataset.theme==='dark'?'dark':'light';}
  function pintaBtn(){var b=document.getElementById('themeBtn');if(b)b.textContent=actual()==='dark'?'☀ Claro':'🌙 Oscuro';}
  window.toggleTheme=function(){
    var t=actual()==='dark'?'light':'dark';
    document.documentElement.dataset.theme=t;
    try{localStorage.setItem('tt_pub_theme',t);}catch(e){}
    pintaBtn();
  };
  pintaBtn();
})();
</script>
</body></html>