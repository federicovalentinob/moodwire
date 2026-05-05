<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Moodwire</title>
<style>
:root {
  --bg:      #0f1117;
  --surface: #1a1d27;
  --border:  #2a2d3a;
  --text:    #f1f5f9;
  --muted:   #64748b;
  --accent:  #3b82f6;
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
  padding: calc(var(--safe-top) + 12px) 16px 0;
  background: var(--bg);
  flex-shrink: 0;
}
#header h1 { font-size:22px; font-weight:800; letter-spacing:-0.5px; margin-bottom:12px; }
#header h1 span { color:var(--accent); }

/* ── Filter chips ───────────────────────────────────────────────────────── */
#filters {
  display:flex; gap:8px; overflow-x:auto; padding-bottom:12px;
  scrollbar-width:none; -webkit-overflow-scrolling:touch;
}
#filters::-webkit-scrollbar { display:none; }
.chip {
  flex-shrink:0; padding:6px 14px; border-radius:20px;
  border:1.5px solid var(--border); background:transparent;
  color:var(--muted); font-size:13px; font-weight:600;
  cursor:pointer; white-space:nowrap; transition:all 0.15s;
  min-width:64px; text-align:center;
}
.chip.active        { background:var(--accent); border-color:var(--accent); color:#fff; }
.chip.active.low    { background:var(--low);  border-color:var(--low);  }
.chip.active.mid    { background:var(--mid);  border-color:var(--mid);  }
.chip.active.high   { background:var(--high); border-color:var(--high); }
.chip-sep { flex-shrink:0; width:1px; background:var(--border); margin:4px 4px; }

/* ── Feed ───────────────────────────────────────────────────────────────── */
#feed {
  flex:1; overflow-y:auto; padding:8px 12px;
  -webkit-overflow-scrolling:touch; scrollbar-width:none;
}
#feed::-webkit-scrollbar { display:none; }

/* ── Card ───────────────────────────────────────────────────────────────── */
.card {
  background:var(--surface); border-radius:var(--radius);
  margin-bottom:10px; overflow:hidden;
  border-left:4px solid var(--border);
  animation:fadeUp 0.25s ease both;
}
@keyframes fadeUp {
  from { opacity:0; transform:translateY(12px); }
  to   { opacity:1; transform:translateY(0); }
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
.badge-cat   { background:#1e293b; color:#94a3b8; }
.badge-geo   { background:#1e293b; color:#7c3aed; }
.badge-type  { background:#1e293b; color:#0891b2; }
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
  font-size:14px; color:#cbd5e1; line-height:1.5;
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
.article-row:active { background:rgba(255,255,255,0.04); }

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
#prefs {
  padding:10px 16px 0; display:flex; flex-direction:column; gap:6px;
}
.pref-row { display:flex; align-items:flex-start; gap:8px; }
.pref-label {
  font-size:10px; font-weight:800; text-transform:uppercase;
  letter-spacing:0.5px; padding-top:3px; flex-shrink:0; width:20px;
}
.pref-label.like    { color:var(--low); }
.pref-label.dislike { color:var(--high); }
.pref-tags { display:flex; flex-wrap:wrap; gap:4px; }
.pref-tag {
  font-size:11px; padding:2px 8px; border-radius:10px;
  font-weight:600; cursor:pointer;
}
.pref-tag.like    { background:rgba(22,163,74,0.15);  color:var(--low);  border:1px solid rgba(22,163,74,0.3); }
.pref-tag.dislike { background:rgba(220,38,38,0.15); color:var(--high); border:1px solid rgba(220,38,38,0.3); }
.pref-empty { font-size:11px; color:var(--muted); font-style:italic; }
#prefs-divider { height:1px; background:var(--border); margin:10px 16px 0; }

/* ── Swipe gesture ──────────────────────────────────────────────────────── */
.card { position:relative; cursor:pointer; user-select:none; touch-action:pan-y; }
.card.swiping { transition:none !important; }
.swipe-overlay {
  position:absolute; inset:0; border-radius:var(--radius);
  display:flex; align-items:center; justify-content:center;
  font-size:36px; opacity:0; pointer-events:none; transition:opacity 0.1s;
  font-weight:900;
}
.swipe-overlay.like    { background:rgba(22,163,74,0.25); }
.swipe-overlay.dislike { background:rgba(220,38,38,0.25); }

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
  background:var(--bg); flex-shrink:0;
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
  </div>
  <div id="prefs-divider" style="display:none"></div>

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
let activeAnxiety  = '';
let activeCategory = '';
let expandedCards  = new Set();

// ── Fetch & render topics ──────────────────────────────────────────────────
async function loadTopics() {
  document.getElementById('feed').innerHTML = '<div id="loader">Loading…</div>';
  const params = new URLSearchParams();
  if (activeAnxiety)  params.set('anxiety',  activeAnxiety);
  if (activeCategory) params.set('category', activeCategory);
  const res   = await fetch('api.php?action=topics&' + params);
  const topics = await res.json();
  renderFeed(topics);
}

function renderFeed(topics) {
  const feed = document.getElementById('feed');
  if (!topics.length) {
    feed.innerHTML = '<div id="empty">No topics found.</div>';
    return;
  }
  feed.innerHTML = topics.map((t, i) => renderCard(t, i)).join('');
  document.querySelectorAll('.card').forEach(attachSwipe);
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
  </div>`;
}

// Signal interest — accepts tags array or topic_id, optional source and anxiety
async function signal(tagsOrId, direction = 'right', source = 'swipe', anxiety = null) {
  const body = typeof tagsOrId === 'number'
    ? { topic_id: tagsOrId, direction, source, anxiety }
    : { tags: tagsOrId, direction, source, anxiety };
  if (Array.isArray(tagsOrId) && !tagsOrId.length) return;
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
  const url = el.href;
  if (!el.dataset.signaled) {
    el.dataset.signaled = '1';
    const tags    = JSON.parse(el.dataset.tags ?? '[]');
    const anxiety = parseFloat(el.dataset.anxiety ?? 5);
    await signal(tags, 'right', 'click', anxiety);
  }
  window.location.href = url;
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

function attachSwipe(card) {
  let startX = null, startY = null, dx = 0;

  card.addEventListener('touchstart', e => {
    startX = e.touches[0].clientX;
    startY = e.touches[0].clientY;
    dx = 0;
    card.classList.add('swiping');
  }, { passive: true });

  card.addEventListener('touchmove', e => {
    if (startX === null) return;
    dx = e.touches[0].clientX - startX;
    const dy = e.touches[0].clientY - startY;
    if (Math.abs(dy) > Math.abs(dx) + 10) return; // vertical scroll wins
    e.preventDefault();
    card.style.transform = `translateX(${dx}px) rotate(${dx * 0.03}deg)`;
    const like    = card.querySelector('.swipe-overlay.like');
    const dislike = card.querySelector('.swipe-overlay.dislike');
    like.style.opacity    = dx > 0 ? Math.min(dx / SWIPE_THRESHOLD, 1) : 0;
    dislike.style.opacity = dx < 0 ? Math.min(-dx / SWIPE_THRESHOLD, 1) : 0;
  }, { passive: false });

  card.addEventListener('touchend', async () => {
    card.classList.remove('swiping');
    card.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
    if (Math.abs(dx) >= SWIPE_THRESHOLD) {
      const dir = dx > 0 ? 'right' : 'left';
      const id  = parseInt(card.dataset.id);
      // Fly off screen
      card.style.transform = `translateX(${dir === 'right' ? '120vw' : '-120vw'}) rotate(${dir === 'right' ? 20 : -20}deg)`;
      card.style.opacity = '0';
      setTimeout(() => card.remove(), 300);
      // Only signal tags on swipe right if card was never opened (state 0)
      const wasUntouched = parseInt(card.dataset.state) === 0;
      if (dir === 'left' || wasUntouched) {
        const res = await fetch('api.php?action=swipe', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ topic_id: id, direction: dir })
        });
        const data = await res.json();
        renderPreferences(data.liked, data.disliked);
      }
    } else {
      card.style.transform = '';
      card.querySelector('.swipe-overlay.like').style.opacity    = 0;
      card.querySelector('.swipe-overlay.dislike').style.opacity = 0;
    }
    startX = null;
  }, { passive: true });
}

// ── Preferences ───────────────────────────────────────────────────────────
function renderPreferences(liked, disliked) {
  const hasPrefs = Object.keys(liked ?? {}).length || Object.keys(disliked ?? {}).length;
  document.getElementById('prefs').style.display         = hasPrefs ? '' : 'none';
  document.getElementById('prefs-divider').style.display = hasPrefs ? '' : 'none';
  document.getElementById('clear-btn').style.display     = hasPrefs ? '' : 'none';

  const likedEntries    = Object.entries(liked    ?? {}).sort((a,b) => b[1]-a[1]).slice(0,15);
  const dislikedEntries = Object.entries(disliked ?? {}).sort((a,b) => b[1]-a[1]).slice(0,15);

  document.getElementById('liked-tags').innerHTML =
    likedEntries.length ? likedEntries.map(([t,c]) =>
      `<span class="pref-tag like">${esc(t)}${c>1?` <b>${c}</b>`:''}</span>`).join('')
    : '<span class="pref-empty">swipe right to add</span>';

  document.getElementById('disliked-tags').innerHTML =
    dislikedEntries.length ? dislikedEntries.map(([t,c]) =>
      `<span class="pref-tag dislike">${esc(t)}${c>1?` <b>${c}</b>`:''}</span>`).join('')
    : '<span class="pref-empty">swipe left to add</span>';
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

async function clearPreferences() {
  await fetch('api.php?action=clear_preferences', { method: 'POST' });
  renderPreferences({}, {});
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
