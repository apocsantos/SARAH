const $ = (s, r=document) => r.querySelector(s);

const DB_NAME = "aac_offline_db_v1";
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

async function speakText(text, voiceDefaults){
  if(!('speechSynthesis' in window)) { alert('TTS not supported.'); return; }
  const synth = window.speechSynthesis;
  try{ synth.cancel(); }catch{}
  await ensureVoices();
  const d = voiceDefaults || { lang:'pt-PT', rate:1, pitch:1 };
  const u = new SpeechSynthesisUtterance(String(text||'').trim());
  u.lang = d.lang || 'pt-PT';
  u.rate = clamp(d.rate ?? 1, .5, 2);
  u.pitch= clamp(d.pitch?? 1, .5, 2);
  await new Promise(res=>{ u.onend=res; u.onerror=res; synth.speak(u); });
}

function normalizeUrl(base, path){
  if(!path) return "";
  if(/^https?:\/\//i.test(path)) return path;
  if(base){
    return base.replace(/\/+$/,'') + '/' + path.replace(/^\/+/,'');
  }
  return path;
}

async function prefetchIcons(seed, baseUrl){
  const cache = await caches.open(CACHE_DYNAMIC);
  const icons = (seed.items||[]).map(it => normalizeUrl(baseUrl, it.icon)).filter(Boolean);
  const uniq = Array.from(new Set(icons));
  for(const url of uniq){
    try{
      const req = new Request(url, { cache: "no-store" });
      const res = await fetch(req);
      if(res.ok) await cache.put(req, res.clone());
    }catch{}
  }
}

async function loadLocalSeed(){
  const res = await fetch('data/seed.json', { cache:'no-store' });
  return await res.json();
}

async function loadSeed(){
  const updated = await dbGet("seed.updated");
  if(updated && updated.items) return updated;
  return await loadLocalSeed();
}

async function render(){
  const seed = await loadSeed();
  const voiceDefaults = seed.defaults?.voice || { lang:'pt-PT', rate:1, pitch:1 };

  const pages = seed.pages || [];
  const items = seed.items || [];
  const baseUrl = (await dbGet("updates.baseUrl")) || "";

  const tabs = $("#tabs");
  const grid = $("#grid");
  const strip = $("#strip");
  const speakOnTap = $("#speakOnTap");

  let activePage = await dbGet("ui.activePage") || (pages[0]?.id || "");
  let sentence = await dbGet("ui.sentence") || [];

  function renderTabs(){
    tabs.innerHTML = "";
    for(const p of pages){
      const b = document.createElement("button");
      b.className = "tab" + (p.id === activePage ? " active" : "");
      b.textContent = p.label;
      b.onclick = async () => {
        activePage = p.id;
        await dbSet("ui.activePage", activePage);
        renderAll();
      };
      tabs.appendChild(b);
    }
  }

  function renderStrip(){
    strip.innerHTML = "";
    if(!sentence.length){
      strip.innerHTML = `<span class="hint">Tap tiles to build a sentence…</span>`;
      return;
    }
    sentence.forEach((t, idx) => {
      const tok = document.createElement("span");
      tok.className = "token";
      tok.title = "Tap to remove";
      tok.innerHTML = `${t.icon ? `<img src="${t.icon}" alt="">` : ""}<span>${t.text}</span>`;
      tok.onclick = async () => {
        sentence.splice(idx,1);
        await dbSet("ui.sentence", sentence);
        renderStrip();
      };
      strip.appendChild(tok);
    });
  }

  function renderGrid(){
    grid.innerHTML = "";
    const list = items.filter(it => it.page === activePage);
    for(const it of list){
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "tile";
      const iconUrl = normalizeUrl(baseUrl, it.icon);
      btn.setAttribute("aria-label", it.text);
      btn.innerHTML = `${iconUrl ? `<img src="${iconUrl}" alt="">` : ""}<div class="label">${it.text}</div>`;
      btn.onclick = async () => {
        sentence.push({ text: it.text, icon: iconUrl || "" });
        await dbSet("ui.sentence", sentence);
        renderStrip();
        if(speakOnTap.checked) await speakText(it.text, voiceDefaults);
      };
      grid.appendChild(btn);
    }
  }

  function renderAll(){
    renderTabs();
    renderGrid();
    renderStrip();
  }

  $("#btnSpeak").onclick = async () => {
    const txt = sentence.map(x=>x.text).join(" ").trim();
    if(txt) await speakText(txt, voiceDefaults);
  };
  $("#btnBack").onclick = async () => {
    sentence.pop();
    await dbSet("ui.sentence", sentence);
    renderStrip();
  };
  $("#btnClear").onclick = async () => {
    sentence = [];
    await dbSet("ui.sentence", sentence);
    renderStrip();
  };

  // Settings / Updates
  $("#btnOpenSettings").onclick = async () => {
    const dialog = $("#settingsDialog");
    $("#updateUrl").value = (await dbGet("updates.url")) || "";
    $("#baseUrl").value = (await dbGet("updates.baseUrl")) || "";
    dialog.showModal();
  };

  $("#btnSaveSettings").onclick = async () => {
    await dbSet("updates.url", $("#updateUrl").value.trim());
    await dbSet("updates.baseUrl", $("#baseUrl").value.trim());
    $("#settingsDialog").close();
    renderAll();
  };

  $("#btnCheckUpdates").onclick = async () => {
    const url = ($("#updateUrl").value || "").trim() || (await dbGet("updates.url")) || "";
    if(!url){ alert("Set an Update URL first (points to a seed.json on the web)."); return; }

    try{
      const res = await fetch(url, { cache:"no-store" });
      if(!res.ok) throw new Error("HTTP " + res.status);
      const remoteSeed = await res.json();
      const currentSeed = await loadSeed();

      const remoteVer = Number(remoteSeed.version || 0);
      const currentVer = Number(currentSeed.version || 0);
      const remoteStamp = String(remoteSeed.exportedAt || "");
      const currentStamp = String(currentSeed.exportedAt || "");

      const shouldUpdate = (remoteVer > currentVer) || (remoteVer === currentVer && remoteStamp && remoteStamp !== currentStamp);

      if(!shouldUpdate){
        alert("No update found.");
        return;
      }

      await dbSet("seed.updated", remoteSeed);

      const base = ($("#baseUrl").value || "").trim() || (await dbGet("updates.baseUrl")) || "";
      await prefetchIcons(remoteSeed, base);

      alert("Updated! Reloading…");
      location.reload();
    }catch(e){
      alert("Update failed: " + (e?.message || e));
    }
  };

  renderAll();
}

async function registerSW(){
  if(!('serviceWorker' in navigator)) return;
  try{
    await navigator.serviceWorker.register('./sw.js', { scope: './' });
  }catch{}
}

window.addEventListener('load', async () => {
  await registerSW();
  await render();
});
