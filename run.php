<?php

require_once __DIR__ . '/functions.php';

$step    = $_POST['step'] ?? '';
$message = '';

// ── Trigger a step via fire-and-forget HTTP self-call ───────────────────────
// Shared hosting (IONOS) silently drops exec("... &"), so we hit the matching
// step script over HTTP and hang up. The Apache worker keeps running thanks to
// ignore_user_abort(true) inside each step file.
if ($step) {
    $scripts = [
        'all'           => 'pipeline.php',
        'fetch'         => 'fetch.php',
        'fetch_images'  => 'fetch_images.php',
        'normalize'     => 'normalize.php',
        'embed'         => 'embed.php',
        'cluster'       => 'cluster.php',
        'label'         => 'label.php',
    ];

    if (isset($scripts[$step])) {
        $script_path = __DIR__ . '/' . $scripts[$step];
        if (PHP_SAPI === 'cli-server') {
            // Local dev: php -S is single-threaded, so an HTTP self-call would
            // deadlock. Spawn a real CLI php process instead and detach.
            exec("php " . escapeshellarg($script_path) . " >> /tmp/moodwire_run.log 2>&1 &");
        } else {
            // Production (Apache): fire-and-forget HTTP self-call. The Apache
            // worker keeps running thanks to ignore_user_abort() in the step file.
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $url   = $proto . '://' . $_SERVER['HTTP_HOST'] . '/' . $scripts[$step];
            $ch    = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT_MS     => 200,
                CURLOPT_NOSIGNAL       => 1,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            curl_exec($ch);
        }
        $message = "Step '{$step}' started in background. Refresh to see progress in logs.";
        log_action($step, 'success', 'Started via run.php');
    }
    header('Location: run.php?msg=' . urlencode($message));
    exit;
}

if (isset($_GET['msg'])) $message = $_GET['msg'];

// ── Stats ─────────────────────────────────────────────────────────────────────
$stats = [
    'feeds'           => db()->query('SELECT COUNT(*) FROM feeds WHERE active=1')->fetchColumn(),
    'articles'        => db()->query('SELECT COUNT(*) FROM articles')->fetchColumn(),
    'with_image'      => db()->query('SELECT COUNT(*) FROM articles WHERE image_path IS NOT NULL')->fetchColumn(),
    'normalized'      => db()->query('SELECT COUNT(*) FROM articles WHERE clean_content IS NOT NULL AND clean_content != ""')->fetchColumn(),
    'embedded'        => (int)db()->query('SELECT COUNT(*) FROM article_embeddings')->fetchColumn(),
    'clusters'        => (int)(db()->query("SHOW TABLES LIKE 'topic_clusters'")->fetchColumn() ? db()->query('SELECT COUNT(DISTINCT cluster_id) FROM topic_clusters')->fetchColumn() : 0),
    'topics'          => db()->query('SELECT COUNT(*) FROM topics')->fetchColumn(),
];

$logs = db()->query('SELECT * FROM logs ORDER BY id DESC LIMIT 30')->fetchAll();

// ── Detect the currently-active step (most recent log within 2 min) ──────────
$action_to_step = [
    'fetch'        => 1,
    'fetch_images' => 2,
    'normalize'    => 3,
    'embed'        => 4,
    'cluster'      => 5,
    'label'        => 6,
];
$active_step = 0;
$active_ago  = null;
if (!empty($logs)) {
    foreach ($logs as $l) {
        if (isset($action_to_step[$l['action']])) {
            $age = time() - strtotime($l['created_at']);
            if ($age <= 120) {
                $active_step = $action_to_step[$l['action']];
                $active_ago  = $age;
            }
            break;  // only consider the very latest log
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Moodwire — Pipeline</title>
<link rel="stylesheet" href="style.css">
<style>
  .pipeline-step.is-active {
    background: linear-gradient(0deg, #fff7e6, #fff7e6), #fff;
    border-color: #f59e0b;
    box-shadow: 0 0 0 2px #fde68a inset;
    animation: stepPulse 1.4s ease-in-out infinite;
  }
  .pipeline-step.is-active .step-num { background:#f59e0b; color:#fff; }
  .pipeline-step.is-active::after {
    content:"running…"; margin-left:auto; padding:2px 10px;
    font-size:11px; font-weight:800; letter-spacing:0.4px; text-transform:uppercase;
    background:#f59e0b; color:#fff; border-radius:4px;
  }
  @keyframes stepPulse {
    0%, 100% { box-shadow: 0 0 0 2px #fde68a inset; }
    50%      { box-shadow: 0 0 0 2px #f59e0b inset; }
  }
</style>
<?php if ($message || $active_step): ?>
<meta http-equiv="refresh" content="<?= $active_step ? 5 : 5 ?>;url=run.php">
<?php endif; ?>
</head>
<body>
<div class="container">
  <nav class="nav">
    <a href="index.php" class="nav-logo">Moodwire</a>
    <a href="map.php">Map</a>
    <a href="feeds.php">Feeds</a>
    <a href="run.php" class="active">Run Pipeline</a>
  </nav>

  <h1>Pipeline</h1>

  <?php if ($message): ?>
    <div class="message"><?= htmlspecialchars($message) ?> <small>(refreshing in 5s…)</small></div>
  <?php endif; ?>

  <div class="stats-bar">
    <div class="stat"><span><?= $stats['feeds'] ?></span>Feeds</div>
    <div class="stat"><span><?= $stats['articles'] ?></span>Articles</div>
    <div class="stat"><span><?= $stats['with_image'] ?></span>Images</div>
    <div class="stat"><span><?= $stats['normalized'] ?></span>Normalized</div>
    <div class="stat"><span><?= $stats['embedded'] ?></span>Embedded</div>
    <div class="stat"><span><?= $stats['clusters'] ?></span>Clusters</div>
    <div class="stat"><span><?= $stats['topics'] ?></span>Topics</div>
  </div>

  <div style="margin-bottom:24px">
    <form method="POST">
      <input type="hidden" name="step" value="all">
      <button class="btn" style="padding:12px 32px;font-size:15px">▶ Run Full Pipeline</button>
    </form>
  </div>

  <?php
    $unembedded = max(0, (int)$stats['articles'] - (int)$stats['embedded']);
    $no_image   = max(0, (int)$stats['articles'] - (int)$stats['with_image']);
    $no_clean   = max(0, (int)$stats['articles'] - (int)$stats['normalized']);
  ?>
  <div class="pipeline">
  <?php $_n = 0; ?>

    <div class="pipeline-step<?= $active_step === ++$_n ? ' is-active' : '' ?>" style="flex-wrap:wrap;gap:12px">
      <div class="step-num">1</div>
      <div class="step-info">
        <h3>Fetch</h3>
        <p>Pull latest articles from all active RSS feeds, purge anything &gt; 24h.</p>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
        <button id="fetch-btn" class="btn" onclick="runFetch(event)">Run</button>
        <div id="fetch-result" style="display:none;font-size:12px;font-weight:600;gap:10px;white-space:nowrap"></div>
      </div>
    </div>

    <div class="pipeline-step<?= $active_step === ++$_n ? ' is-active' : '' ?>">
      <div class="step-num">2</div>
      <div class="step-info">
        <h3>Images</h3>
        <p>Fetch OG cover images (<?= $stats['with_image'] ?>/<?= $stats['articles'] ?> articles have one).</p>
      </div>
      <form method="POST">
        <input type="hidden" name="step" value="fetch_images">
        <button class="btn" disabled title="Run as part of Full Pipeline">Run</button>
      </form>
    </div>

    <div class="pipeline-step<?= $active_step === ++$_n ? ' is-active' : '' ?>">
      <div class="step-num">3</div>
      <div class="step-info">
        <h3>Normalize</h3>
        <p>Strip HTML and clean article body text (<?= $stats['normalized'] ?>/<?= $stats['articles'] ?> normalized).</p>
      </div>
      <form method="POST">
        <input type="hidden" name="step" value="normalize">
        <button class="btn">Run</button>
      </form>
    </div>

    <div class="pipeline-step<?= $active_step === ++$_n ? ' is-active' : '' ?>">
      <div class="step-num">4</div>
      <div class="step-info">
        <h3>Embed</h3>
        <p>OpenAI <code>text-embedding-3-small</code> @ 384 dims — <?= $stats['embedded'] ?>/<?= $stats['articles'] ?> embedded (<?= $unembedded ?> pending).</p>
      </div>
      <form method="POST">
        <input type="hidden" name="step" value="embed">
        <button class="btn" <?= $unembedded == 0 ? 'disabled' : '' ?>>
          <?= $unembedded > 0 ? 'Run' : 'Up to date ✓' ?>
        </button>
      </form>
    </div>

    <div class="pipeline-step<?= $active_step === ++$_n ? ' is-active' : '' ?>">
      <div class="step-num">5</div>
      <div class="step-info">
        <h3>Cluster</h3>
        <p>Local threshold k-NN clustering on the embedding space (cos ≥ 0.70). <?= $stats['clusters'] ?> clusters from last run.</p>
      </div>
      <form method="POST">
        <input type="hidden" name="step" value="cluster">
        <button class="btn">Run</button>
      </form>
    </div>

    <div class="pipeline-step<?= $active_step === ++$_n ? ' is-active' : '' ?>">
      <div class="step-num">6</div>
      <div class="step-info">
        <h3>Label</h3>
        <p>One <code>gpt-4o-mini</code> call per cluster — title, bullets, anxiety, country (<?= $stats['topics'] ?> topics in DB).</p>
      </div>
      <form method="POST">
        <input type="hidden" name="step" value="label">
        <button class="btn">Run</button>
      </form>
    </div>

  </div>

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
    <h2>Recent Logs</h2>
    <a href="run.php" class="btn btn-sm btn-grey">↻ Refresh</a>
  </div>

  <table class="table">
    <thead><tr><th>Time</th><th>Action</th><th>Status</th><th>Message</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
      <tr>
        <td><?= $log['created_at'] ?></td>
        <td><?= htmlspecialchars($log['action']) ?></td>
        <td><span class="badge badge-<?= $log['status'] === 'success' ? 'green' : 'red' ?>">
            <?= $log['status'] ?></span></td>
        <td><?= htmlspecialchars($log['message']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

</div>

<script>
async function runFetch(e) {
  e.preventDefault();
  const btn = document.getElementById('fetch-btn');
  const result = document.getElementById('fetch-result');
  btn.disabled = true;
  btn.textContent = '⏳ Fetching…';
  result.style.display = 'none';
  try {
    const res  = await fetch('fetch.php');
    const data = await res.json();
    result.innerHTML =
      `<span style="color:#dc2626">− ${data.removed} removed</span>` +
      `<span style="color:#16a34a">+ ${data.total_new} new</span>` +
      `<span style="color:#6b7280">= ${data.total_articles} total</span>`;
    result.style.display = 'flex';
    btn.textContent = 'Done ✓';
    setTimeout(() => { btn.disabled = false; btn.textContent = 'Run'; location.reload(); }, 3000);
  } catch(err) {
    btn.disabled = false;
    btn.textContent = 'Run';
    result.innerHTML = '<span style="color:#dc2626">Error — check console</span>';
    result.style.display = 'flex';
  }
}
</script>
</body>
</html>
