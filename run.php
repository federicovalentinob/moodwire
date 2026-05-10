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
        'all'        => 'pipeline.php',
        'fetch'      => 'fetch.php',
        'normalize'  => 'normalize.php',
        'embed'      => 'embed.php',
        'cluster'    => 'cluster.php',
        'label'      => 'label.php',
    ];

    if (isset($scripts[$step])) {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $url   = $proto . '://' . $_SERVER['HTTP_HOST'] . '/' . $scripts[$step];
        $ch    = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS     => 200,   // hang up almost immediately — server keeps working
            CURLOPT_NOSIGNAL       => 1,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        curl_exec($ch);
        $message = "Step '{$step}' started in background. Refresh to see progress in logs.";
        log_action($step, 'success', 'Started via run.php');
    }
    header('Location: run.php?msg=' . urlencode($message));
    exit;
}

if (isset($_GET['msg'])) $message = $_GET['msg'];

// ── Stats ─────────────────────────────────────────────────────────────────────
$stats = [
    'feeds'       => db()->query('SELECT COUNT(*) FROM feeds WHERE active=1')->fetchColumn(),
    'articles'    => db()->query('SELECT COUNT(*) FROM articles')->fetchColumn(),
    'unprocessed' => count_unprocessed_articles(),
    'topics'      => db()->query('SELECT COUNT(*) FROM topics')->fetchColumn(),
];

$logs = db()->query('SELECT * FROM logs ORDER BY created_at DESC LIMIT 30')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Moodwire — Pipeline</title>
<link rel="stylesheet" href="style.css">
<?php if ($message): ?>
<meta http-equiv="refresh" content="5;url=run.php">
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
    <div class="stat"><span><?= $stats['feeds'] ?></span>Active Feeds</div>
    <div class="stat"><span><?= $stats['articles'] ?></span>Articles</div>
    <div class="stat"><span><?= $stats['unprocessed'] ?></span>Untagged</div>
    <div class="stat"><span><?= $stats['topics'] ?></span>Topics</div>
  </div>

  <div style="margin-bottom:24px">
    <form method="POST">
      <input type="hidden" name="step" value="all">
      <button class="btn" style="padding:12px 32px;font-size:15px">▶ Run Full Pipeline</button>
    </form>
  </div>

  <div class="pipeline">

    <div class="pipeline-step" style="flex-wrap:wrap;gap:12px">
      <div class="step-num">1</div>
      <div class="step-info">
        <h3>Fetch</h3>
        <p>Pull latest articles from all active RSS feeds</p>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
        <button id="fetch-btn" class="btn" onclick="runFetch(event)">Run</button>
        <div id="fetch-result" style="display:none;font-size:12px;font-weight:600;display:none;gap:10px;white-space:nowrap"></div>
      </div>
    </div>

    <div class="pipeline-step">
      <div class="step-num">2</div>
      <div class="step-info">
        <h3>Normalize</h3>
        <p>Clean and standardize article content</p>
      </div>
      <form method="POST">
        <input type="hidden" name="step" value="normalize">
        <button class="btn">Run</button>
      </form>
    </div>

    <div class="pipeline-step">
      <div class="step-num">3</div>
      <div class="step-info">
        <h3>Tag</h3>
        <p>Perplexity assigns tags + anxiety to each article (<?= $stats['unprocessed'] ?> untagged)</p>
      </div>
      <form method="POST">
        <input type="hidden" name="step" value="tag">
        <button class="btn" <?= $stats['unprocessed'] == 0 ? 'disabled' : '' ?>>
          <?= $stats['unprocessed'] > 0 ? 'Run' : 'Done ✓' ?>
        </button>
      </form>
    </div>

    <div class="pipeline-step">
      <div class="step-num">4</div>
      <div class="step-info">
        <h3>Synthesize</h3>
        <p>Cluster articles into up to 25 topics + generate bullet points</p>
      </div>
      <form method="POST">
        <input type="hidden" name="step" value="synthesize">
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
