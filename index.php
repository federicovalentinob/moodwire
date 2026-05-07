<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Moodwire</title>
<style>
:root {
  --bg:       #f2f2f2;
  --surface:  #ffffff;
  --border:   #e8e8e8;
  --text:     #0d0d0d;
  --muted:    #888;
  --accent:   #e11d48;
  --low:      #16a34a;
  --mid:      #d97706;
  --high:     #dc2626;
  --radius:   16px;
  --shadow:   0 1px 3px rgba(0,0,0,0.05), 0 4px 14px rgba(0,0,0,0.07);
  --safe-top: env(safe-area-inset-top, 0px);
  --safe-bot: env(safe-area-inset-bottom, 0px);
}

* { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
html, body { height:100%; background:var(--bg); color:var(--text); font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; overflow:hidden; }
#app { display:flex; flex-direction:column; height:100vh; height:100dvh; }

/* ── Header ─────────────────────────────────────────────────────────────── */
#header {
  padding: calc(var(--safe-top) + 16px) 16px 0;
  background: var(--surface);
  flex-shrink: 0;
  border-bottom: 1px solid var(--border);
}
#header-title-row { display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:14px; }
#header h1 { font-size:32px; font-weight:900; letter-spacing:-1.5px; line-height:1; }
#header h1 span { color:var(--accent); }
#toggle-prefs-btn {
  background:none; border:1.5px solid var(--border); color:var(--muted);
  font-size:11px; font-weight:700; padding:5px 12px; border-radius:20px;
  cursor:pointer; letter-spacing:0.2px; transition:all 0.15s; margin-bottom:3px;
}
#toggle-prefs-btn:active { opacity:0.7; }
#toggle-prefs-btn.open { background:var(--text); border-color:var(--text); color:#fff; }

/* ── Anxiety widget ─────────────────────────────────────────────────────── */
#anxiety-widget { border-top:1px solid var(--border); padding:10px 0 0; margin-bottom:4px; }
#anxiety-widget-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
#anxiety-widget-title { font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:1.2px; color:var(--muted); }
#anxiety-chips { display:flex; gap:4px; }
.anx-chip {
  padding:4px 11px; border-radius:20px;
  border:1.5px solid var(--border); background:transparent;
  color:var(--muted); font-size:11px; font-weight:700;
  cursor:pointer; transition:all 0.15s; height:26px;
  display:inline-flex; align-items:center;
}
.anx-chip.active        { background:#0d0d0d; border-color:#0d0d0d; color:#fff; }
.anx-chip.active.low    { background:var(--low);  border-color:var(--low);  }
.anx-chip.active.mid    { background:var(--mid);  border-color:var(--mid);  }
.anx-chip.active.high   { background:var(--high); border-color:var(--high); }
.anx-row { display:flex; align-items:center; gap:10px; margin-bottom:6px; }
.anx-row:last-child { margin-bottom:10px; }
.anx-row-label { font-size:9px; font-weight:800; width:38px; flex-shrink:0; color:var(--muted); text-transform:uppercase; letter-spacing:0.6px; }
.anx-track { flex:1; height:6px; background:#ebebeb; border-radius:3px; overflow:hidden; }
.anx-fill { height:100%; border-radius:3px; transition:width 0.6s cubic-bezier(0.22,1,0.36,1),background 0.4s; width:0%; }
.anx-num { font-size:13px; font-weight:800; min-width:26px; text-align:right; letter-spacing:-0.3px; }

/* ── Category chips ─────────────────────────────────────────────────────── */
#filters {
  display:flex; gap:6px; overflow-x:auto; padding-bottom:10px;
  scrollbar-width:none; -webkit-overflow-scrolling:touch; align-items:center; flex-wrap:nowrap;
}
#filters::-webkit-scrollbar { display:none; }
#cat-chips { display:contents; }
.chip {
  flex-shrink:0; padding:5px 12px; border-radius:20px;
  border:1.5px solid var(--border); background:transparent;
  color:var(--muted); font-size:12px; font-weight:600;
  cursor:pointer; white-space:nowrap; transition:all 0.15s;
  height:28px; display:inline-flex; align-items:center; gap:3px;
}
.chip.active { background:#0d0d0d; border-color:#0d0d0d; color:#fff; }

/* ── Feed ───────────────────────────────────────────────────────────────── */
#feed { flex:1; overflow-y:auto; padding:10px 12px 12px; touch-action:pan-y; -webkit-overflow-scrolling:touch; }

/* ── Card ───────────────────────────────────────────────────────────────── */
.card {
  background:var(--surface); border-radius:var(--radius);
  margin-bottom:10px; overflow:hidden;
  box-shadow:var(--shadow); border:1px solid var(--border);
  position:relative; cursor:pointer; user-select:none; touch-action:pan-y;
}
.card.slide-in { animation:slideIn 0.35s cubic-bezier(0.22,1,0.36,1) both; }
@keyframes slideIn {
  from { opacity:0; transform:translateY(18px) scale(0.98); }
  to   { opacity:1; transform:translateY(0)    scale(1); }
}
#saved-overlay-card .card { touch-action:none; }
.card.swiping { transition:none !important; }
.swipe-overlay { display:none; }

/* accent strip at top of card */
.card-accent { height:3px; width:100%; display:block; }

/* hero image */
.card-hero { width:100%; height:168px; overflow:hidden; position:relative; background:#ebebeb; }
.card-hero img { width:100%; height:100%; object-fit:cover; display:block; }
.card-hero-badge {
  position:absolute; bottom:8px; right:10px;
  font-size:10px; font-weight:800; padding:3px 9px; border-radius:6px;
  color:#fff; letter-spacing:0.2px; text-shadow:0 1px 3px rgba(0,0,0,0.4);
}

/* card body */
.card-body { padding:12px 14px 12px; }
.card-eyebrow { display:flex; align-items:center; gap:6px; margin-bottom:6px; flex-wrap:wrap; }
.badge { font-size:10px; font-weight:700; padding:2px 7px; border-radius:5px; letter-spacing:0.3px; text-transform:uppercase; }
.badge-cat { background:#f0f0f0; color:#555; }
.badge-geo { background:#ede9fe; color:#7c3aed; }
.badge-new { background:#dcfce7; color:#15803d; }
.card-time { font-size:11px; color:var(--muted); margin-left:auto; }
.card-title { font-size:18px; font-weight:800; line-height:1.25; letter-spacing:-0.4px; color:var(--text); margin-bottom:8px; }
.card-foot { display:flex; align-items:center; justify-content:space-between; }
.card-article-count { font-size:11px; color:var(--muted); font-weight:500; }
.anxiety-pip { font-size:10px; font-weight:800; padding:3px 8px; border-radius:6px; color:#fff; flex-shrink:0; }
.card-save-btn {
  background:none; border:none; cursor:pointer; padding:4px;
  color:var(--muted); border-radius:6px; display:flex; align-items:center;
  transition:color 0.15s, background 0.15s; flex-shrink:0;
}
.card-save-btn:hover { color:var(--text); background:rgba(0,0,0,0.05); }
.card-save-btn.saved { color:var(--accent); }

/* expanded bullets */
.card-bullets { border-top:1px solid var(--border); padding:10px 14px 12px; }
.card-bullets li { font-size:14px; color:#333; line-height:1.55; padding:3px 0 3px 16px; list-style:none; position:relative; }
.card-bullets li::before { content:'·'; position:absolute; left:2px; color:var(--muted); font-size:20px; line-height:0.85; }

/* tap hint */
.card-tap-hint {
  padding:8px 14px 10px;
  font-size:11px; font-weight:600; color:var(--muted);
  text-align:center; letter-spacing:0.2px;
}

/* articles section */
.articles-section { border-top:1px solid var(--border); }
.articles-header {
  display:flex; justify-content:space-between; align-items:center;
  padding:8px 14px; font-size:9px; font-weight:800;
  color:var(--muted); text-transform:uppercase; letter-spacing:1px;
}
.article-row {
  display:flex; align-items:center; gap:10px; padding:10px 14px;
  text-decoration:none; color:var(--text); border-top:1px solid var(--border);
  transition:background 0.1s; -webkit-tap-highlight-color:transparent;
}
.article-row:active { background:rgba(0,0,0,0.025); }
.article-thumb { width:54px; height:38px; object-fit:cover; border-radius:8px; flex-shrink:0; background:var(--border); }
.article-thumb-placeholder { width:54px; height:38px; border-radius:8px; flex-shrink:0; background:#f0f0f0; }
.article-info { flex:1; min-width:0; }
.article-title { font-size:13px; line-height:1.4; font-weight:500; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.article-src { font-size:11px; color:var(--muted); margin-top:2px; }
.article-anx { width:6px; height:6px; border-radius:50%; flex-shrink:0; }

/* ── Preferences bar ────────────────────────────────────────────────────── */
#prefs { padding:0 14px 0; display:flex; flex-direction:column; gap:4px; background:var(--surface); }
.prefs-header {
  display:flex; align-items:center; justify-content:space-between;
  padding:8px 0 4px; border-bottom:1px solid var(--border); margin-bottom:4px;
}
.prefs-title { font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:var(--muted); }
.prefs-clear-btn {
  background:none; border:1px solid var(--border); color:var(--muted);
  font-size:10px; font-weight:700; padding:3px 10px; border-radius:20px;
  cursor:pointer; letter-spacing:0.2px; transition:all 0.15s;
}
.prefs-clear-btn:active { opacity:0.7; }
#country-row { border-top:1px solid var(--border); padding-top:6px; margin-top:2px; }
.pref-row { display:flex; align-items:center; gap:6px; overflow:hidden; }
.pref-label { font-size:11px; font-weight:800; flex-shrink:0; width:16px; }
.pref-label.like { color:var(--low); }
.pref-tags { display:flex; gap:4px; overflow-x:auto; flex-wrap:nowrap; scrollbar-width:none; }
.pref-tags::-webkit-scrollbar { display:none; }
.pref-tag { font-size:11px; padding:3px 8px; border-radius:8px; font-weight:600; flex-shrink:0; }
.pref-tag.like { background:rgba(22,163,74,0.1); color:var(--low); border:1px solid rgba(22,163,74,0.25); }
.tag-del { background:none; border:none; cursor:pointer; font-size:12px; line-height:1; padding:0 0 0 3px; opacity:0.4; color:inherit; vertical-align:middle; }
.tag-del:hover { opacity:1; }
.pref-empty { font-size:11px; color:var(--muted); font-style:italic; flex-shrink:0; }
#prefs-divider { height:1px; background:var(--border); margin:6px 0 0; }

/* ── Saved strip ─────────────────────────────────────────────────────────── */
#saved-strip {
  display:none; gap:8px; overflow-x:auto; padding:8px 14px;
  scrollbar-width:none; flex-shrink:0; border-bottom:1px solid var(--border); background:var(--surface);
}
#saved-strip::-webkit-scrollbar { display:none; }
#saved-strip.has-items { display:flex; }
.saved-chip {
  display:flex; align-items:center; flex-shrink:0;
  background:var(--bg); border:1px solid var(--border);
  border-radius:10px; padding:5px 10px; border-left:3px solid var(--border);
  cursor:pointer; max-width:160px; transition:opacity 0.15s;
}
.saved-chip:active { opacity:0.7; }
.saved-chip-title { font-size:11px; font-weight:600; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

/* ── Saved overlay ───────────────────────────────────────────────────────── */
#saved-overlay {
  position:fixed; inset:0; z-index:100; display:none;
  background:rgba(0,0,0,0.45); backdrop-filter:blur(8px);
  align-items:flex-end; justify-content:center;
}
#saved-overlay.open { display:flex; }
#saved-overlay-card {
  width:100%; max-height:90dvh; overflow-y:auto;
  background:var(--bg); border-radius:22px 22px 0 0;
  padding:8px 0 calc(var(--safe-bot)+8px);
}
#saved-overlay-card .card { margin:0 12px 10px; }
#overlay-handle { width:36px; height:4px; background:var(--border); border-radius:2px; margin:0 auto 10px; }
#overlay-hint { text-align:center; font-size:11px; color:var(--muted); padding:0 0 8px; letter-spacing:0.3px; }


/* ── Empty / Loading ─────────────────────────────────────────────────────── */
#empty  { text-align:center; padding:60px 20px; color:var(--muted); font-size:15px; }
#loader { text-align:center; padding:40px; color:var(--muted); font-size:13px; }

/* ── Bottom nav ─────────────────────────────────────────────────────────── */
#bottom-nav {
  display:flex; border-top:1px solid var(--border);
  padding-bottom:var(--safe-bot); background:var(--surface); flex-shrink:0;
}
.nav-item {
  flex:1; display:flex; flex-direction:column; align-items:center;
  padding:10px 0; gap:3px; text-decoration:none;
  color:var(--muted); font-size:10px; font-weight:600;
  letter-spacing:0.3px; text-transform:uppercase; transition:color 0.15s;
}
.nav-item.active, .nav-item:active { color:var(--text); }
.nav-icon { font-size:20px; line-height:1; }
</style>
</head>
<body>
<div id="app">

  <!-- Header -->
  <div id="header">
    <div id="header-title-row">
      <div>
        <h1>Moodwire<span>.</span></h1>
        <div id="last-updated" style="font-size:10px;color:var(--muted);font-weight:500;margin-top:2px"></div>
      </div>
      <button id="toggle-prefs-btn" onclick="togglePrefs()" style="display:none">Preferences</button>
    </div>

    <!-- Anxiety widget -->
    <div id="anxiety-widget">
      <div id="anxiety-widget-header">
        <span id="anxiety-widget-title">Anxiety Index</span>
        <div id="anxiety-chips">
          <button class="anx-chip active" data-filter="">All</button>
          <button class="anx-chip low"    data-filter="low">Low</button>
          <button class="anx-chip mid"    data-filter="medium">Mid</button>
          <button class="anx-chip high"   data-filter="high">High</button>
        </div>
      </div>
      <div class="anx-row">
        <span class="anx-row-label">Global</span>
        <div class="anx-track"><div id="global-bar" class="anx-fill"></div></div>
        <span id="global-val" class="anx-num" style="color:var(--muted)">—</span>
      </div>
      <div class="anx-row" id="yours-row" style="display:none">
        <span class="anx-row-label">Yours</span>
        <div class="anx-track"><div id="yours-bar" class="anx-fill"></div></div>
        <span id="yours-val" class="anx-num"></span>
      </div>
    </div>

    <!-- Category chips -->
    <div id="filters">
      <div id="cat-chips"></div>
    </div>
  </div>

  <!-- Preferences -->
  <div id="prefs" style="display:none">
    <div class="prefs-header">
      <span class="prefs-title">Preferences</span>
      <button class="prefs-clear-btn" onclick="clearPreferences()">Clear</button>
    </div>
    <div class="pref-row">
      <span class="pref-label like">✓</span>
      <div class="pref-tags" id="liked-tags"></div>
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
      <div id="overlay-hint">↙ remove &nbsp;·&nbsp; ↘ like &nbsp;·&nbsp; ↓ close</div>
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
const STACK_SIZE    = 15;
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
  document.getElementById('feed').innerHTML = topics.map((t, i) => renderCard(t, i)).join('');
  attachSentinel();
  isLoading = false;
}

// ── Infinite scroll: append more cards ───────────────────────────────────
let isFetching = false;

async function loadMore() {
  if (isFetching) return;
  isFetching = true;
  const topics = await fetchTopics(10, 'mixed');
  if (topics.length) {
    const feed = document.getElementById('feed');
    // Re-attach sentinel after new cards
    const sentinel = document.getElementById('feed-sentinel');
    if (sentinel) sentinel.remove();
    topics.forEach((t, i) => {
      const tmp = document.createElement('div');
      tmp.innerHTML = renderCard(t, i);
      feed.appendChild(tmp.firstElementChild);
    });
    attachSentinel();
  }
  isFetching = false;
}

function attachSentinel() {
  const feed = document.getElementById('feed');
  const sentinel = document.createElement('div');
  sentinel.id = 'feed-sentinel';
  sentinel.style.height = '1px';
  feed.appendChild(sentinel);
  observer.observe(sentinel);
}

const observer = new IntersectionObserver(entries => {
  if (entries[0].isIntersecting) loadMore();
}, { rootMargin: '200px' });


function anxColor(a) {
  const idx = Math.min(9, Math.max(0, Math.floor(a)));
  return ['#16a34a','#4ade80','#a3e635','#facc15','#fb923c','#f97316','#ef4444','#dc2626','#b91c1c','#7f1d1d'][idx];
}

function renderCard(t, i) {
  const expand = !!t.personalized;
  const delay = Math.min(i * 40, 400);
  const bullets = t.bullets.map(b => `<li>${esc(b)}</li>`).join('');

  const heroArticle = t.articles.find(a => a.image_path);
  const hero = heroArticle
    ? `<div class="card-hero">
        <img src="${esc(heroArticle.image_path)}" alt="" loading="lazy"
             onerror="this.closest('.card-hero').remove()">
        <div class="card-hero-badge" style="background:${t.anxiety_color}">anxiety ${t.anxiety_avg.toFixed(1)}</div>
       </div>`
    : '';

  const articles = t.articles.map(a => {
    const anx   = parseFloat(a.anxiety ?? 5);
    const thumb = a.image_path
      ? `<img src="${esc(a.image_path)}" class="article-thumb" alt="">`
      : '';
    return `<a href="${esc(a.url)}" target="_blank" class="article-row">
      ${thumb}
      <div class="article-info">
        <div class="article-title">${esc(a.title)}</div>
        <div class="article-src">${esc(a.feed_name)}${a.time_ago ? ' · ' + esc(a.time_ago) : ''}</div>
      </div>
      <div class="article-anx" style="background:${anxColor(anx)}"></div>
    </a>`;
  }).join('');

  const anxPip = heroArticle ? '' :
    `<span class="anxiety-pip" style="background:${t.anxiety_color}">anxiety ${t.anxiety_avg.toFixed(1)}</span>`;

  return `
  <div class="card" data-id="${t.id}" data-state="0" data-anxiety="${t.anxiety_avg}"
       style="animation-delay:${delay}ms"
       onclick="tapCard(${t.id}, this)" data-state="${expand ? 1 : 0}">
    <div class="card-accent" style="background:${t.anxiety_color}"></div>
    ${hero}
    <div class="card-body">
      <div class="card-eyebrow">
        ${t.is_new   ? `<span class="badge badge-new">NEW</span>` : ''}
        ${t.category ? `<span class="badge badge-cat">${esc(t.category)}</span>` : ''}
        ${t.geo      ? `<span class="badge badge-geo">${esc(t.geo)}</span>` : ''}
        <span class="card-time">${esc(t.time_ago ?? '')}</span>
      </div>
      <div class="card-title">${esc(t.title)}</div>
      <div class="card-foot">
        <span class="card-article-count">${t.articles.length} article${t.articles.length !== 1 ? 's' : ''}</span>
        <div style="display:flex;align-items:center;gap:8px">
          ${anxPip}
          <button class="card-save-btn" onclick="keepCard(${t.id},this,event)" title="Keep">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
          </button>
        </div>
      </div>
    </div>

    <ul class="card-bullets card-section" style="display:${expand ? '' : 'none'}">${bullets}</ul>

    <div class="card-tap-hint" style="display:${expand ? '' : 'none'}">Tap for sources</div>

    <div class="articles-section card-section" style="display:none">
      <div class="articles-header">
        <span>Sources</span>
        <span>${t.articles.length}</span>
      </div>
      ${articles}
    </div>
  </div>`;
}

function keepCard(id, btn, event) {
  event.stopPropagation();
  const card = btn.closest('.card');
  if (btn.classList.contains('saved')) return;
  addToSaved(id, card, card.outerHTML);
  btn.classList.add('saved');
  btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>';
}

// 3-state tap: 0=title only → 1=+bullets → 2=+articles → 0
function tapCard(id, card) {
  if (event.target.closest('a')) return;
  const state = parseInt(card.dataset.state);
  const next  = (state + 1) % 3;

  card.dataset.state = next;
  card.querySelector('.card-bullets').style.display        = next >= 1 ? '' : 'none';
  card.querySelector('.card-tap-hint').style.display       = next === 1 ? '' : 'none';
  card.querySelector('.articles-section').style.display    = next >= 2 ? '' : 'none';
}

function timeAgo(dateStr) {
  const sec = Math.floor((Date.now() - new Date(dateStr)) / 1000);
  if (sec < 60)   return 'just now';
  if (sec < 3600) return Math.floor(sec / 60) + 'm ago';
  if (sec < 86400)return Math.floor(sec / 3600) + 'h ago';
  return Math.floor(sec / 86400) + 'd ago';
}

function esc(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

let globalAnxietyAvg = null;

// ── Load category chips + global anxiety ─────────────────────────────────
async function loadStats() {
  const res  = await fetch('api.php?action=stats');
  const data = await res.json();
  const wrap = document.getElementById('cat-chips');
  wrap.innerHTML = data.categories.map(c =>
    `<button class="chip" data-filter="${esc(c.category)}" data-group="category"
      onclick="setCategory(this)">${esc(c.category)} <span style="opacity:.5;font-size:10px">${c.c}</span></button>`
  ).join('');
  globalAnxietyAvg = parseFloat(data.global_anxiety_avg) || null;
  renderAnxietyBars(null, null);
  if (data.last_updated) {
    document.getElementById('last-updated').textContent = 'Updated ' + timeAgo(data.last_updated);
  }
}

// ── Anxiety filter chips ──────────────────────────────────────────────────
document.getElementById('anxiety-chips').addEventListener('click', e => {
  const chip = e.target.closest('.anx-chip');
  if (!chip) return;
  document.querySelectorAll('.anx-chip').forEach(c => c.classList.remove('active'));
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
  const color = card.querySelector('.card-accent')?.style.background || '#e8e8e8';
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
    card.querySelectorAll('.swipe-overlay').forEach(o=>o.style.opacity=0);
    card.dataset.state='2';
    const bullets=card.querySelector('.card-bullets');
    const articles=card.querySelector('.articles-section');
    if(bullets)bullets.style.display='';
    if(articles)articles.style.display='';
    
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
    const savedHtml = card.outerHTML;
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
  if (dir === 'right' || dir === 'left') {
    const res  = await fetch('api.php?action=swipe', {method:'POST',headers:{'Content-Type':'application/json'},
      body: JSON.stringify({topic_id:id, direction:dir, anxiety: parseFloat(card.dataset.anxiety ?? 5)})});
    const data = await res.json();
    renderPreferences(data.liked, data.countries);
    if (data.anxiety_avg !== undefined) renderAnxietyMeter(data.anxiety_avg, data.anxiety_count);
  }
}

// ── Swipe via event delegation (works for all cards, dynamic or not) ─────
let _swipeCard=null, _swipeOnSwipe=null, _sx=0, _sy=0, _dx=0, _dy=0, _swipeLocked=null;

function setupDelegatedSwipe(container, cardSelector, getHandler, verticalSwipe=false) {
  if (!verticalSwipe) container.style.touchAction = 'pan-y';
  else container.style.touchAction = 'none';

  container.addEventListener('touchstart', e => {
    const card = e.target.closest(cardSelector);
    if (!card) return;
    _swipeCard = card;
    _swipeOnSwipe = getHandler(card);
    _sx=e.touches[0].clientX; _sy=e.touches[0].clientY;
    _dx=_dy=0; _swipeLocked=null;
    card.classList.add('swiping');
  }, {passive:true});

  container.addEventListener('touchmove', e => {
    if (!_swipeCard) return;
    _dx=e.touches[0].clientX-_sx; _dy=e.touches[0].clientY-_sy;

    // Lock direction on first significant movement
    if (!_swipeLocked && (Math.abs(_dx) > 4 || Math.abs(_dy) > 4)) {
      _swipeLocked = Math.abs(_dx) >= Math.abs(_dy) ? 'h' : 'v';
    }

    if (_swipeLocked === 'v' && !verticalSwipe) {
      // Abort swipe, let browser scroll
      _swipeCard.style.transform = '';
      _swipeCard.classList.remove('swiping');
      _swipeCard = null; _swipeOnSwipe = null; _swipeLocked = null;
      return;
    }

    e.preventDefault();
    _swipeCard.style.transition = 'none';
    if (_swipeLocked === 'v') {
      _swipeCard.style.transform = `translateY(${_dy}px)`;
    } else {
      _swipeCard.style.transform = `translateX(${_dx}px) rotate(${_dx*0.03}deg)`;
    }
  }, {passive:false});

  container.addEventListener('touchend', () => {
    if (!_swipeCard) return;
    const card=_swipeCard, onSwipe=_swipeOnSwipe;
    card.classList.remove('swiping');
    card.style.transition='transform 0.28s ease, opacity 0.28s ease';
    const isUp   = _dy < -SWIPE_THRESHOLD && Math.abs(_dy)>Math.abs(_dx);
    const isDown = _dy >  SWIPE_THRESHOLD && Math.abs(_dy)>Math.abs(_dx);
    const isHoriz= Math.abs(_dx)>=SWIPE_THRESHOLD && Math.abs(_dx)>=Math.abs(_dy);
    if (isUp)         onSwipe('up');
    else if (isDown)  onSwipe('down');
    else if (isHoriz) onSwipe(_dx>0?'right':'left');
    else { card.style.transform=''; }
    _swipeCard=null; _swipeOnSwipe=null; _swipeLocked=null;
  }, {passive:true});
}

// Feed swipe delegation — preference tracking only, no batch loading
setupDelegatedSwipe(document.getElementById('feed'), '.card', card => dir => {
  if (dir==='down') return;
  handleSwipeAction(card, dir);
});

// Overlay delegation
setupDelegatedSwipe(document.getElementById('overlay-content'), '.card', card => dir => {
  const savedId = overlayActiveId;
  if (dir==='down') { closeSavedOverlay(); card.style.transform=''; return; }
  if (dir==='up')   { closeSavedOverlay(); return; }
  handleSwipeAction(card, dir, () => removeSaved(savedId));
  setTimeout(() => removeSaved(savedId), 320);
}, true);

function attachSwipe(card) {} // no-op — delegation handles all cards
function attachSwipeOverlay(card, savedId) {} // no-op — delegation handles overlay

// ── Preferences ───────────────────────────────────────────────────────────
function flag(cc) {
  return cc.toUpperCase().split('').map(c => String.fromCodePoint(0x1F1E6 + c.charCodeAt(0) - 65)).join('');
}

let prefsOpen = false;

function togglePrefs() {
  prefsOpen = !prefsOpen;
  const btn = document.getElementById('toggle-prefs-btn');
  document.getElementById('prefs').style.display         = prefsOpen ? '' : 'none';
  document.getElementById('prefs-divider').style.display = prefsOpen ? '' : 'none';
  btn.textContent = prefsOpen ? 'Hide' : 'Preferences';
  btn.classList.toggle('open', prefsOpen);
}

function renderPreferences(liked, countries) {
  const likedEntries   = Object.entries(liked ?? {}).sort((a,b) => b[1]-a[1]).slice(0,15);
  const countryEntries = Object.entries(countries ?? {}).filter(([,c]) => c > 0).sort((a,b) => b[1]-a[1]).slice(0,15);
  const hasPrefs = likedEntries.length > 0 || countryEntries.length > 0;
  document.getElementById('toggle-prefs-btn').style.display = hasPrefs ? '' : 'none';
  if (!hasPrefs) {
    prefsOpen = false;
    document.getElementById('prefs').style.display         = 'none';
    document.getElementById('prefs-divider').style.display = 'none';
    const btn = document.getElementById('toggle-prefs-btn');
    btn.textContent = 'Preferences';
    btn.classList.remove('open');
  }

  document.getElementById('liked-tags').innerHTML =
    likedEntries.length ? likedEntries.map(([t,c]) => {
      const scoreStyle = c < 0 ? 'color:var(--high)' : '';
      return `<span class="pref-tag like" style="${c < 0 ? 'opacity:0.7' : ''}">${esc(t)} <b style="${scoreStyle}">${c > 0 ? '+' : ''}${c}</b><button class="tag-del" onclick="removeTag('${esc(t)}','liked')">×</button></span>`;
    }).join('')
    : '<span class="pref-empty">swipe right to add</span>';

  const countryRow = document.getElementById('country-row');
  countryRow.style.display = countryEntries.length ? '' : 'none';
  document.getElementById('country-tags').innerHTML =
    countryEntries.map(([cc,c]) =>
      `<span class="pref-tag like" style="background:rgba(37,99,235,0.1);color:#2563eb;border-color:rgba(37,99,235,0.3)">${flag(cc)} ${cc}${c>1?` <b>${c}</b>`:''}<button class="tag-del" onclick="removeTag('${esc(cc)}','countries')">×</button></span>`
    ).join('');
}

function anxLevelColor(score) {
  return score >= 7 ? '#dc2626' : score >= 4 ? '#d97706' : '#16a34a';
}

function renderAnxietyBars(personalAvg, personalCount) {
  // Global bar
  if (globalAnxietyAvg !== null) {
    const color = anxLevelColor(globalAnxietyAvg);
    document.getElementById('global-bar').style.width      = (globalAnxietyAvg / 10 * 100) + '%';
    document.getElementById('global-bar').style.background = color;
    document.getElementById('global-val').style.color      = color;
    document.getElementById('global-val').textContent      = globalAnxietyAvg.toFixed(1);
  }

  // Personal bar
  const yoursRow = document.getElementById('yours-row');
  if (!personalAvg || personalCount === 0) { yoursRow.style.display = 'none'; return; }
  yoursRow.style.display = 'flex';

  const color = anxLevelColor(personalAvg);
  document.getElementById('yours-bar').style.width      = (personalAvg / 10 * 100) + '%';
  document.getElementById('yours-bar').style.background = color;
  document.getElementById('yours-val').style.color      = color;
  document.getElementById('yours-val').textContent      = personalAvg.toFixed(1);

}

function renderAnxietyMeter(avg, count) {
  renderAnxietyBars(avg, count);
}

async function removeTag(tag, list) {
  const res  = await fetch('api.php?action=remove_tag', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ tag, list })
  });
  const data = await res.json();
  renderPreferences(data.liked, data.countries);
  if (data.anxiety_avg !== undefined) renderAnxietyMeter(data.anxiety_avg, data.anxiety_count);
}

async function clearPreferences() {
  await fetch('api.php?action=clear_preferences', { method: 'POST' });
  prefsOpen = false;
  renderPreferences({}, {});
}

async function loadPreferences() {
  const res  = await fetch('api.php?action=preferences');
  const data = await res.json();
  renderPreferences(data.liked, data.countries);
  renderAnxietyMeter(data.anxiety_avg, data.anxiety_count);
}


// ── Init ──────────────────────────────────────────────────────────────────
loadStats();
loadPreferences();
loadTopics();
</script>
</body>
</html>
