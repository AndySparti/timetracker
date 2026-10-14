<?php
declare(strict_types=1);
require __DIR__.'/lib.php';
 $err='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST'&&(isset($_POST['login'])||isset($_POST['user']))){
  $u=trim($_POST['user']??'');$p=$_POST['pass']??'';
  try{
    $st=db()->prepare('SELECT * FROM users WHERE username=?');$st->execute([$u]);$row=$st->fetch();
    if($row&&password_verify($p,$row['password_hash'])){
      session_regenerate_id(true);
      $_SESSION['tt_uid']=(int)$row['id'];$_SESSION['tt_user']=$row['username'];
      if(empty($_SESSION['tt_csrf']))$_SESSION['tt_csrf']=bin2hex(random_bytes(16));
      header('Location: '.base_dir().'/');exit;
    }
  }catch(Throwable $e){$err='Error de base de datos. ¿Ejecutaste install.php?';}
  if($err==='')$err='Usuario o contraseña incorrectos.';
}
 $user=current_user();
if(!$user){
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TimeTrack · Acceso</title><style>
body{font-family:system-ui,sans-serif;background:#0f1117;color:#e9ecf4;margin:0;display:grid;place-items:center;min-height:100vh}
.c{background:#171a23;border:1px solid #282e3d;border-radius:14px;padding:30px;width:340px;box-shadow:0 4px 18px rgba(0,0,0,.4)}
h1{font-size:19px;margin:0 0 2px;text-align:center}h1 span{color:#5b8cff}
p.s{color:#8d95a8;font-size:12.5px;text-align:center;margin:0 0 18px}
label{display:block;font-size:12px;color:#8d95a8;font-weight:600;margin:10px 0 4px}
input{width:100%;box-sizing:border-box;background:#1d212d;border:1px solid #282e3d;color:#e9ecf4;border-radius:8px;padding:10px;font-size:14px}
input:focus{outline:none;border-color:#5b8cff}
button{margin-top:18px;width:100%;background:#5b8cff;color:#fff;border:none;border-radius:8px;padding:11px;font-size:15px;font-weight:700;cursor:pointer}
.e{background:#2a1a1c;border:1px solid #5a2a2e;color:#e08a8a;border-radius:8px;padding:9px;font-size:13px;margin-bottom:10px}
</style></head><body><div class="c">
<h1>⏱ <span>Time</span>Track</h1><p class="s">Acceso privado</p>
<?php if($err): ?><div class="e"><?=htmlspecialchars($err)?></div><?php endif; ?>
<form method="post"><label>Usuario</label><input name="user" autofocus required>
<label>Contraseña</label><input type="password" name="pass" required>
<button type="submit" name="login">Entrar</button></form>
</div></body></html><?php
exit;
}
if(empty($_SESSION['tt_csrf']))$_SESSION['tt_csrf']=bin2hex(random_bytes(16));
 $boot=json_encode(build_state(),JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_INVALID_UTF8_SUBSTITUTE);
if($boot===false||$boot===''||$boot==='null'){
  $boot='{"projects":[],"tags":[],"entries":[],"launchers":[],"settings":{"autoPause":true,"autoPauseMin":10,"theme":"dark"},"filters":{"preset":"month"}}';
}
 $csrf=$_SESSION['tt_csrf'];
?><!doctype html>
<html lang="es" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TimeTrack — Registro de horas</title>
<style>
:root{
  --bg:#0f1117; --card:#171a23; --card2:#1d212d; --border:#282e3d; --text:#e9ecf4;
  --muted:#8d95a8; --accent:#5b8cff; --ok:#3fb96f; --warn:#e0a13a; --bad:#e05a5a;
  --shadow:0 2px 10px rgba(0,0,0,.35);
}
html[data-theme="light"]{
  --bg:#f3f5f9; --card:#ffffff; --card2:#f7f8fc; --border:#e1e6ef; --text:#1d2534;
  --muted:#69748e; --accent:#3b6fe0; --shadow:0 2px 8px rgba(20,30,60,.08);
}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text);font-size:15px;line-height:1.45}
header{position:sticky;top:0;z-index:10;background:var(--bg);border-bottom:1px solid var(--border);padding:14px 20px;display:flex;justify-content:space-between;align-items:center;gap:10px}
header h1{font-size:18px;margin:0;font-weight:700}
header h1 span{color:var(--accent)}
.hbtns{display:flex;gap:6px;align-items:center}
.chip{display:inline-block;background:var(--card2);border:1px solid var(--border);border-radius:20px;padding:1px 9px;font-size:12px;color:var(--muted)}
.wrap{max-width:1080px;margin:0 auto;padding:20px}
nav{display:flex;gap:6px;border-bottom:1px solid var(--border);padding:0 20px;background:var(--bg);position:sticky;top:53px;z-index:9;overflow-x:auto}
nav button{background:none;border:none;color:var(--muted);padding:10px 14px;font-size:14px;cursor:pointer;border-bottom:2px solid transparent;font-weight:500;white-space:nowrap}
nav button.active{color:var(--text);border-bottom-color:var(--accent)}
.syncbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:8px 20px;border-bottom:1px solid var(--border);background:var(--card);font-size:12.5px;color:var(--muted)}
.syncbar b{color:var(--text)}
.syncbar .btn{padding:3px 10px;font-size:12px}
.syncdot{width:8px;height:8px;border-radius:50%;background:var(--muted);flex-shrink:0}
.syncdot.on{background:var(--ok)}
.syncdot.err{background:var(--bad)}
.syncdot.save{background:var(--warn)}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px;box-shadow:var(--shadow)}
.card h3{margin:0 0 12px;font-size:15px}
label{display:block;font-size:12px;color:var(--muted);margin:0 0 4px;font-weight:600}
input,select,textarea{width:100%;background:var(--card2);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:8px 10px;font-size:14px;font-family:inherit}
input:focus,select:focus{outline:none;border-color:var(--accent)}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
.row>div{flex:1;min-width:120px}
.btn{background:var(--accent);color:#fff;border:none;border-radius:8px;padding:9px 16px;font-size:14px;font-weight:600;cursor:pointer}
.btn:hover{filter:brightness(1.1)}
.btn.ghost{background:transparent;color:var(--muted);border:1px solid var(--border)}
.btn.ghost:hover{color:var(--text)}
.btn.danger{background:transparent;color:var(--bad);border:1px solid var(--border)}
.btn.small{padding:4px 10px;font-size:12px}
.dot{width:10px;height:10px;border-radius:50%;display:inline-block;flex-shrink:0}
.entry{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border)}
.entry:last-child{border-bottom:none}
.entry-main{flex:1;min-width:0}
.entry-main .desc{color:var(--muted);font-size:13px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.entry-side{display:flex;align-items:center;gap:8px;white-space:nowrap}
.day-head{font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin:14px 0 2px}
.stat{background:var(--card2);border:1px solid var(--border);border-radius:10px;padding:12px 14px}
.stat .n{font-size:22px;font-weight:700}
.stat .l{font-size:12px;color:var(--muted)}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}
@media(max-width:720px){.stats{grid-template-columns:repeat(2,1fr)}}
.bar-row{display:grid;grid-template-columns:44px 1fr 72px;gap:8px;align-items:center;margin:5px 0}
.bar-row .bl{font-size:11px;color:var(--muted);font-weight:600}
.bar-track{background:var(--card2);border-radius:5px;height:16px;overflow:hidden}
.bar{height:100%;border-radius:5px;min-width:2px}
.bar.plan{background:var(--muted);opacity:.45}
.bar-row .bv{font-size:12px;text-align:right;color:var(--muted);font-variant-numeric:tabular-nums}
.prow{padding:10px 0;border-bottom:1px solid var(--border)}
.prow:last-child{border-bottom:none}
.prow-head{display:flex;align-items:center;gap:8px;margin-bottom:6px;font-weight:600;font-size:14px}
.prow-head .pct{margin-left:auto;font-size:13px;font-variant-numeric:tabular-nums}
.daychart{display:flex;gap:3px;align-items:flex-end;height:150px;overflow-x:auto;padding-top:4px}
.dcol{flex:1;min-width:14px;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;height:100%}
.dfill{width:70%;border-radius:4px 4px 0 0;background:var(--accent);min-height:0}
.dlab{font-size:9px;color:var(--muted);margin-top:4px}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.4px;padding:6px 8px;border-bottom:1px solid var(--border)}
td{padding:7px 8px;border-bottom:1px solid var(--border);font-variant-numeric:tabular-nums}
tfoot td{font-weight:700;border-bottom:none}
.num{text-align:right}
.proj{display:flex;align-items:center;gap:10px;padding:12px 0;border-bottom:1px solid var(--border);flex-wrap:wrap}
.proj:last-child{border-bottom:none}
.proj-main{flex:1;min-width:200px}
.proj-name{font-weight:600}
.plan-chips{margin-top:4px;display:flex;flex-wrap:wrap;gap:5px}
.tag-row{display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--border)}
.tag-row:last-child{border-bottom:none}
.tag-row input{flex:1}
.pulse{width:9px;height:9px;border-radius:50%;background:var(--bad);display:inline-block;animation:p 1.2s infinite}
@keyframes p{50%{opacity:.3}}
.clock{font-size:26px;font-weight:700;font-variant-numeric:tabular-nums}
.empty{color:var(--muted);font-size:13px;text-align:center;padding:18px 0}
.filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.fchip{background:var(--card2);border:1px solid var(--border);color:var(--muted);border-radius:20px;padding:5px 13px;font-size:13px;cursor:pointer}
.fchip.active{background:var(--accent);color:#fff;border-color:var(--accent);font-weight:600}
.hint{display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:12px;color:var(--muted);margin-top:12px}
.hint input[type=checkbox]{width:auto;margin:0}
.hint input[type=number]{width:64px;padding:4px 6px}
.launch{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border);flex-wrap:wrap}
.launch:last-child{border-bottom:none}
.launch-main{flex:1;min-width:180px}
.launch .url{display:block;font-size:11px;color:var(--muted);max-width:420px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
footer{text-align:center;color:var(--muted);font-size:11px;padding:24px 20px 34px}
footer button{background:none;border:none;color:var(--muted);text-decoration:underline;cursor:pointer;font-size:11px}
.muted{color:var(--muted)}
</style>
</head>
<body>
<header>
  <h1>⏱ <span>Time</span>Track</h1>
  <div class="hbtns">
    <span class="chip"><?=htmlspecialchars($user['username'])?></span>
    <button class="btn ghost small" onclick="toggleTheme()" id="themeBtn">☀ Claro</button>
    <button class="btn ghost small" onclick="logout()">Salir</button>
  </div>
</header>
<nav>
  <button id="tab-registro" class="active" onclick="switchView('registro')">Registro</button>
  <button id="tab-proyectos" onclick="switchView('proyectos')">Proyectos</button>
  <button id="tab-tipologias" onclick="switchView('tipologias')">Tipologías</button>
  <button id="tab-dashboard" onclick="switchView('dashboard')">Dashboard</button>
</nav>
<div id="syncBar" class="syncbar"></div>

<div class="wrap">
<!-- ============ REGISTRO ============ -->
<section id="view-registro">
  <div class="card">
    <div id="timerIdle">
      <div class="row">
        <div><label>Proyecto</label><select id="tProj"></select></div>
        <div><label>Tipología</label><select id="tTag"></select></div>
        <div style="flex:2"><label>En qué trabajas (opcional)</label><input id="tDesc" placeholder="Descripción breve"></div>
        <div style="flex:0"><button class="btn" onclick="startTimer()">▶ Iniciar</button></div>
      </div>
      <div class="hint">
        <input type="checkbox" id="apOn" onchange="setAutoPause()"> Auto-pausar tras
        <input type="number" id="apMin" min="1" max="120" onchange="setAutoPause()"> min sin actividad
        <span>(solo aplica mientras esta pestaña está en primer plano)</span>
      </div>
    </div>
    <div id="timerRunning" style="display:none">
      <div class="row" style="align-items:center">
        <div><span class="pulse"></span> <b id="timerInfo"></b></div>
        <div class="clock" id="timerClock">00:00:00</div>
        <div style="flex:0;display:flex;gap:8px">
          <button class="btn ghost" onclick="pauseTimer('manual')">⏸ Pausar</button>
          <button class="btn" onclick="stopTimer()">■ Detener y guardar</button>
          <button class="btn ghost" onclick="cancelTimer()">Descartar</button>
        </div>
      </div>
    </div>
    <div id="timerPaused" style="display:none">
      <div class="row" style="align-items:center">
        <div>⏸ <b id="pauseReason">En pausa</b></div>
        <div class="clock" id="pauseClock" style="color:var(--warn)">00:00:00</div>
        <div style="flex:0;display:flex;gap:8px">
          <button class="btn" onclick="resumeTimer()">▶ Reanudar</button>
          <button class="btn" onclick="stopTimer()">■ Detener y guardar</button>
          <button class="btn ghost" onclick="cancelTimer()">Descartar</button>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <h3>Inicios rápidos</h3>
    <div id="launchList"></div>
    <div class="row" style="margin-top:10px">
      <div style="flex:2"><label>Nombre del acceso</label><input id="lName" placeholder="Ej: Figma — Proyecto XXX"></div>
      <div><label>Proyecto</label><select id="lProj"></select></div>
      <div><label>Tipología</label><select id="lTag"></select></div>
    </div>
    <div class="row" style="margin-top:8px">
      <div style="flex:3"><label>URL o archivo que se abrirá al iniciar (opcional)</label>
        <input id="lUrl" placeholder="https://figma.com/... · figma://..."></div>
      <div style="flex:0"><button class="btn ghost" onclick="addLauncher()">+ Guardar acceso</button></div>
    </div>
  </div>

  <div class="card">
    <h3 id="manualTitle">Añadir entrada manual</h3>
    <div class="row">
      <div><label>Fecha</label><input type="date" id="mDate"></div>
      <div><label>Proyecto</label><select id="mProj"></select></div>
      <div><label>Tipología</label><select id="mTag"></select></div>
      <div style="max-width:90px"><label>Horas</label><input type="number" id="mH" min="0" max="23" value="0"></div>
      <div style="max-width:90px"><label>Min</label><input type="number" id="mMin" min="0" max="59" value="30"></div>
    </div>
    <div class="row" style="margin-top:10px">
      <div style="flex:3"><label>Descripción</label><input id="mDesc" placeholder="Opcional"></div>
      <div style="flex:0"><button class="btn" id="mBtn" onclick="saveManual()">Añadir</button>
      <button class="btn ghost" id="mCancel" style="display:none" onclick="cancelEditEntry()">Cancelar</button></div>
    </div>
  </div>

  <div class="card"><h3>Entradas</h3><div id="entriesList"></div></div>
</section>

<!-- ============ PROYECTOS ============ -->
<section id="view-proyectos" style="display:none">
  <div class="card">
    <h3 id="projFormTitle">Nuevo proyecto</h3>
    <div class="row">
      <div style="flex:3"><label>Nombre</label><input id="projName" placeholder="Ej: Proyecto XXX" oninput="onNameInput()"></div>
      <div style="flex:0;max-width:70px"><label>Color</label><input type="color" id="projColor" value="#5b8cff" style="padding:2px;height:38px"></div>
    </div>
    <div class="row" style="margin-top:12px">
      <div><label>Tipología</label><select id="planTag"></select></div>
      <div style="max-width:100px"><label>Horas planificadas</label><input type="number" id="planHours" min="0.5" step="0.5" placeholder="20"></div>
      <div style="flex:0"><button class="btn ghost" onclick="addPlanRow()">+ Añadir al plan</button></div>
    </div>
    <div id="planDraft" style="margin-top:10px"></div>
    <div class="hint" style="margin-top:14px;border-top:1px solid var(--border);padding-top:12px">
      <input type="checkbox" id="projPublic"> Visible públicamente
      <span style="flex:1"></span>
      <span>URL pública (slug):</span>
      <input id="projSlug" placeholder="xxx" style="width:150px" oninput="slugDirty=true;updateSlugPreview()">
    </div>
    <div class="hint" id="slugPreview" style="margin-top:6px"></div>
    <div class="row" style="margin-top:14px">
      <div style="flex:0"><button class="btn" onclick="saveProject()">Guardar proyecto</button>
      <button class="btn ghost" id="projCancel" style="display:none" onclick="cancelEditProject()">Cancelar</button></div>
    </div>
  </div>
  <div class="card"><h3>Mis proyectos</h3><div id="projList"></div></div>
</section>

<!-- ============ TIPOLOGÍAS ============ -->
<section id="view-tipologias" style="display:none">
  <div class="card">
    <h3>Nueva tipología</h3>
    <div class="row">
      <div style="flex:3"><input id="tagName" placeholder="Ej: Motion design" onkeydown="if(event.key==='Enter')addTag()"></div>
      <div style="flex:0"><button class="btn" onclick="addTag()">Añadir</button></div>
    </div>
  </div>
  <div class="card"><h3>Tipologías de tarea</h3><div id="tagList"></div></div>
</section>

<!-- ============ DASHBOARD ============ -->
<section id="view-dashboard" style="display:none">
  <div class="card">
    <div class="filters">
      <button class="fchip" data-p="week" onclick="applyPreset('week')">Esta semana</button>
      <button class="fchip" data-p="month" onclick="applyPreset('month')">Este mes</button>
      <button class="fchip" data-p="lastmonth" onclick="applyPreset('lastmonth')">Mes pasado</button>
      <button class="fchip" data-p="all" onclick="applyPreset('all')">Todo</button>
      <span class="muted" style="font-size:12px">del</span>
      <input type="date" id="fFrom" style="width:150px" onchange="setCustomFrom(this.value)">
      <span class="muted" style="font-size:12px">al</span>
      <input type="date" id="fTo" style="width:150px" onchange="setCustomTo(this.value)">
      <select id="fProj" style="width:auto" onchange="state.filters.project=this.value;renderDashboard()"></select>
      <select id="fTag" style="width:auto" onchange="state.filters.tag=this.value;renderDashboard()"></select>
      <button class="btn ghost" onclick="exportCSV()">⬇ Exportar CSV</button>
    </div>
  </div>
  <div class="stats" id="kpis"></div>
  <div class="card"><h3>Planificado vs. realizado por proyecto</h3><div id="chartProjects"></div></div>
  <div class="card"><h3>Horas por día</h3><div id="chartDays"></div></div>
  <div class="card" style="overflow-x:auto"><h3>Detalle de cumplimiento</h3><div id="detailTable"></div></div>
</section>
</div>

<footer>
  Datos guardados en tu servidor ·
  <button onclick="exportJSON()">⬇ Exportar JSON</button> ·
  <button onclick="importJSONPick()">⬆ Importar JSON</button> ·
  <button onclick="wipeAll()">Borrar todos los datos</button>
</footer>

<script>
/* AUTODIAGNÓSTICO: si el script principal falla, se muestra el error en pantalla */
(function(){
  function banner(msg){
    var b=document.createElement('div');
    b.style.cssText='position:fixed;bottom:0;left:0;right:0;z-index:9999;background:#5a1f24;color:#ffd9d9;font:12px/1.5 monospace;padding:8px 14px;max-height:40vh;overflow:auto';
    b.textContent='⚠ Error JavaScript: '+msg+'  — copia este texto y envíamelo.';
    document.body.appendChild(b);
  }
  window.addEventListener('error',function(e){banner(e.message+' (línea '+(e.lineno||'?')+')');});
  window.addEventListener('unhandledrejection',function(e){
    var r=e.reason;banner('promesa: '+((r&&r.message)||r));
  });
  setTimeout(function(){
    if(!window.__ttReady)banner('La aplicación no terminó de arrancar. Es probable que index.php quedara incompleto al subirlo: vuelve a subirlo completo.');
  },3000);
})();
</script>

<script>
'use strict';
const CSRF=<?=json_encode($csrf)?>;
const BOOT=<?=$boot?>;
const TKEY='timetrack_timer';
const $=s=>document.querySelector(s);
const BASE=location.pathname.replace(/\/[^\/]*$/,'/');
const publicURL=slug=>location.origin+BASE+slug;

function normalizeState(s){
  s=s||{};
  s.projects=s.projects||[];s.tags=s.tags||[];s.entries=s.entries||[];s.launchers=s.launchers||[];
  s.settings=s.settings||{autoPause:true,autoPauseMin:10,theme:'dark'};
  s.filters=s.filters||{from:'',to:'',project:'',tag:'',preset:'month'};
  return s;
}
function adoptState(s){
  const prevF=state&&state.filters?state.filters:null;
  state=normalizeState(s);
  if(prevF)state.filters=prevF;
  applyTheme();renderAll();
}
let state=normalizeState(BOOT);


let planDraft={};
let editingEntryId=null, editingProjectId=null, view='registro';
let lastActivity=Date.now(), slugDirty=false;

/* ---------- helpers ---------- */
const uid=()=>Date.now().toString(36)+Math.random().toString(36).slice(2,8);
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const pad=n=>String(n).padStart(2,'0');
const toISO=d=>d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate());
const parseISO=s=>{const[a,b,c]=s.split('-').map(Number);return new Date(a,b-1,c);};
const todayISO=()=>toISO(new Date());
const proj=id=>state.projects.find(p=>String(p.id)===String(id));
const tag=id=>state.tags.find(t=>String(t.id)===String(id));
const tagName=id=>tag(id)?.name??'(eliminada)';
const mondayOf=d=>{const x=new Date(d);x.setDate(x.getDate()-((x.getDay()+6)%7));x.setHours(0,0,0,0);return x;};
const elapsedMs=t=>t.accumulatedMs+(t.runningSince?Date.now()-t.runningSince:0);
const slugify=s=>String(s).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'')||'proyecto';

function fmtDur(min){min=Math.round(min);if(min<60)return min+' min';const h=Math.floor(min/60),m=min%60;return m?h+' h '+m+' min':h+' h';}
function fmtH(min){const n=Math.round(min/6)/10;return String(n).replace('.',',')+' h';}
function fmtClock(ms){const s=Math.floor(ms/1000);return pad(Math.floor(s/3600))+':'+pad(Math.floor(s%3600/60))+':'+pad(s%60);}
function pctColor(p){if(p>=90&&p<=110)return'var(--ok)';if(p>=70&&p<=125)return'var(--warn)';return'var(--bad)';}

['mousemove','mousedown','keydown','wheel','touchstart','scroll'].forEach(ev=>
  document.addEventListener(ev,()=>{lastActivity=Date.now();},{passive:true,capture:true}));

/* ---------- API ---------- */
function setSync(mode,msg){
  const bar=$('#syncBar');if(!bar)return;
  let left='';
  if(mode==='saving')left='<span class="syncdot save"></span> Guardando…';
  else if(mode==='ok')left='<span class="syncdot on"></span> Guardado en servidor';
  else if(mode==='error')left='<span class="syncdot err"></span> <b>Error:</b> '+esc(msg||'desconocido')+' <button class="btn ghost" onclick="resync()">Reintentar</button>';
  else left='<span class="syncdot on"></span> Conectado a la base de datos';
  bar.innerHTML=left+'<span style="margin-left:auto"></span>'+
    '<button class="btn ghost" onclick="exportJSON()">⬇ Exportar JSON</button>'+
    '<button class="btn ghost" onclick="importJSONPick()">⬆ Importar JSON</button>';
}
async function api(action,data={}){
  setSync('saving');
  try{
    const r=await fetch('api.php'+(action==='bootstrap'?'?action=bootstrap':''),{
      method:action==='bootstrap'?'GET':'POST',
      headers:{'Content-Type':'application/json','X-CSRF':CSRF},
      body:action==='bootstrap'?undefined:JSON.stringify({action,data})
    });
    const j=await r.json();
    if(!j.ok)throw new Error(j.error||'Error del servidor');

    if(j.state){adoptState(j.state);}

    setSync('ok');
    return j;
  }catch(e){
    if(String(e.message).includes('Sesión')){alert(e.message);location.href=BASE;throw e;}
    setSync('error',e.message);alert(e.message);
   
try{const r=await fetch('api.php?action=bootstrap');const j=await r.json();if(j.ok)adoptState(j.state);}catch(_){}

    throw e;
  }
}
async function resync(){setSync('saving');try{await api('bootstrap');}catch(e){}}
function logout(){api('logout').catch(()=>{}).finally(()=>{location.href=BASE;});}

/* ---------- vistas ---------- */
function switchView(v){
  view=v;
  ['registro','proyectos','tipologias','dashboard'].forEach(x=>{
    $('#view-'+x).style.display=x===v?'':'none';
    $('#tab-'+x).classList.toggle('active',x===v);
  });
  if(v==='dashboard')renderDashboard();
}
function applyTheme(){
  document.documentElement.dataset.theme=state.settings.theme||'dark';
  $('#themeBtn').textContent=state.settings.theme==='dark'?'☀ Claro':'🌙 Oscuro';
}
function toggleTheme(){
  state.settings.theme=state.settings.theme==='dark'?'light':'dark';
  applyTheme();
  api('save_settings',{theme:state.settings.theme}).catch(()=>{});
}

/* ---------- cronómetro ---------- */
function persistTimer(){if(state.timer)localStorage.setItem(TKEY,JSON.stringify(state.timer));else localStorage.removeItem(TKEY);}
function startTimer(){
  const projectId=$('#tProj').value,tagId=$('#tTag').value;
  if(!projectId||!tagId)return alert('Selecciona proyecto y tipología.');
  state.timer={projectId,tagId,desc:$('#tDesc').value.trim(),startedAt:Date.now(),runningSince:Date.now(),accumulatedMs:0,pausedReason:null};
  lastActivity=Date.now();persistTimer();renderRegistro();
}
function pauseTimer(reason){
  const t=state.timer;if(!t||!t.runningSince)return;
  t.accumulatedMs+=Date.now()-t.runningSince;t.runningSince=null;t.pausedReason=reason;
  persistTimer();renderRegistro();
}
function resumeTimer(){
  const t=state.timer;if(!t||t.runningSince)return;
  t.runningSince=Date.now();t.pausedReason=null;lastActivity=Date.now();
  persistTimer();renderRegistro();
}
function stopTimer(){
  const t=state.timer;if(!t)return;
  const min=Math.max(1,Math.round(elapsedMs(t)/60000));
  clearTimerStorage();
  api('save_entry',{date:todayISO(),projectId:String(t.projectId),tagId:String(t.tagId),desc:t.desc,minutes:min})
    .then(()=>{state.timer=null;persistTimer();renderRegistro();})
    .catch(()=>{state.timer=t;persistTimer();renderRegistro();});
  state.timer=null;renderRegistro();
}
function clearTimerStorage(){localStorage.removeItem(TKEY);}
function cancelTimer(){
  if(!confirm('¿Descartar la sesión en curso? Se perderá el tiempo registrado.'))return;
  state.timer=null;clearTimerStorage();renderRegistro();
}
function restoreTimer(){
  try{
    const t=JSON.parse(localStorage.getItem(TKEY));
    if(t&&t.startedAt&&proj(t.projectId)&&tag(t.tagId))state.timer=t;
  }catch(e){}
}
function checkIdle(){
  const t=state.timer;
  if(!t||!t.runningSince||!state.settings.autoPause)return;
  if(document.hidden||!document.hasFocus())return;
  if(Date.now()-lastActivity>=Math.max(1,state.settings.autoPauseMin)*60000)pauseTimer('auto');
}
function setAutoPause(){
  state.settings.autoPause=$('#apOn').checked;
  state.settings.autoPauseMin=Math.min(120,Math.max(1,+$('#apMin').value||10));
  $('#apMin').value=state.settings.autoPauseMin;
  api('save_settings',{autoPause:state.settings.autoPause,autoPauseMin:state.settings.autoPauseMin}).catch(()=>{});
}
function tick(){
  const t=state.timer;
  if(t&&t.runningSince&&view==='registro')$('#timerClock').textContent=fmtClock(elapsedMs(t));
  checkIdle();
}

/* ---------- inicios rápidos ---------- */
function addLauncher(){
  const projectId=$('#lProj').value,tagId=$('#lTag').value;
  if(!projectId||!tagId)return alert('Elige proyecto y tipología para el acceso.');
  api('save_launcher',{name:$('#lName').value.trim(),projectId,tagId,url:$('#lUrl').value.trim()})
    .then(()=>{$('#lName').value='';$('#lUrl').value='';})
    .catch(()=>{});
}
function delLauncher(id){
  if(!confirm('¿Eliminar este acceso rápido?'))return;
  api('delete_launcher',{id}).catch(()=>{});
}
function openUrl(url){
  const a=document.createElement('a');
  a.href=url;a.target='_blank';a.rel='noopener';
  document.body.appendChild(a);a.click();a.remove();
}
function runLauncher(id){
  const l=state.launchers.find(x=>String(x.id)===String(id));if(!l)return;
  state.timer={projectId:String(l.projectId),tagId:String(l.tagId),desc:l.name,startedAt:Date.now(),runningSince:Date.now(),accumulatedMs:0,pausedReason:null};
  lastActivity=Date.now();persistTimer();renderRegistro();
  if(l.url)openUrl(l.url);
}
function testLauncher(id){
  const l=state.launchers.find(x=>String(x.id)===String(id));
  if(l&&l.url)openUrl(l.url);
}

/* ---------- entradas ---------- */
function saveManual(){
  const date=$('#mDate').value||todayISO();
  const projectId=$('#mProj').value,tagId=$('#mTag').value;
  const min=(+$('#mH').value||0)*60+(+$('#mMin').value||0);
  if(!projectId||!tagId)return alert('Selecciona proyecto y tipología.');
  if(min<=0)return alert('La duración debe ser mayor que cero.');
  const payload={date,projectId,tagId,desc:$('#mDesc').value.trim(),minutes:min};
  if(editingEntryId)payload.id=editingEntryId;
  api('save_entry',payload).then(()=>cancelEditEntry(true)).catch(()=>{});
}
function editEntry(id){
  const e=state.entries.find(x=>String(x.id)===String(id));if(!e)return;
  editingEntryId=e.id;
  $('#manualTitle').textContent='Editar entrada';
  $('#mBtn').textContent='Guardar cambios';
  $('#mCancel').style.display='';
  $('#mDate').value=e.date;$('#mProj').value=String(e.projectId);$('#mTag').value=String(e.tagId);
  $('#mH').value=Math.floor(e.minutes/60);$('#mMin').value=e.minutes%60;$('#mDesc').value=e.desc;
  $('.wrap').scrollIntoView({behavior:'smooth'});
}
function cancelEditEntry(keep){
  editingEntryId=null;
  $('#manualTitle').textContent='Añadir entrada manual';
  $('#mBtn').textContent='Añadir';
  $('#mCancel').style.display='none';
  $('#mDate').value=todayISO();$('#mH').value=0;$('#mMin').value=30;$('#mDesc').value='';
  if(!keep)renderRegistro();
}
function deleteEntry(id){
  if(!confirm('¿Eliminar esta entrada?'))return;
  api('delete_entry',{id}).catch(()=>{});
}

/* ---------- proyectos ---------- */
function onNameInput(){
  if(!slugDirty&&!editingProjectId){$('#projSlug').value=slugify($('#projName').value);updateSlugPreview();}
}
function updateSlugPreview(){
  const s=$('#projSlug').value.trim();
  const pub=$('#projPublic').checked;
  $('#slugPreview').innerHTML='Enlace: <b>'+(s?esc(publicURL(s)):'—')+'</b>'+(pub?'':' <span class="muted">(oculto hasta que marques "Visible públicamente")</span>');
}
function addPlanRow(){
  const tagId=$('#planTag').value,h=+$('#planHours').value;
  if(!tagId||!h||h<=0)return alert('Indica tipología y horas (> 0).');
  planDraft[tagId]=h;
  $('#planHours').value='';
  renderPlanDraft();
}
function removePlanRow(tagId){delete planDraft[tagId];renderPlanDraft();}
function renderPlanDraft(){
  const rows=Object.entries(planDraft);
  $('#planDraft').innerHTML=rows.length
    ? rows.map(([tid,h])=>`<span class="chip" style="margin:2px">${esc(tagName(tid))}: ${h} h <b style="cursor:pointer;color:var(--bad)" onclick="removePlanRow('${tid}')">✕</b></span>`).join('')
    : '<span class="muted" style="font-size:12px">Sin horas planificadas todavía.</span>';
}
function saveProject(){
  const name=$('#projName').value.trim();
  if(!name)return alert('El proyecto necesita un nombre.');
  const payload={
    id:editingProjectId||0,
    name,
    color:$('#projColor').value,
    slug:$('#projSlug').value.trim(),
    isPublic:$('#projPublic').checked,
    plan:planDraft
  };
  api('save_project',payload).then(()=>cancelEditProject(true)).catch(()=>{});
}
function editProject(id){
  const p=proj(id);if(!p)return;
  editingProjectId=p.id;planDraft={...p.plan};slugDirty=true;
  $('#projFormTitle').textContent='Editar proyecto';
  $('#projCancel').style.display='';
  $('#projName').value=p.name;$('#projColor').value=p.color;
  $('#projPublic').checked=!!p.isPublic;$('#projSlug').value=p.slug||slugify(p.name);
  fillTagSelect('#planTag');renderPlanDraft();updateSlugPreview();
  window.scrollTo({top:0,behavior:'smooth'});
}
function cancelEditProject(keep){
  editingProjectId=null;planDraft={};slugDirty=false;
  $('#projFormTitle').textContent='Nuevo proyecto';
  $('#projCancel').style.display='none';
  $('#projName').value='';$('#planHours').value='';
  $('#projPublic').checked=false;$('#projSlug').value='';updateSlugPreview();
  if(!keep){renderPlanDraft();renderProyectos();}
}
function toggleArchive(id){
  const p=proj(id);if(!p)return;
  api('archive_project',{id:p.id,archived:!p.archived}).catch(()=>{});
}
function deleteProject(id){
  const p=proj(id);if(!p)return;
  const n=state.entries.filter(e=>String(e.projectId)===String(p.id)).length;
  const msg=n?`Se eliminarán también sus ${n} entradas. Se recomienda archivar en su lugar. ¿Continuar?`:'¿Eliminar este proyecto?';
  if(!confirm(msg))return;
  api('delete_project',{id:p.id}).catch(()=>{});
}

/* ---------- tipologías ---------- */
function addTag(){
  const name=$('#tagName').value.trim();
  if(!name)return;
  api('save_tag',{name}).then(()=>{$('#tagName').value='';}).catch(()=>{});
}
function renameTag(id,name){
  name=String(name||'').trim();
  const old=tag(id);
  if(!old)return renderTipologias();
  if(!name){renderTipologias();return;}
  if(name===old.name)return;
  api('save_tag',{id:old.id,name}).catch(()=>{});
}
function deleteTag(id){
  const t=tag(id);if(!t)return;
  const used=state.entries.filter(e=>String(e.tagId)===String(t.id)).length;
  if(used)return alert('Esta tipología tiene '+used+' entradas asociadas. Edítalas o elimínalas antes de borrarla.');
  if(!confirm('Se quitará también de los planes de proyecto y accesos. ¿Eliminar tipología?'))return;
  api('delete_tag',{id:t.id}).catch(()=>{});
}
function wipeAll(){
  if(!confirm('¿Borrar TODOS los datos del servidor (proyectos, entradas, planes y tipologías)? No se puede deshacer.'))return;
  if(!confirm('Confirmación final: se perderá todo el historial. ¿Continuar?'))return;
  api('wipe_all').catch(()=>{});
}

/* ---------- dashboard ---------- */
function applyPreset(p){
  const f=state.filters||{preset:'month'};state.filters=f;f.preset=p;f.from='';f.to='';
  const now=new Date(),y=now.getFullYear(),m=now.getMonth();
  if(p==='week'){const mo=mondayOf(now);const su=new Date(mo);su.setDate(su.getDate()+6);f.from=toISO(mo);f.to=toISO(su);}
  if(p==='month'){f.from=toISO(new Date(y,m,1));f.to=toISO(new Date(y,m+1,0));}
  if(p==='lastmonth'){f.from=toISO(new Date(y,m-1,1));f.to=toISO(new Date(y,m,0));}
  renderDashboard();
}
function setCustomFrom(v){state.filters.from=v;state.filters.preset='custom';renderDashboard();}
function setCustomTo(v){state.filters.to=v;state.filters.preset='custom';renderDashboard();}

function filteredEntries(){
  const f=state.filters||{};
  return state.entries.filter(e=>
    (!f.from||e.date>=f.from)&&(!f.to||e.date<=f.to)&&
    (!f.project||String(e.projectId)===String(f.project))&&(!f.tag||String(e.tagId)===String(f.tag)));
}
function planRows(){
  const f=state.filters||{},out=[];
  state.projects.filter(p=>!f.project||String(p.id)===String(f.project)).forEach(p=>{
    Object.entries(p.plan||{}).forEach(([tid,h])=>{
      if(h>0&&(!f.tag||String(tid)===String(f.tag)))out.push({projectId:p.id,tagId:tid,plan:h*60});
    });
  });
  return out;
}

function renderDashboard(){
  const f=state.filters||{};
  document.querySelectorAll('.fchip').forEach(b=>b.classList.toggle('active',b.dataset.p===f.preset));
  $('#fFrom').value=f.from||'';$('#fTo').value=f.to||'';
  $('#fProj').innerHTML='<option value="">Todos los proyectos</option>'+
    state.projects.map(p=>`<option value="${p.id}" ${String(f.project)===String(p.id)?'selected':''}>${esc(p.name)}${p.archived?' (arch.)':''}</option>`).join('');
  $('#fTag').innerHTML='<option value="">Todas las tipologías</option>'+
    state.tags.map(t=>`<option value="${t.id}" ${String(f.tag)===String(t.id)?'selected':''}>${esc(t.name)}</option>`).join('');

  const ents=filteredEntries();
  const realMin=ents.reduce((a,e)=>a+e.minutes,0);
  const realMap={};
  ents.forEach(e=>{const k=e.projectId+'|'+e.tagId;realMap[k]=(realMap[k]||0)+e.minutes;});
  const planMin=planRows().reduce((a,r)=>a+r.plan,0);
  const pct=planMin>0?Math.round(realMin/planMin*100):null;
  $('#kpis').innerHTML=`
    <div class="stat"><div class="n">${fmtH(realMin)}</div><div class="l">Horas registradas</div></div>
    <div class="stat"><div class="n">${fmtH(planMin)}</div><div class="l">Horas planificadas</div></div>
    <div class="stat"><div class="n" style="color:${pct===null?'var(--muted)':pctColor(pct)}">${pct===null?'—':pct+'%'}</div><div class="l">Cumplimiento</div></div>
    <div class="stat"><div class="n">${ents.length}</div><div class="l">Entradas</div></div>`;

  const byProj={};
  planRows().forEach(r=>{byProj[r.projectId]=byProj[r.projectId]||{plan:0,real:0};byProj[r.projectId].plan+=r.plan;});
  Object.entries(realMap).forEach(([k,min])=>{
    const[pid]=k.split('|');
    byProj[pid]=byProj[pid]||{plan:0,real:0};byProj[pid].real+=min;
  });
  const pKeys=Object.keys(byProj);
  $('#chartProjects').innerHTML=pKeys.length?(()=>{
    const max=Math.max(...pKeys.map(k=>Math.max(byProj[k].plan,byProj[k].real)));
    return pKeys.map(pid=>{
      const p=proj(pid)||{name:'(proyecto eliminado)',color:'#888'};
      const d=byProj[pid],pc=d.plan?Math.round(d.real/d.plan*100):null;
      return `<div class="prow">
        <div class="prow-head"><span class="dot" style="background:${p.color}"></span>${esc(p.name)}
          ${pc===null?'':`<span class="pct" style="color:${pctColor(pc)}">${pc}%</span>`}</div>
        <div class="bar-row"><span class="bl">Plan</span><div class="bar-track"><div class="bar plan" style="width:${d.plan/max*100}%"></div></div><span class="bv">${fmtH(d.plan)}</span></div>
        <div class="bar-row"><span class="bl">Real</span><div class="bar-track"><div class="bar" style="width:${d.real/max*100}%;background:${p.color}"></div></div><span class="bv">${fmtH(d.real)}</span></div>
      </div>`;
    }).join('');
  })():'<div class="empty">Sin datos en el rango seleccionado.</div>';

  let from=f.from,to=f.to;
  if(!from||!to){
    const dates=state.entries.map(e=>e.date).sort();
    if(!from)from=dates[0]||todayISO();
    if(!to)to=todayISO();
  }
  let d0=parseISO(from),d1=parseISO(to);
  if(d1-d0>366*864e5){d0=new Date(d1);d0.setDate(d0.getDate()-365);}
  const perDay={};let n=0;
  for(let d=new Date(d0);d<=d1&&n<400;d.setDate(d.getDate()+1),n++){
    const iso=toISO(d);
    perDay[iso]=ents.filter(e=>e.date===iso).reduce((a,e)=>a+e.minutes,0);
  }
  const days=Object.entries(perDay);
  const dMax=Math.max(1,...days.map(([,v])=>v));
  const labEvery=days.length>20?7:1;
  $('#chartDays').innerHTML=days.length
    ?`<div class="daychart">${days.map(([iso,min],i)=>`
      <div class="dcol" title="${iso} — ${fmtDur(min)}">
        <div class="dfill" style="height:${min/dMax*82}%;${min?'':'background:var(--border)'}"></div>
        <span class="dlab">${i%labEvery===0||min?parseISO(iso).getDate():''}</span>
      </div>`).join('')}</div>`
    :'<div class="empty">Sin datos.</div>';

  const keys=new Set([...planRows().map(r=>r.projectId+'|'+r.tagId),...Object.keys(realMap)]);
  const rows=[...keys].map(k=>{
    const[pid,tid]=k.split('|');
    const plan=(planRows().find(r=>String(r.projectId)===pid&&String(r.tagId)===tid)||{plan:0}).plan;
    const real=realMap[k]||0;
    const pc=plan?real/plan*100:null;
    return{pid,tid,plan,real,pc};
  }).sort((a,b)=>(proj(a.pid)?.name||'').localeCompare(proj(b.pid)?.name||''));
  const tPlan=rows.reduce((a,r)=>a+r.plan,0),tReal=rows.reduce((a,r)=>a+r.real,0);
  $('#detailTable').innerHTML=rows.length?`
    <table><thead><tr><th>Proyecto</th><th>Tipología</th><th class="num">Plan</th><th class="num">Real</th><th class="num">Dif.</th><th class="num">Cumpl.</th></tr></thead>
    <tbody>${rows.map(r=>{
      const diff=r.real-r.plan;
      const ds=(diff>=0?'+':'−')+fmtH(Math.abs(diff));
      return`<tr>
        <td><span class="dot" style="background:${proj(r.pid)?.color||'#888'}"></span> ${esc(proj(r.pid)?.name||'—')}</td>
        <td>${esc(tagName(r.tid))}</td>
        <td class="num">${fmtH(r.plan)}</td>
        <td class="num">${fmtH(r.real)}</td>
        <td class="num" style="color:${diff>0?'var(--warn)':diff<0?'var(--muted)':'inherit'}">${ds}</td>
        <td class="num" style="color:${r.pc===null?'var(--muted)':pctColor(r.pc)};font-weight:700">${r.pc===null?'—':Math.round(r.pc)+'%'}</td>
      </tr>`;}).join('')}</tbody>
    <tfoot><tr><td colspan="2">Total</td><td class="num">${fmtH(tPlan)}</td><td class="num">${fmtH(tReal)}</td><td></td>
    <td class="num" style="color:${tPlan?pctColor(tReal/tPlan*100):'var(--muted)'}">${tPlan?Math.round(tReal/tPlan*100)+'%':'—'}</td></tr></tfoot></table>`
    :'<div class="empty">Sin datos en el rango seleccionado.</div>';
}

/* ---------- CSV / JSON ---------- */
function exportCSV(){
  const ents=filteredEntries();
  if(!ents.length)return alert('No hay entradas que exportar con los filtros actuales.');
  const rows=[['Fecha','Proyecto','Tipología','Descripción','Minutos','Horas']];
  ents.forEach(e=>rows.push([e.date,proj(e.projectId)?.name||'',tagName(e.tagId),e.desc||'',e.minutes,(e.minutes/60).toString().replace('.',',')]));
  const csv='\ufeff'+rows.map(r=>r.map(c=>'"'+String(c).replace(/"/g,'""')+'"').join(';')).join('\n');
  const a=document.createElement('a');
  a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'}));
  a.download='timetrack_entradas_'+todayISO()+'.csv';
  a.click();URL.revokeObjectURL(a.href);
}
function exportJSON(){
  const a=document.createElement('a');
  a.href=URL.createObjectURL(new Blob([JSON.stringify(state,null,2)],{type:'application/json'}));
  a.download='timetrack-backup-'+todayISO()+'.json';
  a.click();URL.revokeObjectURL(a.href);
}
function importJSONPick(){
  const i=document.createElement('input');i.type='file';i.accept='.json,application/json';
  i.onchange=()=>{
    const f=i.files[0];if(!f)return;
    const r=new FileReader();
    r.onload=()=>{
      try{
        const s=JSON.parse(r.result);
        if(!s||!s.tags)throw 0;
        if(!confirm('¿Reemplazar TODOS los datos del servidor por los de "'+f.name+'"? Se borrarán proyectos, entradas, planes, tipologías y accesos actuales.'))return;
        api('import_json',{raw:r.result}).then(()=>alert('Datos importados correctamente.')).catch(()=>{});
      }catch(e){alert('El archivo no es un respaldo válido de TimeTrack.');}
    };
    r.readAsText(f);
  };
  i.click();
}

/* ---------- renders ---------- */
function fillTagSelect(sel){
  const el=$(sel);const cur=el.value;
  el.innerHTML='<option value="">—</option>'+state.tags.map(t=>`<option value="${t.id}">${esc(t.name)}</option>`).join('');
  if(cur&&tag(cur))el.value=cur;
}
function fillProjSelect(sel,onlyActive){
  const el=$(sel);const cur=el.value;
  el.innerHTML='<option value="">—</option>'+state.projects
    .filter(p=>!onlyActive||!p.archived)
    .map(p=>`<option value="${p.id}">${esc(p.name)}</option>`).join('');
  if(cur&&proj(cur))el.value=cur;
}
function renderRegistro(){
  fillProjSelect('#tProj',true);fillTagSelect('#tTag');
  fillProjSelect('#mProj');fillTagSelect('#mTag');
  fillProjSelect('#lProj',true);fillTagSelect('#lTag');
  $('#apOn').checked=!!state.settings.autoPause;
  $('#apMin').value=state.settings.autoPauseMin;
  if(!$('#mDate').value)$('#mDate').value=todayISO();

  if(state.timer){
    const t=state.timer;
    $('#timerIdle').style.display='none';
    if(t.runningSince){
      $('#timerRunning').style.display='';
      $('#timerPaused').style.display='none';
      $('#timerInfo').textContent=(proj(t.projectId)?.name||'—')+' · '+tagName(t.tagId)+(t.desc?' — '+t.desc:'');
      $('#timerClock').textContent=fmtClock(elapsedMs(t));
    }else{
      $('#timerRunning').style.display='none';
      $('#timerPaused').style.display='';
      $('#pauseReason').textContent=t.pausedReason==='auto'?'Pausa automática por inactividad':'En pausa';
      $('#pauseClock').textContent=fmtClock(t.accumulatedMs);
    }
  }else{
    $('#timerIdle').style.display='';
    $('#timerRunning').style.display='none';
    $('#timerPaused').style.display='none';
  }

  $('#launchList').innerHTML=state.launchers.length?state.launchers.map(l=>`
    <div class="launch">
      <button class="btn small" onclick="runLauncher('${l.id}')">▶ Iniciar</button>
      <div class="launch-main"><b>${esc(l.name)}</b>
        <div style="margin-top:3px"><span class="chip">${esc(proj(l.projectId)?.name||'—')}</span> <span class="chip">${esc(tagName(l.tagId))}</span></div>
        ${l.url?`<span class="url">${esc(l.url)}</span>`:''}</div>
      ${l.url?`<button class="btn ghost small" onclick="testLauncher('${l.id}')">Probar</button>`:''}
      <button class="btn danger small" onclick="delLauncher('${l.id}')">✕</button>
    </div>`).join('')
    :'<div class="empty">Sin accesos rápidos. Crea uno abajo para arrancar el cronómetro (y abrir su URL) con un clic.</div>';

  const groups={};
  [...state.entries].sort((a,b)=>b.date.localeCompare(a.date)||(b.createdAt||0)-(a.createdAt||0))
    .forEach(e=>{(groups[e.date]=groups[e.date]||[]).push(e);});
  const dates=Object.keys(groups);
  $('#entriesList').innerHTML=dates.length?dates.map(d=>{
    const lbl=parseISO(d).toLocaleDateString('es',{weekday:'short',day:'numeric',month:'short'});
    return`<div class="day-head">${lbl}</div>`+groups[d].map(e=>{
      const p=proj(e.projectId);
      return`<div class="entry">
        <span class="dot" style="background:${p?.color||'#888'}"></span>
        <div class="entry-main"><b>${esc(p?.name||'—')}</b> <span class="chip">${esc(tagName(e.tagId))}</span>
          ${e.desc?`<span class="desc">${esc(e.desc)}</span>`:''}</div>
        <div class="entry-side"><b>${fmtDur(e.minutes)}</b>
          <button class="btn ghost small" onclick="editEntry('${e.id}')">Editar</button>
          <button class="btn danger small" onclick="deleteEntry('${e.id}')">Borrar</button></div>
      </div>`;
    }).join('');
  }).join(''):'<div class="empty">Sin entradas todavía. Inicia el cronómetro o añade una manual.</div>';
}
function renderProyectos(){
  fillTagSelect('#planTag');
  $('#projList').innerHTML=state.projects.length?state.projects.map(p=>{
    const chips=Object.entries(p.plan||{}).map(([tid,h])=>`<span class="chip">${esc(tagName(tid))}: ${h} h</span>`).join('');
    const total=Object.values(p.plan||{}).reduce((a,b)=>a+b,0);
    const pub=p.isPublic?`<span class="chip" style="color:var(--ok);border-color:var(--ok)">Público</span> <button class="btn ghost small" onclick="window.open('${publicURL(p.slug)}','_blank')">↗ Ver panel</button>`:'';
    return`<div class="proj">
      <span class="dot" style="background:${p.color};width:12px;height:12px"></span>
      <div class="proj-main"><span class="proj-name">${esc(p.name)}</span>
        ${p.archived?' <span class="chip">Archivado</span>':''} ${pub}
        <div class="plan-chips">${chips||'<span class="muted" style="font-size:12px">Sin plan definido</span>'}
        ${total?`<span class="chip" style="font-weight:700">Total: ${total} h</span>`:''}</div></div>
      <div style="display:flex;gap:6px">
        <button class="btn ghost small" onclick="editProject('${p.id}')">Editar</button>
        <button class="btn ghost small" onclick="toggleArchive('${p.id}')">${p.archived?'Reactivar':'Archivar'}</button>
        <button class="btn danger small" onclick="deleteProject('${p.id}')">Eliminar</button>
      </div></div>`;
  }).join(''):'<div class="empty">Crea tu primer proyecto con su plan de horas.</div>';
}
function renderTipologias(){
  $('#tagList').innerHTML=state.tags.length?state.tags.map(t=>`
    <div class="tag-row">
      <input value="${esc(t.name)}" onchange="renameTag('${t.id}',this.value)">
      <button class="btn danger small" onclick="deleteTag('${t.id}')">Eliminar</button>
    </div>`).join(''):'<div class="empty">No hay tipologías.</div>';
}
function renderAll(){
  renderRegistro();renderProyectos();renderTipologias();
  if(view==='dashboard')renderDashboard();
}

/* ---------- init ---------- */
applyTheme();
setSync('ok');
restoreTimer();
applyPreset('month');
renderAll();
setInterval(tick,1000);
window.__ttReady=true;
</script>
</body>
</html>