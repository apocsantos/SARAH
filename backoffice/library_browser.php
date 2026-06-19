<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_login();
app_header('SARAH Biblioteca');
?>
<h1>Biblioteca de pictogramas</h1>
<p><a href="dashboard.php">← Dashboard</a></p>
<div class="card">
<label>Pesquisar</label><input id="q" placeholder="comer, agua, casa...">
<button class="primary" onclick="search(true)">Pesquisar</button><button onclick="search(false)">Mais</button>
<p id="status" class="muted">Pronto.</p>
<div id="grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px"></div>
</div>
<script>
let offset=0, limit=80, assets={};
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function search(reset){
 const q=document.getElementById('q').value.trim();
 if(q.length<2){document.getElementById('status').textContent='Escreve pelo menos 2 letras.';return;}
 if(reset){offset=0;assets={};}
 document.getElementById('status').textContent='A pesquisar...';
 const r=await fetch('api/list_icons.php?q='+encodeURIComponent(q)+'&offset='+offset+'&limit='+limit+'&_='+Date.now(),{cache:'no-store'});
 const d=await r.json();
 (d.items||[]).forEach(a=>assets[a.path]=a);
 offset+=d.returned||0;
 render();
 document.getElementById('status').textContent='Carregados: '+Object.keys(assets).length+' / digitalizados: '+(d.scanned||0);
}
function render(){
 const g=document.getElementById('grid');g.innerHTML='';
 Object.values(assets).forEach(a=>{
  const div=document.createElement('div');
  div.className='card';
  div.style.margin='0';
  div.innerHTML='<div style="height:100px;display:flex;align-items:center;justify-content:center;background:#0b1220;border-radius:12px;overflow:hidden">'+(a.content||'🖼️')+'</div><b>'+esc(a.name)+'</b><p class="muted">'+esc(a.path)+'</p>';
  g.appendChild(div);
 });
}
</script>
<?php app_footer(); ?>
