/* Comunicador AAC — PWA Offline + Updates (PT-PT)
   - UI: estilo grelha (como no exemplo do utilizador)
   - Dados: seed.json (local) + seed.updated (IndexedDB) (remoto)
   - Updates: URL configurável + baseUrl opcional para ícones
   - Cache: service worker (app shell) + cache dinâmico para ícones/seed remoto
*/
const $ = (s, r=document) => r.querySelector(s);

const DB_NAME = "aac_ptpt_db_v1";
const DB_STORE = "kv";

const CACHE_DYNAMIC = "aac-dynamic-v1";

function openDb(){
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, 1);
    req.onupgradeneeded = () => {
      const db = req.result;
      if(!db.objectStoreNames.contains(DB_STORE)) db.createObjectStore(DB_STORE);
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}
async function dbGet(key){
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(DB_STORE, "readonly");
    const st = tx.objectStore(DB_STORE);
    const req = st.get(key);
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}
async function dbSet(key, value){
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(DB_STORE, "readwrite");
    const st = tx.objectStore(DB_STORE);
    const req = st.put(value, key);
    req.onsuccess = () => resolve(true);
    req.onerror = () => reject(req.error);
  });
}

function clamp(v,min,max){ v=Number(v); return Math.min(max, Math.max(min, v)); }

async function ensureVoices(){
  return new Promise(resolve=>{
    const synth=speechSynthesis;
    const v=synth.getVoices();
    if(v && v.length) return resolve(v);
    const on=()=>{ synth.removeEventListener('voiceschanged',on); resolve(synth.getVoices()||[]); };
    synth.addEventListener('voiceschanged',on);
    setTimeout(()=>resolve(synth.getVoices()||[]), 900);
  });
}

async function falar(texto, voz){
  if(!('speechSynthesis' in window)) { alert('Este navegador não suporta voz.'); return; }
  const t = String(texto||'').trim();
  if(!t) return;
  try{ speechSynthesis.cancel(); }catch{}
  await ensureVoices();
  const u = new SpeechSynthesisUtterance(t);
  u.lang = voz.lang || 'pt-PT';
  u.rate = clamp(voz.rate ?? 1, .5, 2);
  u.pitch= clamp(voz.pitch?? 1, .5, 2);
  speechSynthesis.speak(u);
}

function normalizeUrl(base, path){
  if(!path) return "";
  if(/^https?:\/\/+/i.test(path)) return path;
  if(base){
    return base.replace(/\/+$/,'') + '/' + path.replace(/^\/+/,'');
  }
  return path;
}

async function prefetchIcons(seed, baseUrl){
  // Cache icons so they work offline
  const cache = await caches.open(CACHE_DYNAMIC);
  const icons = (seed.itens||seed.items||[]).map(it => normalizeUrl(baseUrl, it.icone || it.icon)).filter(Boolean);
  const uniq = Array.from(new Set(icons));
  for(const url of uniq){
    try{
      const req = new Request(url, { cache: "no-store" });
      const res = await fetch(req);
      if(res.ok) await cache.put(req, res.clone());
    }catch{}
  }
}

async function loadBundledSeed(){
  const res = await fetch('./data/seed.json', { cache:'no-store' });
  return await res.json();
}

async function loadSeed(){
  const updated = await dbGet("seed.updated");
  if(updated && (updated.itens || updated.items)) return updated;
  return await loadBundledSeed();
}

function iconSvg(kind){
  const common = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"';
  if(kind==="back") return `<svg ${common} fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>`;
  if(kind==="speak")return `<svg ${common} fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5l-5 4H3v6h3l5 4z"/><path d="M15 9a4 4 0 010 6"/><path d="M17.5 6.5a7 7 0 010 11"/></svg>`;
  if(kind==="x")    return `<svg ${common} fill="none" stroke="white" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>`;
  if(kind==="trash")return `<svg ${common} fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 16h10l1-16"/></svg>`;
  if(kind==="kbd")  return `<svg ${common} fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="12" rx="2"/><path d="M7 11h.01M10 11h.01M13 11h.01M16 11h.01M7 15h10"/></svg>`;
  if(kind==="more") return `<svg ${common} fill="none" stroke="white" stroke-width="2" stroke-linecap="round"><path d="M12 6h.01M12 12h.01M12 18h.01"/></svg>`;
  return "";
}

async function registerSW(){
  if(!('serviceWorker' in navigator)) return;
  try{ await navigator.serviceWorker.register('./sw.js', { scope:'./' }); }catch{}
}

function showToast(msg){
  // lightweight: alert on mobile for now
  alert(msg);
}

async function init(){
  await registerSW();

  const seed = await loadSeed();
  const cats = seed.categorias || seed.pages || [];
  const itens = seed.itens || seed.items || [];
  const baseUrl = (await dbGet("updates.baseUrl")) || "";
  const voz = {
    lang: (seed.voz?.lang || seed.defaults?.voice?.lang || 'pt-PT'),
    rate: Number(seed.voz?.rate ?? seed.defaults?.voice?.rate ?? 1.0),
    pitch:Number(seed.voz?.pitch?? seed.defaults?.voice?.pitch?? 1.0),
  };

  const state = {
    seed, cats, itens,
    modo:"menu",
    categoria:null,
    frase:[],
    voz,
    baseUrl,
  };

  const crumb = $("#crumb");
  const phraseBox = $("#phraseBox");
  const grid = $("#grid");
  const speakOnTap = $("#speakOnTap");
  const dlgMore = $("#dlgMore");

  // Dialog controls
  const rate = $("#rate"), pitch = $("#pitch");
  const rateVal = $("#rateVal"), pitchVal = $("#pitchVal");
  const btnCloseMore = $("#btnCloseMore");

  function setCrumb(){
    if(state.modo==="menu"){
      crumb.textContent = "BASE | MENU PRINCIPAL";
    } else {
      const c = (state.cats||[]).find(x => (x.id||x.id) === state.categoria);
      const nome = c?.nome || c?.label || "CATEGORIA";
      crumb.textContent = "BASE | " + nome.toUpperCase();
    }
  }

  function renderPhrase(){
    phraseBox.innerHTML = "";
    if(!state.frase.length){
      const d = document.createElement('div');
      d.style.color = "#64748b";
      d.style.fontWeight = "900";
      d.textContent = "Frase vazia.";
      phraseBox.appendChild(d);
      return;
    }
    state.frase.forEach((x, idx) => {
      const c = document.createElement('div');
      c.className = "chip";
      c.title = "Toque para remover";
      const icon = x.icone ? `<img src="${x.icone}" alt="">` : "";
      c.innerHTML = `${icon}<span>${x.texto}</span>`;
      c.onclick = () => { state.frase.splice(idx,1); renderPhrase(); };
      phraseBox.appendChild(c);
    });
  }

  async function addToPhrase(item){
    const icon = normalizeUrl(state.baseUrl, item.icone || item.icon || "");
    state.frase.push({ texto:item.texto || item.text, icone: icon });
    renderPhrase();
    if(speakOnTap.checked) await falar(item.texto || item.text, state.voz);
  }

  function getBaseShortcuts(){
    // using "base" category items if exist
    const baseItems = state.itens.filter(i => (i.categoria||i.page) === "base");
    return baseItems;
  }

  function getMenuTiles(){
    const actions = [
      { kind:"action", label:"SAIR", icon:"back", onClick:()=>{ state.modo="menu"; state.categoria=null; setCrumb(); renderGrid(); } },
      { kind:"action", label:"LER", icon:"speak", onClick:async()=>{ const txt=state.frase.map(x=>x.texto).join(" ").trim(); if(txt) await falar(txt, state.voz); } },
      { kind:"action", label:"APAGAR SÍMBOLO", icon:"x", onClick:()=>{ state.frase.pop(); renderPhrase(); } },
      { kind:"action", label:"APAGAR TUDO", icon:"trash", danger:true, onClick:()=>{ state.frase=[]; renderPhrase(); } },
      { kind:"action", label:"TECLADO", icon:"kbd", onClick:async()=>{ 
          const t = prompt("Escreve uma palavra ou frase:");
          if(t && t.trim()){
            await addToPhrase({ texto:t.trim(), icone:"" });
          }
        } 
      },
      { kind:"action", label:"MAIS AÇÕES", icon:"more", onClick:()=>dlgMore.showModal() }
    ];

    const categories = state.cats.filter(c => (c.id||c.id) !== "base");
    const shortcuts = getBaseShortcuts();

    const catTiles = categories.slice(0, 12).map(c => ({
      kind:"cat",
      sub:(c.nome || c.label || "").toUpperCase(),
      pic:"",
      onClick:()=>{ state.modo="categoria"; state.categoria = (c.id||c.id); setCrumb(); renderGrid(); }
    }));

    // Fill to 12 with shortcuts
    const shortcutTiles = shortcuts.map(s => ({
      kind:"item",
      sub:(s.texto||s.text),
      pic: normalizeUrl(state.baseUrl, s.icone||s.icon||""),
      onClick:()=>addToPhrase(s)
    }));

    const fill = [...catTiles];
    while(fill.length < 12 && shortcutTiles.length){
      fill.push(shortcutTiles.shift());
    }
    while(fill.length < 12) fill.push({kind:"empty"});

    return [...actions, ...fill];
  }

  function getCategoryTiles(){
    const actions = [
      { kind:"action", label:"SAIR", icon:"back", onClick:()=>{ state.modo="menu"; state.categoria=null; setCrumb(); renderGrid(); } },
      { kind:"action", label:"LER", icon:"speak", onClick:async()=>{ const txt=state.frase.map(x=>x.texto).join(" ").trim(); if(txt) await falar(txt, state.voz); } },
      { kind:"action", label:"APAGAR SÍMBOLO", icon:"x", onClick:()=>{ state.frase.pop(); renderPhrase(); } },
      { kind:"action", label:"APAGAR TUDO", icon:"trash", danger:true, onClick:()=>{ state.frase=[]; renderPhrase(); } },
      { kind:"action", label:"TECLADO", icon:"kbd", onClick:async()=>{ 
          const t = prompt("Escreve uma palavra ou frase:");
          if(t && t.trim()){
            await addToPhrase({ texto:t.trim(), icone:"" });
          }
        } 
      },
      { kind:"action", label:"MAIS AÇÕES", icon:"more", onClick:()=>dlgMore.showModal() }
    ];

    const list = state.itens.filter(i => (i.categoria||i.page) === state.categoria);
    const base = getBaseShortcuts();
    const show = [...list, ...base].slice(0, 12).map(i => ({
      kind:"item",
      sub:(i.texto||i.text),
      pic: normalizeUrl(state.baseUrl, i.icone||i.icon||""),
      onClick:()=>addToPhrase(i)
    }));
    while(show.length < 12) show.push({kind:"empty"});
    return [...actions, ...show];
  }

  function renderGrid(){
    grid.innerHTML = "";
    const tiles = (state.modo==="menu") ? getMenuTiles() : getCategoryTiles();

    tiles.forEach(t => {
      const tile = document.createElement('div');
      tile.className = 'tile' + (t.kind==="action" ? ' action' : '') + (t.danger ? ' danger' : '');
      if(t.kind==="empty"){
        tile.style.opacity = "0.25";
        tile.style.background = "transparent";
        tile.style.borderStyle = "dashed";
        grid.appendChild(tile);
        return;
      }
      if(t.kind==="action"){
        tile.innerHTML = `<div class="icon">${iconSvg(t.icon)}</div><div class="lbl">${t.label}</div>`;
      } else {
        const pic = t.pic ? `<img class="pic" src="${t.pic}" alt="">` : `<div class="icon">🔷</div>`;
        tile.innerHTML = `${pic}<div class="sub">${t.sub}</div>`;
      }
      tile.onclick = () => t.onClick && t.onClick();
      grid.appendChild(tile);
    });
  }

  function syncVoz(){
    rateVal.textContent = Number(rate.value).toFixed(1);
    pitchVal.textContent = Number(pitch.value).toFixed(1);
    state.voz.rate = Number(rate.value);
    state.voz.pitch = Number(pitch.value);
    // persist
    dbSet("voice.rate", state.voz.rate);
    dbSet("voice.pitch", state.voz.pitch);
  }

  // Restore voice sliders
  const savedRate = await dbGet("voice.rate");
  const savedPitch = await dbGet("voice.pitch");
  if(typeof savedRate === "number") state.voz.rate = savedRate;
  if(typeof savedPitch === "number") state.voz.pitch = savedPitch;
  rate.value = String(state.voz.rate);
  pitch.value = String(state.voz.pitch);
  syncVoz();

  rate.oninput = syncVoz;
  pitch.oninput = syncVoz;
  btnCloseMore.onclick = () => dlgMore.close();

  // Updates dialog: reuse "Mais ações" only for voice now; but allow long-press on badge to open updates prompt
  // We'll add a hidden updates configuration accessible via triple-tap on badge (simple, kid-proof)
  const badge = $(".badge");
  let taps = 0;
  let tapTimer = null;
  badge.addEventListener("click", async () => {
    taps++;
    clearTimeout(tapTimer);
    tapTimer = setTimeout(async () => {
      if(taps >= 3){
        const currentUrl = (await dbGet("updates.url")) || "";
        const currentBase = (await dbGet("updates.baseUrl")) || "";
        const url = prompt("URL de atualização (seed.json na web):", currentUrl) || currentUrl;
        const base = prompt("Base URL dos ícones (opcional):", currentBase) || currentBase;
        await dbSet("updates.url", url.trim());
        await dbSet("updates.baseUrl", base.trim());
        showToast("Guardado. Para atualizar, faça 3 toques e confirme 'Atualizar agora'.");
        const doNow = confirm("Atualizar agora?");
        if(doNow){
          await checkUpdates();
        }
      }
      taps = 0;
    }, 420);
  });

  async function checkUpdates(){
    const url = ((await dbGet("updates.url")) || "").trim();
    if(!url){ showToast("Defina primeiro o URL de atualização (3 toques no ícone ≡)."); return; }

    try{
      const res = await fetch(url, { cache:"no-store" });
      if(!res.ok) throw new Error("HTTP " + res.status);
      const remoteSeed = await res.json();

      const current = await loadSeed();
      const remoteVer = Number(remoteSeed.versao || remoteSeed.version || 0);
      const currentVer = Number(current.versao || current.version || 0);
      const remoteStamp = String(remoteSeed.exportedAt || remoteSeed.exportadoEm || "");
      const currentStamp = String(current.exportedAt || current.exportadoEm || "");

      const shouldUpdate = (remoteVer > currentVer) || (remoteVer === currentVer && remoteStamp && remoteStamp !== currentStamp);
      if(!shouldUpdate){
        showToast("Sem atualizações.");
        return;
      }

      await dbSet("seed.updated", remoteSeed);

      const base = ((await dbGet("updates.baseUrl")) || "").trim();
      await prefetchIcons(remoteSeed, base);

      showToast("Atualizado! A recarregar…");
      location.reload();
    }catch(e){
      showToast("Falha na atualização: " + (e?.message || e));
    }
  }

  // Long-press on badge to update (alternative to prompts)
  badge.addEventListener("contextmenu", (e)=>{ e.preventDefault(); checkUpdates(); });

  // initial render
  setCrumb();
  renderPhrase();
  renderGrid();
}

window.addEventListener("load", () => {
  init().catch(err => {
    document.body.innerHTML = `<div style="padding:16px;color:white;font-family:system-ui">
      <b>Erro a iniciar o comunicador.</b><br><br>
      <pre style="white-space:pre-wrap;color:#94a3b8">${String(err)}</pre>
      <br>Nota: abra por http(s) (não file://).
    </div>`;
  });
});
