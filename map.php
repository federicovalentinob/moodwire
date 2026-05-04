<?php
require_once __DIR__ . '/functions.php';

$types      = ['educative', 'informative', 'entertainment'];
$anx_bands  = [
    'high'   => ['label' => 'High Anxiety',   'min' => 7,  'max' => 10],
    'medium' => ['label' => 'Medium Anxiety',  'min' => 4,  'max' => 6.99],
    'low'    => ['label' => 'Low Anxiety',     'min' => 0,  'max' => 3.99],
];

$topics = db()->query("
    SELECT t.title, t.content_type, t.category, t.anxiety_avg,
           COUNT(ato.article_id) as article_count
    FROM topics t
    LEFT JOIN article_topics ato ON ato.topic_id = t.id
    WHERE t.category IS NOT NULL AND t.content_type IS NOT NULL
    GROUP BY t.id
    HAVING article_count > 0
")->fetchAll();

// Category colours
$cat_colors = [
    'Politics'      => '#3b82f6',
    'Geopolitics'   => '#8b5cf6',
    'Economy'       => '#10b981',
    'Technology'    => '#06b6d4',
    'Science'       => '#0ea5e9',
    'Health'        => '#f43f5e',
    'Society'       => '#f59e0b',
    'Crime'         => '#991b1b',
    'Environment'   => '#16a34a',
    'Sports'        => '#ea580c',
    'Entertainment' => '#ec4899',
    'Travel'        => '#7c3aed',
    'Food'          => '#ca8a04',
];

// Group topics into 9 cells (type × anxiety)
$cells = [];
foreach ($types as $type) {
    foreach (array_keys($anx_bands) as $band) {
        $cells[$type][$band] = [];
    }
}
foreach ($topics as $t) {
    $anx = (float)$t['anxiety_avg'];
    $band = $anx >= 7 ? 'high' : ($anx >= 4 ? 'medium' : 'low');
    $cells[$t['content_type']][$band][] = $t;
}

// Compute cell weights (for grid sizing)
$type_counts = array_map(fn($b) => array_sum(array_map(fn($c) => count($c), $b)), $cells);
$band_counts = [];
foreach (array_keys($anx_bands) as $band) {
    $band_counts[$band] = array_sum(array_map(fn($t) => count($cells[$t][$band]), $types));
}
$total = max(1, array_sum($type_counts));

// Build JSON for D3
$d3_data = ['name' => 'root', 'children' => []];
foreach ($types as $type) {
    $type_node = ['name' => $type, 'children' => []];
    foreach (array_keys($anx_bands) as $band) {
        $band_node = ['name' => $band, 'children' => []];

        // Group topics by category within each cell
        $by_cat = [];
        foreach ($cells[$type][$band] as $t) {
            $by_cat[$t['category']][] = $t;
        }
        foreach ($by_cat as $cat => $cat_topics) {
            $cat_node = [
                'name'     => $cat,
                'category' => $cat,
                'color'    => $cat_colors[$cat] ?? '#94a3b8',
                'children' => [],
            ];
            foreach ($cat_topics as $t) {
                $cat_node['children'][] = [
                    'name'     => $t['title'],
                    'category' => $t['category'],
                    'anxiety'  => $t['anxiety_avg'],
                    'count'    => (int)$t['article_count'],
                    'type'     => $t['content_type'],
                    'band'     => $band,
                    'color'    => $cat_colors[$t['category']] ?? '#94a3b8',
                ];
            }
            $band_node['children'][] = $cat_node;
        }
        $type_node['children'][] = $band_node;
    }
    $d3_data['children'][] = $type_node;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Moodwire — Map</title>
<link rel="stylesheet" href="style.css">
<style>
  #map-svg { width: 100%; border-radius: 10px; overflow: hidden; }
  .topic-rect { cursor: pointer; stroke-width: 2; transition: opacity 0.15s; }
  .topic-rect:hover { opacity: 0.75; stroke-width: 3; }
  .cell-label { pointer-events: none; font-family: -apple-system, sans-serif; }
  .axis-label { font-size: 12px; font-weight: 700; fill: #1e293b; font-family: -apple-system, sans-serif; }
  .band-line { stroke: white; stroke-width: 3; }
  .type-line { stroke: white; stroke-width: 3; }

  /* Tooltip */
  #tooltip {
    position: fixed;
    background: #1e293b;
    color: #f1f5f9;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 12px;
    pointer-events: none;
    display: none;
    z-index: 999;
    max-width: 260px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    line-height: 1.5;
  }

  /* Legend */
  .legend-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
  .legend-item { display: flex; align-items: center; gap: 5px; font-size: 11px; }
  .legend-dot  { width: 12px; height: 12px; border-radius: 3px; flex-shrink: 0; }

  /* Axis labels outside map */
  .map-outer { position: relative; }
  .axis-x-labels { display: flex; margin-left: 48px; margin-bottom: 4px; }
  .axis-x-label  { flex: 1; text-align: center; font-size: 12px; font-weight: 700; color: #1e3a5f; text-transform: uppercase; letter-spacing: 0.5px; }
  .axis-y-wrap   { display: flex; align-items: stretch; }
  .axis-y-labels { display: flex; flex-direction: column; width: 48px; flex-shrink: 0; }
  .axis-y-label  {
    display: flex; align-items: center; justify-content: center;
    writing-mode: vertical-lr; transform: rotate(180deg);
    font-size: 11px; font-weight: 700; color: #1e3a5f;
    text-transform: uppercase; letter-spacing: 0.5px;
    flex-shrink: 0;
  }
</style>
</head>
<body>
<div class="container">
  <nav class="nav">
    <a href="index.php" class="nav-logo">Moodwire</a>
    <a href="index.php">Topics</a>
    <a href="map.php" class="active">Map</a>
    <a href="feeds.php">Feeds</a>
    <a href="run.php">Pipeline</a>
  </nav>

  <h1>Topic Map</h1>

  <!-- Category legend -->
  <div class="legend-grid">
    <?php foreach ($cat_colors as $cat => $col): ?>
      <div class="legend-item">
        <div class="legend-dot" style="background:<?= $col ?>"></div>
        <?= $cat ?>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Anxiety border legend -->
  <div style="display:flex;align-items:center;gap:6px;margin-bottom:20px;font-size:11px;color:#64748b">
    <span style="font-weight:700;color:#1e293b">Border = Anxiety:</span>
    <span>0</span>
    <?php
    $palette = ['#16a34a','#4ade80','#a3e635','#facc15','#fb923c','#f97316','#ef4444','#dc2626','#b91c1c','#7f1d1d'];
    foreach ($palette as $i => $col):
    ?>
      <div style="width:28px;height:12px;background:<?= $col ?>;border-radius:3px" title="<?= $i ?>–<?= $i+1 ?>"></div>
    <?php endforeach; ?>
    <span>10</span>
  </div>

  <div class="map-outer">
    <!-- X axis labels (Type) -->
    <div class="axis-x-labels">
      <div class="axis-x-label">Educative</div>
      <div class="axis-x-label">Informative</div>
      <div class="axis-x-label">Entertainment</div>
    </div>

    <div class="axis-y-wrap">
      <!-- Y axis labels (Anxiety) — heights set by JS to match bands -->
      <div class="axis-y-labels" id="y-labels">
        <div class="axis-y-label" id="yl-high"   style="color:#dc2626">High ↑</div>
        <div class="axis-y-label" id="yl-medium" style="color:#ca8a04">Medium</div>
        <div class="axis-y-label" id="yl-low"    style="color:#16a34a">Low ↓</div>
      </div>

      <!-- Map SVG -->
      <svg id="map-svg"></svg>
    </div>
  </div>

  <div id="tooltip"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js"></script>
<script>
const data    = <?= json_encode($d3_data) ?>;
const types   = ['educative','informative','entertainment'];
const bands   = ['high','medium','low'];
const bandLabels = {high:'High',medium:'Medium',low:'Low'};

// 10-level green→yellow→red gradient for anxiety (0–10)
const anxietyPalette = [
  '#16a34a', // 0–1  deep green
  '#4ade80', // 1–2  light green
  '#a3e635', // 2–3  yellow-green
  '#facc15', // 3–4  yellow
  '#fb923c', // 4–5  light orange (was amber border)
  '#f97316', // 5–6  orange
  '#ef4444', // 6–7  light red
  '#dc2626', // 7–8  red
  '#b91c1c', // 8–9  dark red
  '#7f1d1d', // 9–10 very dark red
];
function anxietyColor(a) {
  const idx = Math.min(9, Math.max(0, Math.floor(a)));
  return anxietyPalette[idx];
}

const W = document.getElementById('map-svg').parentElement.clientWidth;
const H = Math.round(W * 0.65);
document.getElementById('map-svg').setAttribute('viewBox', `0 0 ${W} ${H}`);
document.getElementById('map-svg').setAttribute('height', H);

const svg = d3.select('#map-svg');
const tip = document.getElementById('tooltip');

// Compute column widths & row heights proportional to total article counts
function articlesInCell(type, band) {
  const typeNode = data.children.find(d => d.name === type);
  const bandNode = typeNode?.children.find(d => d.name === band);
  return (bandNode?.children || []).reduce((s, cat) =>
    s + (cat.children || []).reduce((s2, t) => s2 + (t.count||1), 0), 0);
}

const typeTotals = types.map(t => bands.reduce((s,b) => s + articlesInCell(t,b), 0));
const bandTotals = bands.map(b => types.reduce((s,t) => s + articlesInCell(t,b), 0));
const total = typeTotals.reduce((a,b) => a+b, 0) || 1;

// Minimum 10% of axis to avoid invisible cells
const colWidths  = typeTotals.map(n => Math.max(n/total * W, W * 0.10));
const rowHeights = bandTotals.map(n => Math.max(n/total * H, H * 0.10));

// Normalize
const wSum = colWidths.reduce((a,b)=>a+b,0);
const hSum = rowHeights.reduce((a,b)=>a+b,0);
const cw = colWidths.map(w => w/wSum * W);
const rh = rowHeights.map(h => h/hSum * H);

// Cumulative offsets
const cx = [0, cw[0], cw[0]+cw[1]];
const ry = [0, rh[0], rh[0]+rh[1]];

// Align Y-axis labels to actual band heights
['high','medium','low'].forEach((b,i) => {
  const el = document.getElementById('yl-' + b);
  if (el) el.style.height = rh[i] + 'px';
});

// Draw each cell as a mini treemap
types.forEach((type, ti) => {
  bands.forEach((band, bi) => {
    const typeNode = data.children.find(d => d.name === type);
    const bandNode = typeNode?.children.find(d => d.name === band);
    const topics   = bandNode?.children || [];
    if (!topics.length) return;

    const x = cx[ti], y = ry[bi], w = cw[ti], h = rh[bi];

    // Build treemap for this cell — with category as intermediate level
    const root = d3.hierarchy({ children: topics })
      .sum(d => d.count || 1)
      .sort((a,b) => {
        // Sort categories by average anxiety desc, then leaves by anxiety desc
        const aAnx = a.data.anxiety ?? (a.children ? a.children.reduce((s,c)=>s+(c.data.anxiety||0),0)/a.children.length : 0);
        const bAnx = b.data.anxiety ?? (b.children ? b.children.reduce((s,c)=>s+(c.data.anxiety||0),0)/b.children.length : 0);
        return bAnx - aAnx;
      });

    d3.treemap()
      .size([w, h])
      .padding(1)
      .paddingInner(1)
      .paddingOuter(0)
      .paddingTop(d => d.depth === 1 ? 2 : 1) // extra padding between categories
      .tile(d3.treemapBinary)(root);

    // Draw category blocs (depth=1 nodes) with a subtle border
    svg.selectAll(null)
      .data(root.descendants().filter(d => d.depth === 1))
      .enter().append('rect')
        .attr('x',      d => x + d.x0)
        .attr('y',      d => y + d.y0)
        .attr('width',  d => Math.max(0, d.x1 - d.x0))
        .attr('height', d => Math.max(0, d.y1 - d.y0))
        .attr('fill',   d => d.data.color)
        .attr('opacity', 0.15)
        .attr('stroke', d => d.data.color)
        .attr('stroke-width', 2)
        .attr('rx', 3)
        .attr('pointer-events', 'none');

    // Draw topic leaves
    svg.selectAll(null)
      .data(root.leaves())
      .enter().append('rect')
        .attr('class', 'topic-rect')
        .attr('x',      d => x + d.x0)
        .attr('y',      d => y + d.y0)
        .attr('width',  d => Math.max(0, d.x1 - d.x0))
        .attr('height', d => Math.max(0, d.y1 - d.y0))
        .attr('fill',   d => d.data.color)
        .attr('stroke', d => anxietyColor(parseFloat(d.data.anxiety)))
        .attr('rx', 2)
        .on('mousemove', function(event, d) {
          tip.style.display = 'block';
          tip.style.left    = (event.clientX + 14) + 'px';
          tip.style.top     = (event.clientY - 10) + 'px';
          tip.innerHTML = `<strong>${d.data.name}</strong><br>
            ${d.data.category} · ${d.data.type}<br>
            Anxiety: ${parseFloat(d.data.anxiety).toFixed(1)} · ${d.data.count} article${d.data.count>1?'s':''}`;
        })
        .on('mouseleave', () => tip.style.display = 'none')
        .on('click', (e, d) => {
          window.location.href = `index.php?category=${encodeURIComponent(d.data.category)}`;
        });

    // Label if cell is large enough
    if (w > 60 && h > 30) {
      svg.append('text')
        .attr('x', x + 5).attr('y', y + 14)
        .attr('fill', 'rgba(255,255,255,0.5)')
        .attr('font-size', 9)
        .attr('font-family', '-apple-system, sans-serif')
        .attr('font-weight', '700')
        .text(topics.length + ' topic' + (topics.length>1?'s':''));
    }
  });
});

// Draw grid lines between cells
bands.forEach((b, i) => {
  if (i === 0) return;
  svg.append('line').attr('class','band-line')
    .attr('x1',0).attr('y1',ry[i]).attr('x2',W).attr('y2',ry[i]);
});
types.forEach((t, i) => {
  if (i === 0) return;
  svg.append('line').attr('class','type-line')
    .attr('x1',cx[i]).attr('y1',0).attr('x2',cx[i]).attr('y2',H);
});
</script>
</body>
</html>
