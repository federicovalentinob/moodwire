<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Moodwire</title>
<style>
:root {
  --bg:       #ffffff;
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
#anxiety-widget { border-top:1px solid var(--border); padding:0; margin-bottom:10px; }
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
/* ── Anxiometer — twin semicircle gauges ──────────────────────────────────── */
#anxiety-widget-title { font-size:13px; font-weight:900; letter-spacing:0.3px; color:#0d0d0d; }
#gauge-wrap { display:flex; justify-content:center; margin:0 -16px 2px; }
#gauge-svg { width:100%; max-width:none; height:auto; overflow:visible; }
.gauge-zone { cursor:pointer; fill:none; stroke-width:14; stroke-linecap:butt; transition:opacity 0.25s; }
.gauge-zone.dimmed { opacity:0.28; }
.gauge-chassis { fill:none; stroke:#eaeaea; stroke-width:14; stroke-linejoin:round; stroke-linecap:butt; pointer-events:none; }
.gauge-inner-shadow { fill:none; stroke:rgba(0,0,0,0.18); stroke-width:1.5; stroke-linecap:butt; pointer-events:none; }
.gauge-title { font-size:10px; font-weight:800; fill:#0d0d0d; letter-spacing:1px; font-family:inherit; }
.gauge-axis-label { font-size:7px; font-weight:700; fill:#aaa; letter-spacing:0.5px; font-family:inherit; }
.gauge-value { font-size:18px; font-weight:900; fill:#0d0d0d; font-family:inherit; }

/* needles — rotate around their gauge center */
.needle-grp { transition:transform 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
#needle-global { transform-box:view-box; transform-origin:70px 88px; color:#0d0d0d; }
#needle-yours  { transform-box:view-box; transform-origin:210px 88px; color:#3b82f6; }
.needle-line  { stroke:currentColor; stroke-width:2.5; stroke-linecap:round; fill:none; }
.needle-hub   { fill:currentColor; }

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

/* ── Card (editorial / borderless) ──────────────────────────────────────── */
.card {
  background:transparent; border:none; box-shadow:none; border-radius:0;
  padding:0; margin:0;
  position:relative; cursor:pointer; user-select:none; touch-action:pan-y;
}
.card + .card { border-top:1px solid #e8e8e8; margin-top:22px; padding-top:22px; }
.card.slide-in { animation:slideIn 0.35s cubic-bezier(0.22,1,0.36,1) both; }
@keyframes slideIn {
  from { opacity:0; transform:translateY(18px); }
  to   { opacity:1; transform:translateY(0); }
}
#saved-overlay-card .card { touch-action:none; }
.card.swiping { transition:none !important; }
.swipe-overlay { display:none; }

.card-accent { display:none; }  /* dropped in editorial layout */

/* meta row — homogeneous editorial style: uppercase, dot-separated, color-coded */
.card-meta {
  display:flex; align-items:center; gap:0; margin-bottom:10px; flex-wrap:wrap;
  font-size:11px; font-weight:800; letter-spacing:0.5px; text-transform:uppercase;
  line-height:1;
  padding-right:36px; /* leave room for absolute save button */
}
.card-meta > * { display:inline-flex; align-items:center; line-height:1; }
.card-meta > * + *::before {
  content:""; display:inline-block; width:3px; height:3px; border-radius:50%;
  background:#c8c8c8; margin:0 8px; vertical-align:middle;
}
.card-time { color:#d32f2f; }
.badge { font-size:inherit; font-weight:inherit; padding:0; background:none; border:none; border-radius:0; letter-spacing:inherit; text-transform:inherit; }
.badge-cat { color:#777; }
.badge-geo { color:#7c3aed; }
.badge-new { color:#15803d; }
.card-save-btn {
  position:absolute; top:-6px; right:-6px;
  background:none; border:none; cursor:pointer; padding:6px;
  color:var(--muted); border-radius:6px; display:flex; align-items:center;
  transition:color 0.15s, background 0.15s; flex-shrink:0;
  z-index:2;
}
.card-save-btn:hover { color:var(--text); background:rgba(0,0,0,0.05); }
.card-save-btn.saved { color:var(--accent); }

/* title + byline */
.card-body { padding:0; position:relative; }
.card-title {
  font-size:22px; font-weight:800; line-height:1.22; letter-spacing:-0.5px;
  color:#111; margin-bottom:8px;
}
.card-byline {
  font-size:13px; color:#666; margin-bottom:12px;
  display:flex; align-items:center; gap:8px; flex-wrap:wrap;
}

/* hero image — now BELOW the title */
.card-hero { width:100%; max-height:240px; overflow:hidden; position:relative; background:#ebebeb; margin-bottom:6px; }
.card-hero img { width:100%; height:100%; object-fit:cover; display:block; }
/* hero overlay badge (kept for legacy; not currently rendered) */
.card-hero-badge {
  position:absolute; bottom:8px; right:10px;
  font-size:10.5px; font-weight:800; padding:3px 9px; border-radius:999px;
  color:#fff; letter-spacing:0.3px;
  display:inline-flex; align-items:center; gap:4px;
  text-transform:uppercase; flex-shrink:0;
  box-shadow:0 2px 6px rgba(0,0,0,0.2);
  text-shadow:0 1px 2px rgba(0,0,0,0.4);
}
.card-hero-badge b { font-size:13px; font-weight:900; letter-spacing:-0.3px; }

/* anxiety pip in meta row — flat, inherits typography, color via mood */
.anxiety-pip {
  display:inline-flex; align-items:center; gap:3px;
  background:none !important; box-shadow:none; padding:0; border-radius:0;
  color:inherit; font:inherit; letter-spacing:inherit; text-transform:inherit;
  flex-shrink:0;
}
.anxiety-pip b { font-weight:900; }
.anx-bubble-emoji { font-size:0.95em; line-height:1; filter:none; display:inline-block; }

.card-article-count { font-size:13px; color:#666; font-weight:500; }

/* expanded bullets */
.card-bullets { padding:14px 0 6px; margin-top:6px; }
.card-bullets li {
  font-size:16px; color:#222; line-height:1.6; font-weight:400;
  padding:6px 0 6px 18px; list-style:none; position:relative;
}
.card-bullets li::before {
  content:""; position:absolute; left:4px; top:14px;
  width:5px; height:5px; border-radius:50%; background:#999;
}

/* tap hint */
.card-tap-hint {
  padding:8px 0 0;
  font-size:11px; font-weight:600; color:var(--muted);
  text-align:center; letter-spacing:0.2px;
}

/* ── Anxiety mood styling (editorial: time + pip share one color per level) ── */
.card[data-anx-level="cool"]  .card-time,
.card[data-anx-level="cool"]  .anxiety-pip { color:#16a34a; }
.card[data-anx-level="hot"]   .card-time,
.card[data-anx-level="hot"]   .anxiety-pip { color:#ea580c; }
.card[data-anx-level="panic"] .card-time,
.card[data-anx-level="panic"] .anxiety-pip { color:#dc2626; }

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

/* ── Preferences word cloud ──────────────────────────────────────────────── */
#prefs {
  padding:18px 16px 16px; display:flex; flex-direction:column; gap:14px;
  background:var(--surface);
  max-height:50vh; overflow-y:auto;
  scrollbar-width:thin;
}
.prefs-header {
  display:flex; align-items:center; justify-content:space-between;
  padding:0 0 8px; border-bottom:1px solid var(--border);
}
.prefs-title { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.2px; color:var(--muted); }
.prefs-clear-btn {
  background:none; border:1.5px solid var(--border); color:var(--muted);
  font-size:13px; font-weight:700; padding:7px 18px; border-radius:24px;
  cursor:pointer; letter-spacing:0.3px; transition:all 0.15s;
  text-transform:uppercase;
}
.prefs-clear-btn:hover { color:var(--text); border-color:var(--text); }
.prefs-clear-btn:active { opacity:0.7; }
.prefs-actions { display:flex; gap:8px; }

/* a row = label + cloud */
.pref-row { display:flex; align-items:flex-start; gap:10px; }
.pref-label {
  font-size:11px; font-weight:800; flex-shrink:0; width:20px;
  padding-top:6px; color:var(--muted); text-align:center;
}
.pref-label.like { color:var(--low); }

/* the actual word cloud — tightly tiled, centered packing */
.pref-tags {
  display:flex; flex-wrap:wrap; align-items:center; justify-content:center;
  gap:4px 10px; line-height:1.15;
  overflow:visible;
}
.pref-tags::-webkit-scrollbar { display:none; }

/* a single word in the cloud — all rendered in black */
.pref-tag {
  display:inline-flex; align-items:baseline; gap:3px;
  padding:0; border:none; background:none;
  font-weight:800; letter-spacing:-0.2px;
  color:#0d0d0d;
  cursor:default; flex-shrink:0;
  transition:opacity 0.15s;
}
.pref-tag.negative { color:#0d0d0d; opacity:0.45; }
.pref-tag.country  { color:#0d0d0d; }
.pref-tag .tag-del {
  background:none; border:none; cursor:pointer;
  font-size:0.6em; line-height:1; padding:0 0 0 2px;
  color:inherit; opacity:0; transition:opacity 0.12s;
  vertical-align:middle;
}
.pref-tag:hover .tag-del { opacity:0.55; }
.pref-tag .tag-del:hover { opacity:1; }

.pref-empty { font-size:12px; color:var(--muted); font-style:italic; }
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

    <!-- Anxiometer (cartoonish gauge — zones double as filter buttons) -->
    <div id="anxiety-widget">
      <div id="gauge-wrap">
        <svg viewBox="0 20 280 110" id="gauge-svg" aria-label="Anxiometers">
          <!-- LEFT gauge: Current feed (5 colored segments, 3 filter buckets) -->
          <g id="gauge-global">
            <!-- 5 colored segments -->
            <path class="gauge-zone" data-filter="low"    d="M 27 88        A 43 43 0 0 1 35.21 62.72"  stroke="#15803d" />
            <path class="gauge-zone" data-filter="low"    d="M 35.21 62.72  A 43 43 0 0 1 56.71 47.11"  stroke="#65a30d" />
            <path class="gauge-zone" data-filter="medium" d="M 56.71 47.11  A 43 43 0 0 1 83.29 47.11"  stroke="#eab308" />
            <path class="gauge-zone" data-filter="high"   d="M 83.29 47.11  A 43 43 0 0 1 104.79 62.72" stroke="#ea580c" />
            <path class="gauge-zone" data-filter="high"   d="M 104.79 62.72 A 43 43 0 0 1 113 88"       stroke="#dc2626" />
            <!-- inner-shadow line at the outer edge of the colored band -->
            <path class="gauge-inner-shadow" d="M 20.5 88 A 49.5 49.5 0 0 1 119.5 88" />
            <g id="needle-global" class="needle-grp">
              <line class="needle-line" x1="70" y1="88" x2="70" y2="50" />
              <circle class="needle-hub" cx="70" cy="88" r="6" />
            </g>
            <text x="70"  y="80"  text-anchor="middle" class="gauge-value" id="value-global">—</text>
            <text x="70"  y="108" text-anchor="middle" class="gauge-title">CURRENT FEED</text>
          </g>

          <!-- RIGHT gauge: You (hidden until personal data exists) -->
          <g id="gauge-yours" style="display:none">
<path class="gauge-zone" data-filter="low"    d="M 167 88        A 43 43 0 0 1 175.21 62.72" stroke="#15803d" />
            <path class="gauge-zone" data-filter="low"    d="M 175.21 62.72  A 43 43 0 0 1 196.71 47.11" stroke="#65a30d" />
            <path class="gauge-zone" data-filter="medium" d="M 196.71 47.11  A 43 43 0 0 1 223.29 47.11" stroke="#eab308" />
            <path class="gauge-zone" data-filter="high"   d="M 223.29 47.11  A 43 43 0 0 1 244.79 62.72" stroke="#ea580c" />
            <path class="gauge-zone" data-filter="high"   d="M 244.79 62.72  A 43 43 0 0 1 253 88"       stroke="#dc2626" />
            <path class="gauge-inner-shadow" d="M 160.5 88 A 49.5 49.5 0 0 1 259.5 88" />
            <g id="needle-yours" class="needle-grp">
              <line class="needle-line" x1="210" y1="88" x2="210" y2="50" />
              <circle class="needle-hub" cx="210" cy="88" r="6" />
            </g>
            <text x="210" y="80"  text-anchor="middle" class="gauge-value" id="value-yours">—</text>
            <text x="210" y="108" text-anchor="middle" class="gauge-title">YOU</text>
          </g>
        </svg>
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
      <div class="prefs-actions">
        <button class="prefs-clear-btn" onclick="clearPreferences()">Clear</button>
        <button class="prefs-clear-btn" onclick="togglePrefs()">Hide</button>
      </div>
    </div>
    <div class="pref-row">
      <div class="pref-tags" id="liked-tags"></div>
    </div>
    <div class="pref-row" id="country-row" style="display:none">
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
  // Move the black "Current news level" arrow to reflect the current filter's average
  if (data.current_anxiety_avg != null) {
    globalAnxietyAvg = parseFloat(data.current_anxiety_avg);
    moveGlobalNeedle();
  }
  return data.topics;
}

function zoneColor(v) {
  if (v <= 10/3) return '#16a34a'; // cool zone
  if (v <= 20/3) return '#f59e0b'; // hot zone
  return '#dc2626';                 // panic zone
}

function moveGlobalNeedle() {
  if (globalAnxietyAvg == null) return;
  const angle = anxValueToRotation(globalAnxietyAvg);
  document.getElementById('needle-global').style.transform = `rotate(${angle}deg)`;
  document.getElementById('needle-global').style.color = zoneColor(globalAnxietyAvg);
  const v = document.getElementById('value-global');
  if (v) v.textContent = globalAnxietyAvg.toFixed(1);
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

  const anxLevel = t.anxiety_avg >= 7 ? 'panic' : (t.anxiety_avg >= 4 ? 'hot' : 'cool');
  const anxEmoji = anxLevel === 'panic' ? '⚠️' : (anxLevel === 'hot' ? '🔥' : '🌿');

  const heroArticle = t.articles.find(a => a.image_path);
  const hero = heroArticle
    ? `<div class="card-hero">
        <img src="${esc(heroArticle.image_path)}" alt="" loading="lazy"
             onerror="this.closest('.card-hero').remove()">
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

  const anxPip =
    `<span class="anxiety-pip" style="background:${t.anxiety_color}"><span class="anx-bubble-emoji">${anxEmoji}</span> <b>${t.anxiety_avg.toFixed(1)}</b></span>`;

  const articleCountTxt = `${t.articles.length} article${t.articles.length !== 1 ? 's' : ''}`;

  return `
  <div class="card" data-id="${t.id}" data-state="${expand ? 1 : 0}" data-anxiety="${t.anxiety_avg}" data-anx-level="${anxLevel}"
       style="animation-delay:${delay}ms"
       onclick="tapCard(${t.id}, this)">
    <div class="card-body">
      <button class="card-save-btn" onclick="keepCard(${t.id},this,event)" title="Keep">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
      </button>
      <div class="card-meta">
        ${anxPip}
        <span class="card-time">${esc(t.time_ago ?? '')}</span>
        ${t.is_new   ? `<span class="badge badge-new">NEW</span>` : ''}
        ${t.category ? `<span class="badge badge-cat">${esc(t.category)}</span>` : ''}
        ${t.geo      ? `<span class="badge badge-geo">${esc(t.geo)}</span>` : ''}
      </div>
      <div class="card-title">${esc(t.title)}</div>
      <div class="card-byline">
        <span class="card-article-count">${articleCountTxt}</span>
      </div>
      ${hero}
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

  // First open (0 → 1): bump "Your level" needle just like a right-swipe.
  if (state === 0 && next === 1) {
    fetch('api.php?action=view', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ topic_id: id, anxiety: parseFloat(card.dataset.anxiety ?? 5) })
    }).then(r => r.json()).then(data => {
      if (data.anxiety_avg !== undefined) renderAnxietyMeter(data.anxiety_avg, data.anxiety_count);
    }).catch(() => {});
  }
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
document.getElementById('gauge-svg').addEventListener('click', e => {
  const zone = e.target.closest('.gauge-zone');
  if (!zone) return;
  const f = zone.dataset.filter;
  if (activeAnxiety === f) {
    activeAnxiety = '';
    document.querySelectorAll('.gauge-zone').forEach(z => z.classList.remove('dimmed'));
  } else {
    activeAnxiety = f;
    document.querySelectorAll('.gauge-zone').forEach(z =>
      z.classList.toggle('dimmed', z.dataset.filter !== f));
  }
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

  // map count to font-size for the word cloud — bigger = more important
  // size range tightens as tag count grows so everything fits without scrolling
  const cloudSize = (c, maxAbs, total) => {
    const minPx = total > 12 ? 10 : 12;
    const maxPx = total > 12 ? 20 : (total > 8 ? 24 : 28);
    const scale = maxAbs > 0 ? Math.min(1, Math.abs(c) / maxAbs) : 0;
    return Math.round(minPx + (maxPx - minPx) * scale);
  };
  const likedMax = likedEntries.length ? Math.max(...likedEntries.map(([,c]) => Math.abs(c))) : 0;
  const countryMax = countryEntries.length ? Math.max(...countryEntries.map(([,c]) => c)) : 0;

  document.getElementById('liked-tags').innerHTML =
    likedEntries.length ? likedEntries.map(([t,c]) => {
      const cls = c < 0 ? 'pref-tag negative' : 'pref-tag';
      const px  = cloudSize(c, likedMax, likedEntries.length);
      return `<span class="${cls}" style="font-size:${px}px"
                title="${esc(t)} (${c > 0 ? '+' : ''}${c})">${esc(t)}<button class="tag-del" onclick="removeTag('${esc(t)}','liked')">×</button></span>`;
    }).join('')
    : '<span class="pref-empty">swipe right to add</span>';

  const countryRow = document.getElementById('country-row');
  countryRow.style.display = countryEntries.length ? '' : 'none';
  document.getElementById('country-tags').innerHTML =
    countryEntries.map(([cc,c]) => {
      const px = cloudSize(c, countryMax, countryEntries.length);
      return `<span class="pref-tag country" style="font-size:${px}px"
                title="${cc} (${c})">${flag(cc)} ${cc}<button class="tag-del" onclick="removeTag('${esc(cc)}','countries')">×</button></span>`;
    }).join('');
}

function anxLevelColor(score) {
  return score >= 7 ? '#dc2626' : score >= 4 ? '#d97706' : '#16a34a';
}

function anxValueToOffset(v) {
  // Legacy: track spans x=20 (value 0) to x=260 (value 10) → 240 user units across 10 points.
  const clamped = Math.max(0, Math.min(10, v));
  return (clamped / 10) * 240;
}

function anxValueToRotation(v) {
  // Maps 0..10 anxiety to needle rotation -90°..+90° (left = cool, right = panic).
  const clamped = Math.max(0, Math.min(10, v));
  return (clamped / 10) * 180 - 90;
}

function renderAnxietyBars(personalAvg, personalCount) {
  // Global needle
  if (globalAnxietyAvg !== null) {
    const angle = anxValueToRotation(globalAnxietyAvg);
    document.getElementById('needle-global').style.transform = `rotate(${angle}deg)`;
    document.getElementById('needle-global').style.color = zoneColor(globalAnxietyAvg);
    const v = document.getElementById('value-global');
    if (v) v.textContent = globalAnxietyAvg.toFixed(1);
  }

  // Personal gauge — show entire right gauge only when we have data
  const yoursGauge = document.getElementById('gauge-yours');
  if (!personalAvg || personalCount === 0) {
    yoursGauge.style.display = 'none';
    return;
  }
  yoursGauge.style.display = '';
  const angle = anxValueToRotation(personalAvg);
  document.getElementById('needle-yours').style.transform = `rotate(${angle}deg)`;
  const vy = document.getElementById('value-yours');
  if (vy) vy.textContent = personalAvg.toFixed(1);
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
