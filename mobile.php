<?php
require_once __DIR__ . '/functions.php';

$cat_colors = [
    'Politics'      => '#3b82f6','Geopolitics'   => '#8b5cf6',
    'Economy'       => '#10b981','Technology'    => '#06b6d4',
    'Science'       => '#0ea5e9','Health'        => '#f43f5e',
    'Society'       => '#f59e0b','Crime'         => '#991b1b',
    'Environment'   => '#16a34a','Sports'        => '#ea580c',
    'Entertainment' => '#ec4899','Travel'        => '#7c3aed',
    'Food'          => '#ca8a04',
];
$anx_palette = ['#16a34a','#4ade80','#a3e635','#facc15','#fb923c','#f97316','#ef4444','#dc2626','#b91c1c','#7f1d1d'];

$topics = db()->query("
    SELECT t.id, t.title, t.content_type, t.category, t.anxiety_avg,
           COUNT(ato.article_id) as article_count
    FROM topics t
    LEFT JOIN article_topics ato ON ato.topic_id = t.id
    WHERE t.category IS NOT NULL
    GROUP BY t.id HAVING article_count > 0
")->fetchAll();

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
        'anx_color'=> $anx_palette[min(9,max(0,(int)$t['anxiety_avg']))],
    ];
}

// Build links: 3 per node, same category
$by_cat = [];
foreach ($nodes as $n) $by_cat[$n['category']][] = $n['id'];
$links = [];
foreach ($nodes as $n) {
    $peers = array_values(array_filter($by_cat[$n['category']] ?? [], fn($id) => $id !== $n['id']));
    shuffle($peers);
    foreach (array_slice($peers, 0, 3) as $t) {
        $key = min($n['id'],$t).'-'.max($n['id'],$t);
        $links[$key] = [$n['id'], $t];
    }
}
$links = array_values($links);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
<title>Moodwire Globe</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { background:#0f172a; overflow:hidden; font-family:-apple-system,sans-serif; touch-action:none; }
  canvas { display:block; }

  #info {
    position:fixed; bottom:0; left:0; right:0;
    background:rgba(15,23,42,0.95); color:white;
    padding:16px 20px 32px; transform:translateY(100%);
    transition:transform 0.3s ease; border-radius:20px 20px 0 0;
    backdrop-filter:blur(10px);
  }
  #info.open { transform:translateY(0); }
  #info h2 { font-size:15px; margin-bottom:6px; line-height:1.4; }
  #info .meta { font-size:12px; color:#94a3b8; display:flex; gap:12px; flex-wrap:wrap; }
  #info .badge {
    display:inline-block; padding:2px 8px; border-radius:12px;
    font-size:11px; font-weight:700; color:white;
  }
  #info .close {
    position:absolute; top:12px; right:16px; background:none; border:none;
    color:#64748b; font-size:22px; cursor:pointer;
  }

  #nav {
    position:fixed; top:0; left:0; right:0;
    display:flex; justify-content:space-between; align-items:center;
    padding:12px 16px; background:rgba(15,23,42,0.8); backdrop-filter:blur(8px);
  }
  #nav a { color:#94a3b8; text-decoration:none; font-size:13px; }
  #nav .logo { color:white; font-weight:800; font-size:16px; }

  #hint {
    position:fixed; bottom:24px; left:50%; transform:translateX(-50%);
    color:#475569; font-size:11px; text-align:center;
    transition:opacity 1s; pointer-events:none;
  }
</style>
</head>
<body>
<div id="nav">
  <a href="index.php" class="logo">Moodwire</a>
  <a href="map.php">← Desktop map</a>
</div>

<div id="info">
  <button class="close" onclick="closeInfo()">×</button>
  <h2 id="info-title"></h2>
  <div class="meta">
    <span id="info-cat" class="badge"></span>
    <span id="info-type"></span>
    <span id="info-anxiety"></span>
    <span id="info-count"></span>
  </div>
</div>

<div id="hint">Drag to rotate · Tap a dot for details</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
<script>
const nodes = <?= json_encode(array_values($nodes)) ?>;
const links = <?= json_encode($links) ?>;

// ── Scene setup ──────────────────────────────────────────────────────────────
const W = window.innerWidth, H = window.innerHeight;
const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
renderer.setSize(W, H);
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
document.body.appendChild(renderer.domElement);

const scene  = new THREE.Scene();
const camera = new THREE.PerspectiveCamera(55, W/H, 0.1, 100);
camera.position.set(0, 0, 14);

// Lights
scene.add(new THREE.AmbientLight(0xffffff, 0.5));
const dir = new THREE.DirectionalLight(0xffffff, 0.8);
dir.position.set(5, 10, 5);
scene.add(dir);

// Controls
const controls = new THREE.OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.dampingFactor = 0.08;
controls.enableZoom = true;
controls.minDistance = 8;
controls.maxDistance = 22;
controls.autoRotate = true;
controls.autoRotateSpeed = 0.4;

// ── Globe radius ─────────────────────────────────────────────────────────────
const R = 5;

// ── Coordinate mapping ────────────────────────────────────────────────────────
// Y (vertical): anxiety — high=top (phi≈0), low=bottom (phi≈π)
// X (horizontal): type zone × category spread within zone
const typeZone = { educative:[-Math.PI, -Math.PI/3], informative:[-Math.PI/3, Math.PI/3], entertainment:[Math.PI/3, Math.PI] };
const catsByType = { educative:[], informative:[], entertainment:[] };
nodes.forEach(d => { if (!catsByType[d.type]?.includes(d.category)) catsByType[d.type]?.push(d.category); });

function nodeTheta(d) {
  const zone = typeZone[d.type] ?? [-Math.PI, Math.PI];
  const cats = catsByType[d.type] ?? [];
  const ci   = cats.indexOf(d.category);
  const t    = cats.length > 1 ? (ci + 0.5) / cats.length : 0.5;
  return zone[0] + t * (zone[1] - zone[0]);
}
function nodePhi(d) {
  // phi: 0=north, π=south — high anxiety → north
  const margin = 0.25;
  return margin + (1 - d.anxiety / 10) * (Math.PI - 2 * margin);
}
function spherePos(phi, theta) {
  return new THREE.Vector3(
    R * Math.sin(phi) * Math.cos(theta),
    R * Math.cos(phi),
    R * Math.sin(phi) * Math.sin(theta)
  );
}

// ── Size scale ───────────────────────────────────────────────────────────────
const maxCount = Math.max(...nodes.map(d => d.count));
const dotR = d => 0.12 + (d.count / maxCount) * 0.42;

// ── Place dots ───────────────────────────────────────────────────────────────
const meshes = [];
const positions = [];

nodes.forEach(d => {
  const phi   = nodePhi(d);
  const theta = nodeTheta(d);
  const pos   = spherePos(phi, theta);
  positions.push(pos);

  const geo  = new THREE.SphereGeometry(dotR(d), 16, 16);
  const mat  = new THREE.MeshPhongMaterial({
    color:    parseInt(d.color.replace('#',''), 16),
    emissive: parseInt(d.anx_color.replace('#',''), 16),
    emissiveIntensity: 0.3,
    shininess: 60,
  });
  const mesh = new THREE.Mesh(geo, mat);
  mesh.position.copy(pos);
  mesh.userData = d;
  scene.add(mesh);
  meshes.push(mesh);
});

// ── Draw links ───────────────────────────────────────────────────────────────
links.forEach(([si, ti]) => {
  const p1 = positions[si], p2 = positions[ti];
  if (!p1 || !p2) return;
  // Arc along sphere surface using intermediate points
  const pts = [];
  for (let t = 0; t <= 1; t += 0.1) {
    const v = new THREE.Vector3().lerpVectors(p1, p2, t).normalize().multiplyScalar(R * 1.01);
    pts.push(v);
  }
  const geo  = new THREE.BufferGeometry().setFromPoints(pts);
  const mat  = new THREE.LineBasicMaterial({
    color: parseInt(nodes[si].color.replace('#',''), 16),
    opacity: 0.25, transparent: true
  });
  scene.add(new THREE.Line(geo, mat));
});

// ── Raycasting for tap ───────────────────────────────────────────────────────
const raycaster = new THREE.Raycaster();
const pointer   = new THREE.Vector2();
let   lastTouch = null;

function onTap(cx, cy) {
  pointer.x = (cx / W) * 2 - 1;
  pointer.y = -(cy / H) * 2 + 1;
  raycaster.setFromCamera(pointer, camera);
  const hits = raycaster.intersectObjects(meshes);
  if (hits.length) showInfo(hits[0].object.userData);
}

renderer.domElement.addEventListener('click', e => onTap(e.clientX, e.clientY));
renderer.domElement.addEventListener('touchend', e => {
  const t = e.changedTouches[0];
  if (lastTouch && Math.abs(t.clientX - lastTouch.x) < 8 && Math.abs(t.clientY - lastTouch.y) < 8) {
    onTap(t.clientX, t.clientY);
  }
}, { passive: true });
renderer.domElement.addEventListener('touchstart', e => {
  lastTouch = { x: e.touches[0].clientX, y: e.touches[0].clientY };
  controls.autoRotate = false;
}, { passive: true });

// ── Info panel ───────────────────────────────────────────────────────────────
function showInfo(d) {
  document.getElementById('info-title').textContent = d.title;
  const badge = document.getElementById('info-cat');
  badge.textContent = d.category;
  badge.style.background = d.color;
  document.getElementById('info-type').textContent = d.type;
  document.getElementById('info-anxiety').textContent = 'Anxiety ' + d.anxiety.toFixed(1);
  document.getElementById('info-count').textContent = d.count + ' article' + (d.count>1?'s':'');
  document.getElementById('info').classList.add('open');
}
function closeInfo() {
  document.getElementById('info').classList.remove('open');
  controls.autoRotate = true;
}

// Hide hint after 4s
setTimeout(() => document.getElementById('hint').style.opacity = 0, 4000);

// ── Resize ───────────────────────────────────────────────────────────────────
window.addEventListener('resize', () => {
  camera.aspect = window.innerWidth / window.innerHeight;
  camera.updateProjectionMatrix();
  renderer.setSize(window.innerWidth, window.innerHeight);
});

// ── Render loop ──────────────────────────────────────────────────────────────
(function animate() {
  requestAnimationFrame(animate);
  controls.update();
  renderer.render(scene, camera);
})();
</script>
</body>
</html>
