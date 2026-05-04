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

// Build links: 3 per node to closest (by category) — sort by anxiety proximity for meaningful links
$by_cat = [];
foreach ($nodes as $n) $by_cat[$n['category']][] = $n;
$links = [];
foreach ($nodes as $n) {
    $peers = array_filter($by_cat[$n['category']] ?? [], fn($p) => $p['id'] !== $n['id']);
    // Sort peers by anxiety proximity for more meaningful connections
    usort($peers, fn($a, $b) => abs($a['anxiety'] - $n['anxiety']) <=> abs($b['anxiety'] - $n['anxiety']));
    foreach (array_slice(array_values($peers), 0, 3) as $t) {
        $key = min($n['id'],$t['id']).'-'.max($n['id'],$t['id']);
        $links[$key] = [$n['id'], $t['id']];
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
  body { background:#050a18; overflow:hidden; font-family:-apple-system,sans-serif; touch-action:none; }
  canvas { display:block; }
  #nav {
    position:fixed; top:0; left:0; right:0; z-index:10;
    display:flex; justify-content:space-between; align-items:center;
    padding:12px 16px; background:rgba(5,10,24,0.75); backdrop-filter:blur(8px);
  }
  #nav a { color:#94a3b8; text-decoration:none; font-size:13px; }
  #nav .logo { color:white; font-weight:800; font-size:16px; }
  #info {
    position:fixed; bottom:0; left:0; right:0; z-index:10;
    background:rgba(15,23,42,0.96); color:white;
    padding:16px 20px 36px; transform:translateY(100%);
    transition:transform 0.3s ease; border-radius:20px 20px 0 0;
    backdrop-filter:blur(10px);
  }
  #info.open { transform:translateY(0); }
  #info h2 { font-size:15px; margin-bottom:8px; line-height:1.4; }
  #info .meta { font-size:12px; color:#94a3b8; display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
  #info .badge { display:inline-block; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:700; color:white; }
  #info .close { position:absolute; top:12px; right:16px; background:none; border:none; color:#64748b; font-size:22px; cursor:pointer; }
  #hint { position:fixed; bottom:20px; left:50%; transform:translateX(-50%); color:#334155; font-size:11px; pointer-events:none; transition:opacity 1s; }
</style>
</head>
<body>
<div id="nav">
  <a href="index.php" class="logo">Moodwire</a>
  <a href="map.php">← Map</a>
</div>
<div id="info">
  <button class="close" onclick="closeInfo()">×</button>
  <h2 id="info-title"></h2>
  <div class="meta">
    <span id="info-cat" class="badge"></span>
    <span id="info-type"></span>
    <span id="info-anx"></span>
    <span id="info-count"></span>
  </div>
</div>
<div id="hint">Drag to rotate · Tap a dot</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
<script>
const nodes = <?= json_encode(array_values($nodes)) ?>;
const links = <?= json_encode($links) ?>;

// ── Palette (same as map.php) ─────────────────────────────────────────────────
const ANX_PALETTE = ['#16a34a','#4ade80','#a3e635','#facc15','#fb923c','#f97316','#ef4444','#dc2626','#b91c1c','#7f1d1d'];
const anxColor = a => parseInt(ANX_PALETTE[Math.min(9,Math.max(0,Math.floor(a)))].replace('#',''),16);
const hexInt   = h => parseInt(h.replace('#',''),16);

// ── Size scale (same as map.php) ──────────────────────────────────────────────
const maxCount = Math.max(...nodes.map(d => d.count));
const dotR = d => 0.10 + (d.count / maxCount) * 0.45;

// ── Sphere positioning (same axes as map.php) ─────────────────────────────────
const R = 10;
const typeZone = { educative:[-Math.PI,-Math.PI/3], informative:[-Math.PI/3,Math.PI/3], entertainment:[Math.PI/3,Math.PI] };
const catsByType = { educative:[], informative:[], entertainment:[] };
nodes.forEach(d => { if (catsByType[d.type] && !catsByType[d.type].includes(d.category)) catsByType[d.type].push(d.category); });

function nodeTheta(d) {
  const zone = typeZone[d.type] ?? [-Math.PI,Math.PI];
  const cats = catsByType[d.type] ?? [];
  const ci   = cats.indexOf(d.category);
  const t    = cats.length > 1 ? (ci + 0.5) / cats.length : 0.5;
  return zone[0] + t * (zone[1] - zone[0]);
}
function nodePhi(d) {
  const margin = 0.2;
  return margin + (1 - d.anxiety / 10) * (Math.PI - 2 * margin);
}
function toXYZ(phi, theta) {
  return new THREE.Vector3(
    R * Math.sin(phi) * Math.cos(theta),
    R * Math.cos(phi),
    R * Math.sin(phi) * Math.sin(theta)
  );
}

// ── Scene ─────────────────────────────────────────────────────────────────────
const W = window.innerWidth, H = window.innerHeight;
const renderer = new THREE.WebGLRenderer({ antialias:true });
renderer.setSize(W, H);
renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
renderer.setClearColor(0x050a18);
document.body.appendChild(renderer.domElement);

const scene  = new THREE.Scene();
const camera = new THREE.PerspectiveCamera(55, W/H, 0.1, 300);
camera.position.set(0, 4, 20);
camera.lookAt(0, -8, 0);

// Lights
scene.add(new THREE.AmbientLight(0x8899cc, 0.7));
const sun = new THREE.DirectionalLight(0xffffff, 0.9);
sun.position.set(8, 15, 8);
scene.add(sun);

// Stars
const sg = new THREE.BufferGeometry();
const sp = [];
for (let i=0;i<2500;i++) {
  const a=Math.random()*Math.PI*2, b=Math.random()*Math.PI, r=80+Math.random()*40;
  sp.push(r*Math.sin(b)*Math.cos(a), r*Math.cos(b), r*Math.sin(b)*Math.sin(a));
}
sg.setAttribute('position', new THREE.Float32BufferAttribute(sp,3));
scene.add(new THREE.Points(sg, new THREE.PointsMaterial({color:0xffffff,size:0.2})));

// Planet group — push down for fly-over effect
const planet = new THREE.Group();
planet.position.y = -9;
scene.add(planet);

// Dark planet base
planet.add(new THREE.Mesh(
  new THREE.SphereGeometry(R, 64, 64),
  new THREE.MeshPhongMaterial({ color:0x0d1b2a, shininess:8 })
));
// Atmosphere
planet.add(new THREE.Mesh(
  new THREE.SphereGeometry(R*1.022, 64, 64),
  new THREE.MeshPhongMaterial({ color:0x1a3a6a, transparent:true, opacity:0.15, side:THREE.BackSide })
));

// Controls
const controls = new THREE.OrbitControls(camera, renderer.domElement);
controls.target.set(0, -9, 0);
controls.enableDamping  = true;
controls.dampingFactor  = 0.06;
controls.enableZoom     = true;
controls.minDistance    = 12;
controls.maxDistance    = 28;
controls.autoRotate     = true;
controls.autoRotateSpeed = 0.35;
controls.update();

// ── Place towers ──────────────────────────────────────────────────────────────
const meshes = [];
const positions = nodes.map(d => toXYZ(nodePhi(d), nodeTheta(d)));
const UP = new THREE.Vector3(0, 1, 0);
const minH = 0.15, maxH = 2.8;
const towerH = d => minH + (d.count / maxCount) * (maxH - minH);
const towerR = 0.09;

nodes.forEach((d, i) => {
  const surfacePos = positions[i];
  const normal     = surfacePos.clone().normalize();
  const h          = towerH(d);
  const quat       = new THREE.Quaternion().setFromUnitVectors(UP, normal);

  // Tower body (category color, slight taper)
  const geo = new THREE.CylinderGeometry(towerR * 0.7, towerR, h, 7);
  const mat = new THREE.MeshPhongMaterial({ color: hexInt(d.color), shininess: 80 });
  const mesh = new THREE.Mesh(geo, mat);
  mesh.position.copy(normal.clone().multiplyScalar(R + h / 2));
  mesh.quaternion.copy(quat);
  mesh.userData = d;
  planet.add(mesh);
  meshes.push(mesh);

  // Top cap (anxiety color)
  const capGeo = new THREE.SphereGeometry(towerR * 0.85, 10, 10);
  const capMat = new THREE.MeshPhongMaterial({ color: anxColor(d.anxiety), shininess: 120, emissive: anxColor(d.anxiety), emissiveIntensity: 0.3 });
  const cap = new THREE.Mesh(capGeo, capMat);
  cap.position.copy(normal.clone().multiplyScalar(R + h));
  cap.userData = d;
  planet.add(cap);
  meshes.push(cap);
});

// ── Links — arc between tower tops ───────────────────────────────────────────
links.forEach(([si, ti]) => {
  const n1 = positions[si].clone().normalize();
  const n2 = positions[ti].clone().normalize();
  const h1 = towerH(nodes[si]);
  const h2 = towerH(nodes[ti]);
  const top1 = n1.clone().multiplyScalar(R + h1);
  const top2 = n2.clone().multiplyScalar(R + h2);

  // Arc: lerp between tops, push mid-points above sphere surface
  const pts = [];
  const steps = 16;
  for (let t = 0; t <= 1; t += 1/steps) {
    const p = new THREE.Vector3().lerpVectors(top1, top2, t);
    const minR = R + Math.max(h1, h2) * 0.5;
    if (p.length() < minR) p.normalize().multiplyScalar(minR);
    pts.push(p);
  }
  pts.push(top2.clone());

  const geo = new THREE.BufferGeometry().setFromPoints(pts);
  const mat = new THREE.LineBasicMaterial({ color: hexInt(nodes[si].color), opacity: 0.4, transparent: true });
  planet.add(new THREE.Line(geo, mat));
});

// ── Raycasting ────────────────────────────────────────────────────────────────
const ray = new THREE.Raycaster();
const ptr = new THREE.Vector2();
let lastTouch = null;

function onTap(cx, cy) {
  ptr.x = (cx/W)*2-1; ptr.y = -(cy/H)*2+1;
  ray.setFromCamera(ptr, camera);
  const hits = ray.intersectObjects(meshes, false);
  if (hits.length) { showInfo(hits[0].object.userData); controls.autoRotate = false; }
}
renderer.domElement.addEventListener('click',    e => onTap(e.clientX, e.clientY));
renderer.domElement.addEventListener('touchstart', e => { lastTouch={x:e.touches[0].clientX,y:e.touches[0].clientY}; controls.autoRotate=false; }, {passive:true});
renderer.domElement.addEventListener('touchend',   e => {
  const t=e.changedTouches[0];
  if (lastTouch && Math.abs(t.clientX-lastTouch.x)<8 && Math.abs(t.clientY-lastTouch.y)<8) onTap(t.clientX,t.clientY);
}, {passive:true});

function showInfo(d) {
  document.getElementById('info-title').textContent = d.title;
  const b = document.getElementById('info-cat');
  b.textContent = d.category; b.style.background = d.color;
  document.getElementById('info-type').textContent = d.type;
  document.getElementById('info-anx').textContent  = 'Anxiety ' + d.anxiety.toFixed(1);
  document.getElementById('info-count').textContent= d.count + ' article' + (d.count>1?'s':'');
  document.getElementById('info').classList.add('open');
}
function closeInfo() { document.getElementById('info').classList.remove('open'); controls.autoRotate=true; }

setTimeout(() => document.getElementById('hint').style.opacity=0, 4000);

window.addEventListener('resize', () => {
  camera.aspect = innerWidth/innerHeight;
  camera.updateProjectionMatrix();
  renderer.setSize(innerWidth, innerHeight);
});

(function animate() {
  requestAnimationFrame(animate);
  controls.update();
  renderer.render(scene, camera);
})();
</script>
</body>
</html>
