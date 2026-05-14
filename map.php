<?php
require_once __DIR__ . '/functions.php';

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

$topics = db()->query("
    SELECT t.id, t.title, t.content_type, t.category, t.anxiety_avg,
           COUNT(ato.article_id) as article_count
    FROM topics t
    LEFT JOIN article_topics ato ON ato.topic_id = t.id
    WHERE t.category IS NOT NULL
    GROUP BY t.id
    HAVING article_count > 0
")->fetchAll();

// Build nodes
$nodes = [];
foreach ($topics as $i => $t) {
    $nodes[] = [
        'id'       => $i,
        'title'    => $t['title'],
        'category' => $t['category'],
        'type'     => $t['content_type'],
        'anxiety'  => (float)$t['anxiety_avg'],
        'count'    => (int)$t['article_count'],
        'color'    => $cat_colors[$t['category']] ?? '#94a3b8',
    ];
}

// Build edges: each node connects to 3 other nodes of same category
$links = [];
$by_cat = [];
foreach ($nodes as $n) {
    $by_cat[$n['category']][] = $n['id'];
}
foreach ($nodes as $n) {
    $peers = array_values(array_filter($by_cat[$n['category']], fn($id) => $id !== $n['id']));
    shuffle($peers);
    $targets = array_slice($peers, 0, 3);
    foreach ($targets as $t) {
        // Avoid duplicate edges
        $key = min($n['id'], $t) . '-' . max($n['id'], $t);
        $links[$key] = ['source' => $n['id'], 'target' => $t];
    }
}
$links = array_values($links);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Moodwire — Map</title>
<link rel="stylesheet" href="style.css">
<style>
  #map-svg { width: 100%; display: block; }
  .node { cursor: pointer; }
  .node circle { transition: opacity 0.15s; }
  .node circle:hover { opacity: 0.75; }
  .link { stroke-opacity: 0.25; }
  #tooltip {
    position: fixed; background: #1e293b; color: #f1f5f9;
    padding: 8px 12px; border-radius: 8px; font-size: 12px;
    pointer-events: none; display: none; z-index: 999;
    max-width: 240px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); line-height: 1.5;
  }
  .legend-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
  .legend-item { display: flex; align-items: center; gap: 5px; font-size: 11px; }
  .legend-dot  { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
</style>
</head>
<body>
<div class="container">
  <nav class="nav">
    <a href="index.php" class="nav-logo">Moodwire</a>
    <a href="index.php">Topics</a>
    <a href="map.php" class="active">Map</a>
    <a href="mobile.php">Globe</a>
    <a href="feeds.php">Feeds</a>
    <a href="admin.php">Pipeline</a>
  </nav>

  <h1>Topic Map</h1>

  <div class="legend-grid">
    <?php foreach ($cat_colors as $cat => $col): ?>
      <div class="legend-item">
        <div class="legend-dot" style="background:<?= $col ?>"></div>
        <?= $cat ?>
      </div>
    <?php endforeach; ?>
    <div class="legend-item" style="margin-left:12px;color:#64748b">
      · Dot size = article count &nbsp;· Border color = anxiety (green→red) &nbsp;· Lines = same category
    </div>
  </div>

  <svg id="map-svg"></svg>
  <div id="tooltip"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js"></script>
<script>
const nodes = <?= json_encode(array_values($nodes)) ?>;
const links = <?= json_encode($links) ?>;

const W = document.getElementById('map-svg').parentElement.clientWidth;
const H = Math.round(W * 0.72);
document.getElementById('map-svg').setAttribute('viewBox', `0 0 ${W} ${H}`);
document.getElementById('map-svg').setAttribute('height', H);

const svg = d3.select('#map-svg');
const tip = document.getElementById('tooltip');

// Radius scale: sqrt so area ∝ count
const rScale = d3.scaleSqrt()
  .domain([1, d3.max(nodes, d => d.count)])
  .range([8, 42]);

// Anxiety border color
const anxPalette = ['#16a34a','#4ade80','#a3e635','#facc15','#fb923c','#f97316','#ef4444','#dc2626','#b91c1c','#7f1d1d'];
const anxColor = a => anxPalette[Math.min(9, Math.max(0, Math.floor(a)))];

// Y target: high anxiety → top, low anxiety → bottom
const pad = 40;
const yTarget = d => pad + (1 - d.anxiety / 10) * (H - 2 * pad);

// X axis: 3 type zones
const typeZone = { educative: [0, W/3], informative: [W/3, 2*W/3], entertainment: [2*W/3, W] };

// Spread categories within their type zone
const categories = [...new Set(nodes.map(d => d.category))];
const catsByType = { educative: [], informative: [], entertainment: [] };
nodes.forEach(d => { if (!catsByType[d.type].includes(d.category)) catsByType[d.type].push(d.category); });

const catX = {};
['educative','informative','entertainment'].forEach(type => {
  const [zLeft, zRight] = typeZone[type];
  const cats = catsByType[type];
  cats.forEach((cat, i) => {
    catX[cat] = zLeft + pad/2 + (i + 0.5) / cats.length * (zRight - zLeft - pad);
  });
});
const xTarget = d => catX[d.category] ?? W / 2;

// Force simulation
const sim = d3.forceSimulation(nodes)
  .force('link', d3.forceLink(links).id(d => d.id).distance(d => {
    const r1 = rScale(nodes[d.source.index ?? d.source]?.count ?? 1);
    const r2 = rScale(nodes[d.target.index ?? d.target]?.count ?? 1);
    return r1 + r2 + 8;
  }).strength(0.15))
  .force('charge', d3.forceManyBody().strength(-60))
  .force('y', d3.forceY(yTarget).strength(0.55))
  .force('x', d3.forceX(xTarget).strength(0.2))
  .force('collision', d3.forceCollide().radius(d => rScale(d.count) + 3))
  .force('bounds', () => {
    nodes.forEach(d => {
      const r = rScale(d.count);
      d.x = Math.max(r, Math.min(W - r, d.x));
      d.y = Math.max(r, Math.min(H - r, d.y));
    });
  });

// Type zone dividers and labels
[{x: W/3, label:'◄ Educative   Informative ►'}, {x: 2*W/3, label:'◄ Informative   Entertainment ►'}].forEach(z => {
  svg.append('line').attr('x1', z.x).attr('y1', 0).attr('x2', z.x).attr('y2', H)
    .attr('stroke', '#e2e8f0').attr('stroke-width', 1.5).attr('stroke-dasharray', '6,4');
});
[{x: W/6, label:'Educative'}, {x: W/2, label:'Informative'}, {x: W*5/6, label:'Entertainment'}].forEach(z => {
  svg.append('text').attr('x', z.x).attr('y', 16)
    .attr('text-anchor', 'middle').attr('font-size', 11).attr('font-weight', '700')
    .attr('fill', '#cbd5e1').attr('font-family', '-apple-system, sans-serif')
    .attr('text-transform', 'uppercase').attr('letter-spacing', 1)
    .text(z.label.toUpperCase());
});

// Y-axis anxiety labels
[10,8,6,4,2,0].forEach(val => {
  const y = pad + (1 - val/10) * (H - 2*pad);
  svg.append('line').attr('x1', 0).attr('y1', y).attr('x2', W).attr('y2', y)
    .attr('stroke', '#f1f5f9').attr('stroke-width', 1).attr('stroke-dasharray', '4,4');
  svg.append('text').attr('x', 4).attr('y', y - 4)
    .attr('font-size', 10).attr('fill', '#94a3b8')
    .attr('font-family', '-apple-system, sans-serif')
    .text('anxiety ' + val);
});

// Draw links
const link = svg.append('g')
  .selectAll('line')
  .data(links)
  .enter().append('line')
    .attr('class', 'link')
    .attr('stroke', d => nodes[typeof d.source === 'object' ? d.source.id : d.source]?.color ?? '#94a3b8')
    .attr('stroke-width', 1.2);

// Draw nodes
const node = svg.append('g')
  .selectAll('g')
  .data(nodes)
  .enter().append('g')
    .attr('class', 'node')
    .call(d3.drag()
      .on('start', (e, d) => { if (!e.active) sim.alphaTarget(0.3).restart(); d.fx = d.x; d.fy = d.y; })
      .on('drag',  (e, d) => { d.fx = e.x; d.fy = e.y; })
      .on('end',   (e, d) => { if (!e.active) sim.alphaTarget(0); d.fx = null; d.fy = null; }));

node.append('circle')
  .attr('r',           d => rScale(d.count))
  .attr('fill',        d => d.color)
  .attr('stroke',      d => anxColor(d.anxiety))
  .attr('stroke-width',d => Math.max(2, rScale(d.count) * 0.18))
  .on('mousemove', function(event, d) {
    tip.style.display = 'block';
    tip.style.left    = (event.clientX + 14) + 'px';
    tip.style.top     = (event.clientY - 10) + 'px';
    tip.innerHTML = `<strong>${d.title}</strong><br>
      ${d.category} · ${d.type}<br>
      Anxiety: ${d.anxiety.toFixed(1)} · ${d.count} article${d.count>1?'s':''}`;
  })
  .on('mouseleave', () => tip.style.display = 'none')
  .on('click', (e, d) => window.location.href = `index.php?category=${encodeURIComponent(d.category)}`);

// Tick
sim.on('tick', () => {
  link
    .attr('x1', d => d.source.x)
    .attr('y1', d => d.source.y)
    .attr('x2', d => d.target.x)
    .attr('y2', d => d.target.y);
  node.attr('transform', d => `translate(${d.x},${d.y})`);
});
</script>
</body>
</html>
