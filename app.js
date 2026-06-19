/* Comunicador AAC — PWA Offline + Updates (PT-PT)
   - UI: estilo grelha (como no exemplo do utilizador)
   - Dados: seed.json (local) + seed.updated (IndexedDB) (remoto)
   - Updates: URL configurável + baseUrl opcional para ícones
   - Cache: service worker (app shell) + cache dinâmico para ícones/seed remoto
*/
const $ = (s, r=document) => r.querySelector(s);

const DB_NAME = "aac_ptpt_db_v1";
const DB_STORE = "kv";

const CACHE_DYNAMIC = "aac-dynamic-v2";
const DEFAULT_UPDATE_URL = "https://sarah.aeaveromar.pt/backoffice/storage/seed.json";
const DEFAULT_BASE_URL = "https://sarah.aeaveromar.pt/backoffice/storage/";

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

function normalizeSeedShape(seed){
  seed = seed || {};
  const voz = seed.voz || seed.defaults?.voice || { lang:'pt-PT', rate:1, pitch:1 };
  return {
    ...seed,
    versao: Number(seed.versao || seed.version || 1),
    idioma: seed.idioma || seed.lang || 'pt-PT',
    voz: {
      lang: voz.lang || 'pt-PT',
      rate: Number(voz.rate ?? 1),
      pitch: Number(voz.pitch ?? 1),
    },
    categorias: seed.categorias || seed.pages || [],
    itens: seed.itens || seed.items || [],
    assets_base_url: seed.assets_base_url || seed.assetsBaseUrl || seed.base_url || ''
  };
}

function mergeWithBase(baseSeed, customSeed){
  const base = normalizeSeedShape(baseSeed);
  const custom = normalizeSeedShape(customSeed);
  const cats = [...(base.categorias || [])];
  for(const c of (custom.categorias || [])){
    const id = c.id || c.key || c.page;
    if(!id) continue;
    const normalized = { id, nome: c.nome || c.label || c.name || id, emoji: c.emoji || '' };
    const idx = cats.findIndex(x => (x.id || x.key || x.page) === id);
    if(idx >= 0) cats[idx] = { ...cats[idx], ...normalized }; else cats.push(normalized);
  }
  const items = [...(base.itens || [])];
  for(const i of (custom.itens || [])){
    const id = i.id || i.key || String((i.texto || i.text || i.label || '')).toLowerCase().replace(/\W+/g,'_');
    const texto = i.texto || i.text || i.label || '';
    if(!id || !texto) continue;
    const normalized = { id, texto, categoria: i.categoria || i.page || i.category || 'base', icone: i.icone || i.icon || '' };
    const idx = items.findIndex(x => (x.id || x.key) === id);
    if(idx >= 0) items[idx] = { ...items[idx], ...normalized }; else items.push(normalized);
  }
  return {
    ...base,
    ...custom,
    categorias: cats,
    itens: items,
    voz: custom.voz || base.voz,
    assets_base_url: custom.assets_base_url || base.assets_base_url || ''
  };
}

async function loadSeed(){
  const bundled = await loadBundledSeed();
  const updated = await dbGet("seed.updated");
  if(updated && (updated.itens || updated.items || updated.categorias || updated.pages)) return mergeWithBase(bundled, updated);
  return normalizeSeedShape(bundled);
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
  const baseUrl = seed.assets_base_url || (await dbGet("updates.baseUrl")) || "";
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

  // Administração/pacotes: triplo clique no menu hambúrguer.
  // Mantém a UI normal limpa para a criança/utilizador, mas preserva todas as funções antigas.
  const badge = $(".badge");
  let taps = 0;
  let tapTimer = null;
  async function openAdminDialog(){
    const updateUrl = $("#updateUrl");
    const baseUrlInput = $("#baseUrl");
    if(updateUrl) updateUrl.value = (await dbGet("updates.url")) || DEFAULT_UPDATE_URL;
    if(baseUrlInput) baseUrlInput.value = (await dbGet("updates.baseUrl")) || DEFAULT_BASE_URL;
    dlgMore.showModal();
  }
  badge.addEventListener("click", () => {
    taps++;
    clearTimeout(tapTimer);
    if(taps >= 3){
      taps = 0;
      openAdminDialog();
      return;
    }
    tapTimer = setTimeout(() => { taps = 0; }, 650);
  });

  async function checkUpdates(force=false){
    const url = (($("#updateUrl")?.value) || (await dbGet("updates.url")) || DEFAULT_UPDATE_URL).trim();
    const configuredBase = (($("#baseUrl")?.value) || (await dbGet("updates.baseUrl")) || DEFAULT_BASE_URL).trim();
    if(!url){ showToast("Defina o URL de atualização do seed.json."); return; }

    try{
      const res = await fetch(url, { cache:"no-store", mode:"cors" });
      if(!res.ok) throw new Error("HTTP " + res.status);
      const remoteRaw = await res.json();
      if(!remoteRaw.assets_base_url && configuredBase) remoteRaw.assets_base_url = configuredBase;

      const current = await loadSeed();
      const baseSeed = await loadBundledSeed();
      const remoteSeed = mergeWithBase(baseSeed, remoteRaw);

      const remoteVer = Number(remoteSeed.versao || remoteSeed.version || 0);
      const currentVer = Number(current.versao || current.version || 0);
      const remoteStamp = String(remoteSeed.updated_at || remoteSeed.exportedAt || remoteSeed.exportadoEm || "");
      const currentStamp = String(current.updated_at || current.exportedAt || current.exportadoEm || "");

      const shouldUpdate = force || (remoteVer > currentVer) || (remoteVer === currentVer && remoteStamp && remoteStamp !== currentStamp);
      if(!shouldUpdate){ showToast("Sem atualizações."); return; }

      await dbSet("updates.url", url);
      await dbSet("updates.baseUrl", remoteSeed.assets_base_url || configuredBase);
      await dbSet("seed.updated", remoteSeed);
      await prefetchIcons(remoteSeed, remoteSeed.assets_base_url || configuredBase);
      showToast("Pacote atualizado! A recarregar…");
      setTimeout(()=>location.reload(), 700);
    }catch(e){
      showToast("Falha na atualização: " + (e?.message || e));
    }
  }

  // Long-press on badge to update (alternative to prompts)
  badge.addEventListener("contextmenu", (e)=>{ e.preventDefault(); checkUpdates(true); });

  const btnSaveUpdateCfg = $("#btnSaveUpdateCfg");
  const btnUpdateNow = $("#btnUpdateNow");
  const btnResetPkg = $("#btnResetPkg");
  if(btnSaveUpdateCfg) btnSaveUpdateCfg.onclick = async () => {
    await dbSet("updates.url", ($("#updateUrl")?.value || DEFAULT_UPDATE_URL).trim());
    await dbSet("updates.baseUrl", ($("#baseUrl")?.value || DEFAULT_BASE_URL).trim());
    showToast("URLs guardados.");
  };
  if(btnUpdateNow) btnUpdateNow.onclick = () => checkUpdates(true);
  if(btnResetPkg) btnResetPkg.onclick = async () => {
    if(!confirm("Limpar pacote local e voltar às categorias base incluídas na app?")) return;
    await dbSet("seed.updated", null);
    showToast("Pacote local limpo. A recarregar…");
    setTimeout(()=>location.reload(), 700);
  };

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
