<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Moodwire</title>
<style>
:root {
  --bg:      #f5f5f7;
  --surface: #ffffff;
  --border:  #e4e4e7;
  --text:    #111827;
  --muted:   #6b7280;
  --accent:  #2563eb;
  --low:     #16a34a;
  --mid:     #d97706;
  --high:    #dc2626;
  --radius:  16px;
  --safe-top: env(safe-area-inset-top, 0px);
  --safe-bot: env(safe-area-inset-bottom, 0px);
}

* { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }

html, body { height:100%; background:var(--bg); color:var(--text); font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; overflow:hidden; }

/* ── Layout ─────────────────────────────────────────────────────────────── */
#app { display:flex; flex-direction:column; height:100vh; height:100dvh; }

/* ── Header ─────────────────────────────────────────────────────────────── */
#header {
  padding: calc(var(--safe-top) + 8px) 12px 0;
  background: var(--bg);
  flex-shrink: 0;
}
#header h1 { font-size:20px; font-weight:800; letter-spacing:-0.5px; margin-bottom:8px; }
#header h1 span { color:var(--accent); }

/* ── Filter chips ───────────────────────────────────────────────────────── */
#filters {
  display:flex; gap:6px; overflow-x:auto; padding-bottom:8px;
  scrollbar-width:none; -webkit-overflow-scrolling:touch;
  align-items:center; flex-wrap:nowrap;
}
#filters::-webkit-scrollbar { display:none; }
#cat-chips { display:contents; }
.chip {
  flex-shrink:0; padding:4px 10px; border-radius:14px;
  border:1.5px solid var(--border); background:transparent;
  color:var(--muted); font-size:12px; font-weight:600;
  cursor:pointer; white-space:nowrap; transition:all 0.15s;
  min-width:44px; text-align:center; height:28px;
  display:inline-flex; align-items:center; justify-content:center;
}
.chip.active        { background:var(--accent); border-color:var(--accent); color:#fff; }
.chip.active.low    { background:var(--low);  border-color:var(--low);  }
.chip.active.mid    { background:var(--mid);  border-color:var(--mid);  }
.chip.active.high   { background:var(--high); border-color:var(--high); }
.chip-sep { flex-shrink:0; width:1px; background:var(--border); margin:4px 4px; }

/* ── Feed ───────────────────────────────────────────────────────────────── */
#feed {
  flex:1; overflow-y:auto; padding:6px 10px 10px;
  -webkit-overflow-scrolling:touch; scrollbar-width:none;
}
#feed::-webkit-scrollbar { display:none; }

/* ── Card ───────────────────────────────────────────────────────────────── */
.card {
  background:var(--surface); border-radius:var(--radius);
  margin-bottom:8px; overflow:hidden;
  border-left:4px solid var(--border);
}
.card.slide-in {
  animation:slideIn 0.3s cubic-bezier(0.34,1.56,0.64,1) both;
}
@keyframes slideIn {
  from { opacity:0; transform:translateY(24px) scale(0.97); }
  to   { opacity:1; transform:translateY(0) scale(1); }
}

.card-header {
  padding:12px 14px 0;
  display:flex; justify-content:space-between; align-items:center;
}
.card-meta { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
.badge {
  font-size:10px; font-weight:700; padding:3px 8px;
  border-radius:10px; letter-spacing:0.3px; text-transform:uppercase;
}
.badge-cat   { background:#f1f5f9; color:#64748b; }
.badge-geo   { background:#ede9fe; color:#7c3aed; }
.badge-type  { background:#e0f2fe; color:#0369a1; }
.card-time   { font-size:11px; color:var(--muted); }

.anxiety-pip {
  font-size:11px; font-weight:700; padding:3px 10px;
  border-radius:10px; color:white; flex-shrink:0;
}

.card-title {
  padding:10px 14px 2px;
  font-size:18px; font-weight:700; line-height:1.3;
  letter-spacing:-0.3px;
}

.card-bullets { padding:8px 14px 4px; }
.card-bullets li {
  font-size:14px; color:#374151; line-height:1.5;
  padding:3px 0; list-style:none;
  padding-left:14px; position:relative;
}
.card-bullets li::before { content:'·'; position:absolute; left:2px; color:var(--muted); }

/* ── Articles toggle ────────────────────────────────────────────────────── */
.articles-toggle {
  display:flex; align-items:center; gap:6px;
  padding:10px 14px; color:var(--muted); font-size:13px;
  font-weight:600; cursor:pointer; border:none; background:none;
  width:100%; text-align:left;
  border-top:1px solid var(--border); margin-top:6px;
}
.articles-toggle .arrow { transition:transform 0.2s; display:inline-block; }
.articles-toggle.open .arrow { transform:rotate(90deg); }
.articles-toggle .count-badge {
  margin-left:auto; background:var(--border);
  padding:2px 8px; border-radius:10px; font-size:11px;
}

.articles-list { display:none; }
.articles-list.open { display:block; }

.article-row {
  display:flex; align-items:center; gap:10px;
  padding:10px 14px; text-decoration:none; color:var(--text);
  border-top:1px solid var(--border);
  transition:background 0.1s;
  -webkit-tap-highlight-color:transparent;
}
.article-row:active { background:rgba(0,0,0,0.04); }

.article-thumb {
  width:52px; height:36px; object-fit:cover;
  border-radius:6px; flex-shrink:0; background:var(--border);
}
.article-thumb-placeholder {
  width:52px; height:36px; border-radius:6px;
  flex-shrink:0; background:var(--border);
}
.article-info { flex:1; min-width:0; }
.article-title { font-size:13px; line-height:1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.article-src   { font-size:11px; color:var(--muted); margin-top:2px; }
.article-anx   { width:8px; height:8px; border-radius:50%; flex-shrink:0; }

/* ── Preferences bar ────────────────────────────────────────────────────── */
#prefs { padding:6px 12px 0; display:flex; flex-direction:column; gap:4px; }
.pref-row { display:flex; align-items:center; gap:6px; overflow:hidden; }
.pref-label { font-size:11px; font-weight:800; flex-shrink:0; width:16px; }
.pref-label.like    { color:var(--low); }
.pref-label.dislike { color:var(--high); }
.pref-tags { display:flex; gap:4px; overflow-x:auto; flex-wrap:nowrap; scrollbar-width:none; }
.pref-tags::-webkit-scrollbar { display:none; }
.pref-tag { font-size:11px; padding:2px 7px; border-radius:10px; font-weight:600; flex-shrink:0; }
.pref-tag.like    { background:rgba(22,163,74,0.12);  color:var(--low);  border:1px solid rgba(22,163,74,0.3); }
.pref-tag.dislike { background:rgba(220,38,38,0.12); color:var(--high); border:1px solid rgba(220,38,38,0.3); }
.tag-del {
  background:none; border:none; cursor:pointer; font-size:13px; line-height:1;
  padding:0 0 0 4px; opacity:0.5; color:inherit; vertical-align:middle;
}
.tag-del:hover { opacity:1; }
.pref-empty { font-size:11px; color:var(--muted); font-style:italic; flex-shrink:0; }
#prefs-divider { height:1px; background:var(--border); margin:6px 12px 0; }

/* ── Saved strip ─────────────────────────────────────────────────────────── */
#saved-strip {
  display:none; gap:6px; overflow-x:auto; padding:6px 10px 4px;
  scrollbar-width:none; flex-shrink:0;
  border-bottom:1px solid var(--border);
}
#saved-strip::-webkit-scrollbar { display:none; }
#saved-strip.has-items { display:flex; }
.saved-chip {
  display:flex; align-items:center; gap:6px; flex-shrink:0;
  background:var(--surface); border:1px solid var(--border);
  border-radius:10px; padding:4px 10px 4px 6px;
  border-left:3px solid var(--border);
  cursor:pointer; max-width:160px; transition:opacity 0.15s;
}
.saved-chip:active { opacity:0.7; }
.saved-chip-title { font-size:11px; font-weight:600; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

/* ── Saved overlay ───────────────────────────────────────────────────────── */
#saved-overlay {
  position:fixed; inset:0; z-index:100; display:none;
  background:rgba(0,0,0,0.5); backdrop-filter:blur(4px);
  align-items:flex-end; justify-content:center;
}
#saved-overlay.open { display:flex; }
#saved-overlay-card {
  width:100%; max-height:90dvh; overflow-y:auto;
  background:var(--bg); border-radius:20px 20px 0 0;
  padding:8px 0 calc(var(--safe-bot) + 8px);
  position:relative;
}
#saved-overlay-card .card {
  margin:0 10px 8px; border-radius:var(--radius);
  box-shadow:0 2px 12px rgba(0,0,0,0.1);
}
#overlay-handle {
  width:36px; height:4px; background:var(--border);
  border-radius:2px; margin:0 auto 10px;
}
#overlay-hint {
  text-align:center; font-size:11px; color:var(--muted);
  padding:0 0 8px; letter-spacing:0.3px;
}

/* ── Swipe gesture ──────────────────────────────────────────────────────── */
.card { position:relative; cursor:pointer; user-select:none; touch-action:none; }
.card.swiping { transition:none !important; }
.swipe-overlay {
  position:absolute; inset:0; border-radius:var(--radius);
  display:flex; align-items:center; justify-content:center;
  font-size:36px; opacity:0; pointer-events:none; transition:opacity 0.1s;
  font-weight:900;
}
.swipe-overlay.like    { background:rgba(22,163,74,0.25); }
.swipe-overlay.dislike { background:rgba(220,38,38,0.25); }
.swipe-overlay.save    { background:rgba(37,99,235,0.25); }

/* ── Card hint ──────────────────────────────────────────────────────────── */
.card-hint {
  padding:8px 14px 10px; font-size:11px; color:var(--muted);
  text-align:right; letter-spacing:0.3px; text-transform:uppercase;
}
.articles-header {
  display:flex; justify-content:space-between; align-items:center;
  padding:10px 14px 4px; font-size:12px; font-weight:700;
  color:var(--muted); border-top:1px solid var(--border); text-transform:uppercase;
  letter-spacing:0.5px;
}

/* ── Empty / Loading ─────────────────────────────────────────────────────── */
#empty  { text-align:center; padding:60px 20px; color:var(--muted); font-size:15px; }
#loader { text-align:center; padding:40px; color:var(--muted); font-size:13px; }

/* ── Bottom nav ─────────────────────────────────────────────────────────── */
#bottom-nav {
  display:flex; border-top:1px solid var(--border);
  padding-bottom:var(--safe-bot);
  background:var(--surface); flex-shrink:0;
  box-shadow: 0 -1px 0 var(--border);
}
.nav-item {
  flex:1; display:flex; flex-direction:column; align-items:center;
  padding:10px 0; gap:3px; text-decoration:none;
  color:var(--muted); font-size:10px; font-weight:600;
  letter-spacing:0.3px; text-transform:uppercase;
  transition:color 0.15s;
}
.nav-item.active, .nav-item:active { color:var(--accent); }
.nav-icon { font-size:20px; line-height:1; }
</style>
</head>
<body>
<div id="app">

  <!-- Header -->
  <div id="header">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <h1 style="margin:0">Mood<span>wire</span></h1>
      <div style="display:flex;align-items:center;gap:8px">
        <div id="anxiety-meter" style="display:none;align-items:center;gap:5px">
          <div id="anxiety-bar-wrap" style="width:60px;height:6px;background:var(--border);border-radius:3px;overflow:hidden">
            <div id="anxiety-bar" style="height:100%;border-radius:3px;transition:width 0.4s,background 0.4s"></div>
          </div>
          <span id="anxiety-val" style="font-size:11px;font-weight:700;min-width:24px"></span>
        </div>
        <button id="clear-btn" onclick="clearPreferences()" style="display:none;background:none;border:1px solid var(--border);color:var(--muted);font-size:11px;font-weight:600;padding:4px 12px;border-radius:10px;cursor:pointer">Clear</button>
      </div>
    </div>
    <div id="filters">
      <button class="chip active" data-filter="" data-group="anxiety">All</button>
      <button class="chip low"  data-filter="low"    data-group="anxiety">Low</button>
      <button class="chip mid"  data-filter="medium" data-group="anxiety">Medium</button>
      <button class="chip high" data-filter="high"   data-group="anxiety">High</button>
      <div class="chip-sep"></div>
      <div id="cat-chips"></div>
    </div>
  </div>

  <!-- Preferences -->
  <div id="prefs" style="display:none">
    <div class="pref-row">
      <span class="pref-label like">✓</span>
      <div class="pref-tags" id="liked-tags"></div>
    </div>
    <div class="pref-row">
      <span class="pref-label dislike">✗</span>
      <div class="pref-tags" id="disliked-tags"></div>
    </div>
    <div class="pref-row" id="country-row" style="display:none">
      <span class="pref-label" style="color:#6b7280">🌍</span>
      <div class="pref-tags" id="country-tags"></div>
    </div>
  </div>
  <div id="prefs-divider" style="display:none"></div>

  <!-- Saved strip -->
  <div id="saved-strip"></div>

  <!-- Saved overlay -->
  <div id="saved-overlay" onclick="if(event.target===this)closeSavedOverlay()">
    <div id="saved-overlay-card">
      <div id="overlay-handle"></div>
      <div id="overlay-hint">Swipe left · right · up to act</div>
      <div id="overlay-content"></div>
    </div>
  </div>

  <!-- Feed -->
  <div id="feed">
    <div id="loader">Loading…</div>
  </div>

  <!-- Bottom nav -->
  <nav id="bottom-nav">
    <a href="index.php" class="nav-item active"><span class="nav-icon">📰</span>Feed</a>
    <a href="map.php"   class="nav-item"><span class="nav-icon">🗺</span>Map</a>
    <a href="mobile.php"class="nav-item"><span class="nav-icon">🌍</span>Globe</a>
    <a href="run.php"   class="nav-item"><span class="nav-icon">⚙️</span>Admin</a>
  </nav>

</div>

<script>
let activeAnxiety   = '';
let activeCategory  = '';
let isLoading       = false;
let nextPersonalized = true; // alternates for replacements
const STACK_SIZE    = 5;
const shownIds      = new Set();

// ── Fetch topics (initial stack or single replacement) ────────────────────
async function fetchTopics(count = STACK_SIZE, mode = 'mixed') {
  const params = new URLSearchParams({ limit: count });
  if (activeAnxiety)  params.set('anxiety',  activeAnxiety);
  if (activeCategory) params.set('category', activeCategory);
  if (shownIds.size)  params.set('exclude',  [...shownIds].join(','));
  if (mode !== 'mixed') params.set('mode', mode);

  const res  = await fetch('api.php?action=topics&' + params);
  const data = await res.json();
  data.topics.forEach(t => shownIds.add(t.id));
  return data.topics;
}

// ── Load initial stack ────────────────────────────────────────────────────
async function loadTopics() {
  if (isLoading) return;
  isLoading = true;
  shownIds.clear();
  document.getElementById('feed').innerHTML = '<div id="loader">Loading…</div>';

  const topics = await fetchTopics(STACK_SIZE);

  if (!topics.length) {
    document.getElementById('feed').innerHTML = '<div id="empty">No topics found.</div>';
    isLoading = false; return;
  }
  document.getElementById('feed').innerHTML = topics.map(t => renderCard(t)).join('');
  document.querySelectorAll('.card').forEach(c => attachSwipe(c));
  isLoading = false;
}

// ── Load one replacement card ─────────────────────────────────────────────
async function loadReplacement() {
  const mode = nextPersonalized ? 'personalized' : 'random';
  nextPersonalized = !nextPersonalized;

  const topics = await fetchTopics(1, mode);
  if (!topics.length) return;

  const html = renderCard(topics[0]);
  const tmp  = document.createElement('div');
  tmp.innerHTML = html;
  const card = tmp.firstElementChild;
  card.classList.add('slide-in');
  document.getElementById('feed').appendChild(card);
  attachSwipe(card);
}


function anxColor(a) {
  const idx = Math.min(9, Math.max(0, Math.floor(a)));
  return ['#16a34a','#4ade80','#a3e635','#facc15','#fb923c','#f97316','#ef4444','#dc2626','#b91c1c','#7f1d1d'][idx];
}

function renderCard(t, i) {
  const delay = Math.min(i * 40, 400);
  const bullets = t.bullets.map(b => `<li>${esc(b)}</li>`).join('');
  const articles = t.articles.map(a => {
    const anx   = parseFloat(a.anxiety ?? 5);
    const thumb = a.image_path
      ? `<img src="${esc(a.image_path)}" class="article-thumb" alt="">`
      : `<div class="article-thumb-placeholder"></div>`;
    // Store article tags as data attribute (exclude country tags)
    const artTags = (a.tags ?? '').split(',').map(s=>s.trim()).filter(s=>s && !s.startsWith('country:'));
    const tagsAttr = esc(JSON.stringify(artTags));
    return `<a href="${esc(a.url)}" target="_blank" class="article-row" data-tags="${tagsAttr}" data-anxiety="${anx.toFixed(1)}" onclick="articleClick(event, this)">
      ${thumb}
      <div class="article-info">
        <div class="article-title">${esc(a.title)}</div>
        <div class="article-src">${esc(a.feed_name)}</div>
      </div>
      <div class="article-anx" style="background:${anxColor(anx)}"></div>
    </a>`;
  }).join('');

  return `
  <div class="card" data-id="${t.id}" data-state="0" data-anxiety="${t.anxiety_avg}"
       style="border-left-color:${t.anxiety_color};animation-delay:${delay}ms"
       onclick="tapCard(${t.id}, this)">
    <div class="card-header">
      <div class="card-meta">
        ${t.category ? `<span class="badge badge-cat">${esc(t.category)}</span>` : ''}
        ${t.geo      ? `<span class="badge badge-geo">${esc(t.geo)}</span>` : ''}
        <span class="card-time">${esc(t.time_ago ?? '')}</span>
      </div>
      <span class="anxiety-pip" style="background:${t.anxiety_color}">${t.anxiety_label} ${t.anxiety_avg.toFixed(1)}</span>
    </div>

    <div class="card-title">${esc(t.title)}</div>

    <ul class="card-bullets card-section" style="display:none">${bullets}</ul>

    <div class="articles-section card-section" style="display:none">
      <div class="articles-header">
        <span>Articles</span>
        <span class="count-badge">${t.articles.length}</span>
      </div>
      ${articles}
    </div>

    <div class="card-hint">Tap for summary · Swipe to react</div>
    <div class="swipe-overlay like">👍</div>
    <div class="swipe-overlay dislike">👎</div>
    <div class="swipe-overlay save">🔖</div>
  </div>`;
}

// Signal interest — accepts tags array or topic_id, optional source and anxiety
async function signal(tagsOrId, direction = 'right', source = 'swipe', anxiety = null) {
  const body = typeof tagsOrId === 'number'
    ? { topic_id: tagsOrId, direction, source, anxiety }
    : { tags: tagsOrId, direction, source, anxiety };
  // Skip only if tags empty AND no anxiety to track
  if (Array.isArray(tagsOrId) && !tagsOrId.length && anxiety === null) return;
  const res  = await fetch('api.php?action=swipe', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  const data = await res.json();
  if (data.liked !== undefined) renderPreferences(data.liked, data.disliked);
  if (data.anxiety_avg !== undefined) renderAnxietyMeter(data.anxiety_avg, data.anxiety_count);
}

// Article click — signal article tags once, then navigate
async function articleClick(e, el) {
  e.preventDefault();
  const win = window.open('', '_blank'); // open now (in user gesture, no popup block)
  if (!el.dataset.signaled) {
    el.dataset.signaled = '1';
    const tags    = JSON.parse(el.dataset.tags ?? '[]');
    const anxiety = parseFloat(el.dataset.anxiety ?? 5);
    await signal(tags, 'right', 'click', anxiety); // update meter first
  }
  win.location.href = el.href; // then navigate the tab
}

// 3-state tap: 0=title only → 1=+bullets → 2=+articles → 0
function tapCard(id, card) {
  // Don't collapse if user tapped a link
  if (event.target.closest('a')) return;
  const state    = parseInt(card.dataset.state);
  const next     = (state + 1) % 3;

  // First tap = interest signal for the topic (once only)
  if (state === 0 && !card.dataset.signaled) {
    card.dataset.signaled = '1';
    signal(id, 'right', 'tap', parseFloat(card.dataset.anxiety ?? 5));
  }
  const bullets  = card.querySelector('.card-bullets');
  const articles = card.querySelector('.articles-section');
  const hint     = card.querySelector('.card-hint');

  card.dataset.state = next;
  bullets.style.display  = next >= 1 ? '' : 'none';
  articles.style.display = next >= 2 ? '' : 'none';
  hint.textContent = next === 0 ? 'Tap for summary'
                   : next === 1 ? 'Tap for articles'
                   : 'Tap to collapse';
}

function esc(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Load category chips ───────────────────────────────────────────────────
async function loadStats() {
  const res  = await fetch('api.php?action=stats');
  const data = await res.json();
  const wrap = document.getElementById('cat-chips');
  wrap.innerHTML = data.categories.map(c =>
    `<button class="chip" data-filter="${esc(c.category)}" data-group="category"
      onclick="setCategory(this)">${esc(c.category)} <span style="opacity:.5;font-size:10px">${c.c}</span></button>`
  ).join('');
}

// ── Filters ───────────────────────────────────────────────────────────────
document.getElementById('filters').addEventListener('click', e => {
  const chip = e.target.closest('.chip[data-group="anxiety"]');
  if (!chip) return;
  document.querySelectorAll('.chip[data-group="anxiety"]').forEach(c => c.classList.remove('active'));
  chip.classList.add('active');
  activeAnxiety = chip.dataset.filter;
  loadTopics();
});

function setCategory(chip) {
  document.querySelectorAll('.chip[data-group="category"]').forEach(c => c.classList.remove('active'));
  if (activeCategory === chip.dataset.filter) {
    activeCategory = '';
  } else {
    chip.classList.add('active');
    activeCategory = chip.dataset.filter;
  }
  loadTopics();
}

// ── Swipe gestures ────────────────────────────────────────────────────────
const SWIPE_THRESHOLD = 80;

// ── Saved cards ───────────────────────────────────────────────────────────
const savedCards = new Map();
let overlayActiveId = null;

function addToSaved(id, card, html) {
  if (savedCards.has(id)) return;
  const title = card.querySelector('.card-title')?.textContent?.trim() ?? '';
  const color = card.style.borderLeftColor || '#e4e4e7';
  savedCards.set(id, { title, color, html: html ?? card.outerHTML, id });
  renderSavedStrip();
}

function renderSavedStrip() {
  const strip = document.getElementById('saved-strip');
  if (!savedCards.size) { strip.classList.remove('has-items'); strip.innerHTML=''; return; }
  strip.classList.add('has-items');
  strip.innerHTML = [...savedCards.entries()].map(([id, d]) =>
    `<div class="saved-chip" style="border-left-color:${d.color}" onclick="openSaved(${id})">
       <span class="saved-chip-title">${esc(d.title)}</span>
     </div>`
  ).join('');
}

function openSaved(id) {
  const d = savedCards.get(id);
  if (!d) return;
  overlayActiveId = id;
  const content = document.getElementById('overlay-content');
  content.innerHTML = d.html;
  const card = content.querySelector('.card');
  if (card) {
    card.style.transform=''; card.style.opacity='1'; card.style.transition='';
    card.dataset.state='2';
    const bullets=card.querySelector('.card-bullets');
    const articles=card.querySelector('.articles-section');
    const hint=card.querySelector('.card-hint');
    if(bullets)bullets.style.display='';
    if(articles)articles.style.display='';
    if(hint)hint.textContent='Swipe to act · down to close';
    attachSwipeOverlay(card, id);
  }
  document.getElementById('saved-overlay').classList.add('open');
}

function closeSavedOverlay() {
  document.getElementById('saved-overlay').classList.remove('open');
  overlayActiveId = null;
}

function removeSaved(id) {
  savedCards.delete(id);
  renderSavedStrip();
  closeSavedOverlay();
}

// ── Shared swipe action ───────────────────────────────────────────────────
async function handleSwipeAction(card, dir, onComplete) {
  const id = parseInt(card.dataset.id);
  if (dir === 'up') {
    const savedHtml = card.outerHTML; // capture BEFORE animation
    card.style.transition = 'transform 0.28s ease, opacity 0.28s ease';
    card.style.transform = 'translateY(-110%) scale(0.85)';
    card.style.opacity = '0';
    setTimeout(() => { addToSaved(id, card, savedHtml); card.remove(); if (onComplete) onComplete(); }, 280);
    return;
  }
  card.style.transition = 'transform 0.28s ease, opacity 0.28s ease';
  card.style.transform = `translateX(${dir==='right'?'120vw':'-120vw'}) rotate(${dir==='right'?20:-20}deg)`;
  card.style.opacity = '0';
  setTimeout(() => { card.remove(); if (onComplete) onComplete(); }, 300);
  const wasUntouched = parseInt(card.dataset.state) === 0;
  if (dir === 'left' || wasUntouched) {
    const res  = await fetch('api.php?action=swipe', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({topic_id:id,direction:dir})});
    const data = await res.json();
    renderPreferences(data.liked, data.disliked, data.countries);
    if (data.anxiety_avg !== undefined) renderAnxietyMeter(data.anxiety_avg, data.anxiety_count);
  }
}

function makeSwipeable(card, onSwipe) {
  let startX=null, startY=null, dx=0, dy=0;
  card.addEventListener('touchstart', e => {
    startX=e.touches[0].clientX; startY=e.touches[0].clientY; dx=0; dy=0;
    card.classList.add('swiping');
  }, {passive:true});
  card.addEventListener('touchmove', e => {
    if (startX===null) return;
    dx=e.touches[0].clientX-startX; dy=e.touches[0].clientY-startY;
    e.preventDefault();
    const upDom = Math.abs(dy)>Math.abs(dx) && dy<0;
    const dnDom = Math.abs(dy)>Math.abs(dx) && dy>0;
    if (upDom || dnDom) {
      card.style.transform=`translateY(${dy}px)`;
      card.querySelector('.swipe-overlay.save').style.opacity = dy<0 ? Math.min(-dy/SWIPE_THRESHOLD,1):0;
      card.querySelector('.swipe-overlay.like').style.opacity=0;
      card.querySelector('.swipe-overlay.dislike').style.opacity=0;
    } else {
      card.style.transform=`translateX(${dx}px) rotate(${dx*0.03}deg)`;
      card.querySelector('.swipe-overlay.like').style.opacity=dx>0?Math.min(dx/SWIPE_THRESHOLD,1):0;
      card.querySelector('.swipe-overlay.dislike').style.opacity=dx<0?Math.min(-dx/SWIPE_THRESHOLD,1):0;
      card.querySelector('.swipe-overlay.save').style.opacity=0;
    }
  }, {passive:false});
  card.addEventListener('touchend', () => {
    card.classList.remove('swiping');
    const isUp   = dy < -SWIPE_THRESHOLD && Math.abs(dy)>Math.abs(dx);
    const isDown = dy >  SWIPE_THRESHOLD && Math.abs(dy)>Math.abs(dx);
    const isHoriz= Math.abs(dx)>=SWIPE_THRESHOLD && Math.abs(dx)>=Math.abs(dy);
    if (isUp)         onSwipe('up');
    else if (isDown)  onSwipe('down');
    else if (isHoriz) onSwipe(dx>0?'right':'left');
    else {
      card.style.transform='';
      ['like','dislike','save'].forEach(c=>card.querySelector('.swipe-overlay.'+c).style.opacity=0);
    }
    startX=null;
  }, {passive:true});
}

function attachSwipe(card) {
  makeSwipeable(card, dir => {
    if (dir==='down') return; // ignore down swipe on main cards
    handleSwipeAction(card, dir, () => loadReplacement());
    if (dir!=='up') loadReplacement();
  });
}

function attachSwipeOverlay(card, savedId) {
  makeSwipeable(card, dir => {
    if (dir==='down') { closeSavedOverlay(); card.style.transform=''; return; }
    if (dir==='up')   { closeSavedOverlay(); return; } // keep in strip
    // left/right: act and remove from saved
    handleSwipeAction(card, dir, () => removeSaved(savedId));
    setTimeout(() => removeSaved(savedId), 320);
  });
}

// ── Preferences ───────────────────────────────────────────────────────────
function flag(cc) {
  return cc.toUpperCase().split('').map(c => String.fromCodePoint(0x1F1E6 + c.charCodeAt(0) - 65)).join('');
}

function renderPreferences(liked, disliked, countries) {
  const hasPrefs = Object.keys(liked ?? {}).length || Object.keys(disliked ?? {}).length || Object.keys(countries ?? {}).length;
  document.getElementById('prefs').style.display         = hasPrefs ? '' : 'none';
  document.getElementById('prefs-divider').style.display = hasPrefs ? '' : 'none';
  document.getElementById('clear-btn').style.display     = hasPrefs ? '' : 'none';

  const likedEntries    = Object.entries(liked    ?? {}).sort((a,b) => b[1]-a[1]).slice(0,15);
  const dislikedEntries = Object.entries(disliked ?? {}).sort((a,b) => b[1]-a[1]).slice(0,15);

  document.getElementById('liked-tags').innerHTML =
    likedEntries.length ? likedEntries.map(([t,c]) =>
      `<span class="pref-tag like">${esc(t)}${c>1?` <b>${c}</b>`:''}<button class="tag-del" onclick="removeTag('${esc(t)}','liked')">×</button></span>`).join('')
    : '<span class="pref-empty">swipe right to add</span>';

  document.getElementById('disliked-tags').innerHTML =
    dislikedEntries.length ? dislikedEntries.map(([t,c]) =>
      `<span class="pref-tag dislike">${esc(t)}${c>1?` <b>${c}</b>`:''}<button class="tag-del" onclick="removeTag('${esc(t)}','disliked')">×</button></span>`).join('')
    : '<span class="pref-empty">swipe left to add</span>';

  const countryEntries = Object.entries(countries ?? {}).sort((a,b) => b[1]-a[1]).slice(0,15);
  const countryRow = document.getElementById('country-row');
  countryRow.style.display = countryEntries.length ? '' : 'none';
  document.getElementById('country-tags').innerHTML =
    countryEntries.map(([cc,c]) =>
      `<span class="pref-tag like" style="background:rgba(37,99,235,0.1);color:#2563eb;border-color:rgba(37,99,235,0.3)">${flag(cc)} ${cc}${c>1?` <b>${c}</b>`:''}<button class="tag-del" onclick="removeTag('${esc(cc)}','countries')">×</button></span>`
    ).join('');
}

function renderAnxietyMeter(avg, count) {
  const meter = document.getElementById('anxiety-meter');
  if (avg === null || count === 0) { meter.style.display = 'none'; return; }
  meter.style.display = 'flex';
  const pct   = (avg / 10) * 100;
  const color = avg >= 7 ? '#dc2626' : avg >= 4 ? '#d97706' : '#16a34a';
  document.getElementById('anxiety-bar').style.width      = pct + '%';
  document.getElementById('anxiety-bar').style.background = color;
  document.getElementById('anxiety-val').style.color      = color;
  document.getElementById('anxiety-val').textContent      = avg.toFixed(1);
}

async function removeTag(tag, list) {
  const res  = await fetch('api.php?action=remove_tag', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ tag, list })
  });
  const data = await res.json();
  renderPreferences(data.liked, data.disliked);
  if (data.anxiety_avg !== undefined) renderAnxietyMeter(data.anxiety_avg, data.anxiety_count);
}

async function clearPreferences() {
  await fetch('api.php?action=clear_preferences', { method: 'POST' });
  renderPreferences({}, {}, {});
}

async function loadPreferences() {
  const res  = await fetch('api.php?action=preferences');
  const data = await res.json();
  renderPreferences(data.liked, data.disliked);
  renderAnxietyMeter(data.anxiety_avg, data.anxiety_count);
}


// ── Init ──────────────────────────────────────────────────────────────────
loadStats();
loadPreferences();
loadTopics();
</script>
</body>
</html>
