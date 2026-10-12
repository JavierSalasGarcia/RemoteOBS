<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
session_start();
$logged = !empty($_SESSION['ok']);
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Control OBS</title>
<style>
:root{--bg:#f4f5f7;--card:#fff;--tx:#1b1f24;--mu:#667085;--ok:#18864b;--bad:#c62828;--warn:#b26a00;--bd:#dfe3e8;--ac:#2b59c3}
@media (prefers-color-scheme:dark){:root{--bg:#14171c;--card:#1d2128;--tx:#e8eaed;--mu:#9aa3af;--ok:#3ddc84;--bad:#ff6b6b;--warn:#ffb74d;--bd:#2d333d;--ac:#7aa2ff}}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--tx);font:15px/1.4 system-ui,sans-serif;padding:16px}
main{max-width:980px;margin:auto}
h1{font-size:20px;margin:0 0 4px}h2{font-size:16px;margin:24px 0 8px}
.mu{color:var(--mu);font-size:13px}
.grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(300px,1fr))}
.card{background:var(--card);border:1px solid var(--bd);border-radius:10px;padding:14px}
.dot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:6px;background:var(--bad)}
.dot.on{background:var(--ok)}
.row{display:flex;justify-content:space-between;gap:8px;padding:3px 0;border-bottom:1px dashed var(--bd)}
.row:last-of-type{border:0}
button{font:inherit;padding:8px 12px;border-radius:8px;border:1px solid var(--bd);background:var(--card);color:var(--tx);cursor:pointer}
button.p{background:var(--ac);color:#fff;border-color:var(--ac)}button.d{color:var(--bad)}
.btns{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
label{display:block;font-size:12px;color:var(--mu);margin-top:6px}
input{font:inherit;width:100%;padding:7px;border:1px solid var(--bd);border-radius:6px;background:var(--bg);color:var(--tx)}
.two{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.log{font:12px/1.5 ui-monospace,monospace;max-height:260px;overflow:auto;white-space:pre-wrap}
#login{max-width:320px;margin:20vh auto}
.bad{color:var(--bad)}.okc{color:var(--ok)}.warn{color:var(--warn)}
</style>
</head>
<body>
<main>
<?php if (!$logged): ?>
  <div id="login" class="card">
    <h1>Control OBS</h1>
    <label>Clave</label>
    <input id="pw" type="password" autofocus>
    <div class="btns"><button class="p" id="go">Entrar</button></div>
    <div id="err" class="bad mu"></div>
  </div>
  <script>
  const go=async()=>{
    const r=await fetch('api.php?action=login',{method:'POST',body:JSON.stringify({password:document.getElementById('pw').value})});
    if(r.ok)location.reload();else document.getElementById('err').textContent='Clave incorrecta';
  };
  document.getElementById('go').onclick=go;
  document.getElementById('pw').onkeydown=e=>{if(e.key==='Enter')go()};
  </script>
<?php else: ?>
  <h1>Control OBS <button style="float:right" id="out">Salir</button></h1>
  <div class="mu">Hora del servidor (CDMX): <span id="now">…</span></div>
  <h2>Computadoras</h2>
  <div class="grid" id="agents"></div>
  <h2>Horarios (hora CDMX)</h2>
  <div class="grid" id="events"></div>
  <h2>Registro</h2>
  <div class="card log" id="log"></div>
<script>
let csrf='';
const $=id=>document.getElementById(id);
const esc=s=>String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const post=(a,d)=>fetch('api.php?action='+a,{method:'POST',headers:{'X-CSRF':csrf},body:JSON.stringify(d||{})}).then(r=>r.json());
const fmt=s=>{if(s==null||s<0)return '—';s=Math.round(s);return [s/3600|0,(s/60|0)%60,s%60].map(n=>String(n).padStart(2,'0')).join(':')};
let editing=false;

function agentCard(a){
  const s=a.status||{};
  const live=s.streaming?'<span class="okc">transmitiendo</span>':'<span class="bad">sin transmitir</span>';
  const drift=(s.media_pos!=null&&s.expected_pos!=null&&s.expected_pos>=0)?(s.media_pos-s.expected_pos).toFixed(1)+' s':'—';
  return `<div class="card"><b><span class="dot ${a.online?'on':''}"></span>${esc(a.label)}</b>
  <div class="mu">${a.online?'Agente en línea':'Agente SIN conexión'}${a.seconds_ago!=null?' · visto hace '+a.seconds_ago+' s':''}</div>
  <div class="row"><span>OBS</span><span class="${s.obs_connected?'okc':'bad'}">${s.obs_connected?'conectado':'desconectado'}</span></div>
  <div class="row"><span>YouTube</span><span>${live}</span></div>
  <div class="row"><span>Fase</span><span>${esc(s.phase||'—')}</span></div>
  <div class="row"><span>Evento</span><span>${esc(s.event||'—')}</span></div>
  <div class="row"><span>Video</span><span>${fmt(s.media_pos)} / esperado ${fmt(s.expected_pos)}</span></div>
  <div class="row"><span>Desfase</span><span>${drift}</span></div>
  <div class="row"><span>Modo automático</span><span class="${a.auto?'okc':'warn'}">${a.auto?'ENCENDIDO':'APAGADO'}</span></div>
  <div class="mu">${esc(s.msg||'')}</div>
  <div class="btns">
   <button class="p" onclick="cmd('${a.id}','start')">Iniciar transmisión</button>
   <button class="d" onclick="if(confirm('¿Detener transmisión y apagar el modo automático?'))cmd('${a.id}','stop')">Detener</button>
   <button onclick="cmd('${a.id}','resync')">Resincronizar video</button>
   <button onclick="setAuto('${a.id}',${a.auto?0:1})">${a.auto?'Apagar':'Encender'} automático</button>
  </div></div>`;
}
function eventCard(e){
  return `<div class="card" data-id="${e.id}"><b>${esc(e.name)}</b>
  <div class="two"><div><label>Inicio del video</label><input type="datetime-local" class="f-start" value="${e.start_local}"></div>
  <div><label>Fin (detiene transmisión)</label><input type="datetime-local" class="f-end" value="${e.end_local}"></div></div>
  <div class="two"><div><label>Escena OBS</label><input class="f-scene" value="${esc(e.scene)}"></div>
  <div><label>Fuente multimedia</label><input class="f-input" value="${esc(e.input_name)}"></div></div>
  <label>Minutos de antelación para abrir la transmisión</label><input type="number" min="0" class="f-pre" value="${e.prestart_sec/60}">
  <div class="btns"><button class="p" onclick="saveEvent(this)">Guardar</button></div></div>`;
}
async function cmd(id,c){await post('command',{agent_id:id,cmd:c});load()}
async function setAuto(id,v){await post('set_auto',{agent_id:id,auto:v});load()}
async function saveEvent(btn){
  const c=btn.closest('.card');
  const r=await post('save_event',{id:c.dataset.id,start:c.querySelector('.f-start').value,end:c.querySelector('.f-end').value,
    scene:c.querySelector('.f-scene').value,input_name:c.querySelector('.f-input').value,prestart_min:c.querySelector('.f-pre').value});
  btn.textContent=r.ok?'Guardado ✓':(r.error||'Error');setTimeout(()=>btn.textContent='Guardar',2000);editing=false;
}
async function load(){
  const r=await fetch('api.php?action=state');
  if(r.status===401){location.reload();return}
  const d=await r.json();csrf=d.csrf;
  $('now').textContent=d.now;
  $('agents').innerHTML=d.agents.map(agentCard).join('');
  if(!editing)$('events').innerHTML=d.events.map(eventCard).join('');
  $('log').textContent=d.log.map(l=>`${l.time} [${l.agent_id}] ${l.msg}`).join('\n');
}
$('events').addEventListener('input',()=>editing=true);
$('out').onclick=async()=>{await post('logout');location.reload()};
load();setInterval(load,5000);
</script>
<?php endif; ?>
</main>
</body>
</html>
