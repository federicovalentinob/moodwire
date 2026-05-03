<?php

require_once __DIR__ . '/functions.php';

$step    = $_GET['step'] ?? '';
$results = [];

if ($step === 'fetch') {
    ob_start();
    include __DIR__ . '/fetch.php';
    $out = ob_get_clean();
    $data = json_decode($out, true);
    $results = ['step' => 'Fetch', 'detail' => $data];
} elseif ($step === 'normalize') {
    ob_start();
    include __DIR__ . '/normalize.php';
    $out = ob_get_clean();
    $data = json_decode($out, true);
    $results = ['step' => 'Normalize', 'detail' => $data];
} elseif ($step === 'tag') {
    ob_start();
    include __DIR__ . '/tag.php';
    $out = ob_get_clean();
    $data = json_decode($out, true);
    $results = ['step' => 'Tag', 'detail' => $data];
} elseif ($step === 'synthesize') {
    ob_start();
    include __DIR__ . '/synthesize.php';
    $out = ob_get_clean();
    $data = json_decode($out, true);
    $results = ['step' => 'Synthesize', 'detail' => $data];
}

// Stats
$stats = [
    'feeds'      => db()->query('SELECT COUNT(*) FROM feeds WHERE active=1')->fetchColumn(),
    'articles'   => db()->query('SELECT COUNT(*) FROM articles')->fetchColumn(),
    'unprocessed'=> db()->query('SELECT COUNT(*) FROM articles WHERE processed=0')->fetchColumn(),
    'topics'     => db()->query('SELECT COUNT(*) FROM topics')->fetchColumn(),
];

$logs = db()->query('SELECT * FROM logs ORDER BY created_at DESC LIMIT 20')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Moodwire — Pipeline</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
  <nav class="nav">
    <a href="index.php" class="nav-logo">Moodwire</a>
    <a href="feeds.php">Feeds</a>
    <a href="run.php" class="active">Run Pipeline</a>
  </nav>

  <h1>Pipeline</h1>

  <div class="stats-bar">
    <div class="stat"><span><?= $stats['feeds'] ?></span>Active Feeds</div>
    <div class="stat"><span><?= $stats['articles'] ?></span>Articles</div>
    <div class="stat"><span><?= $stats['unprocessed'] ?></span>Unprocessed</div>
    <div class="stat"><span><?= $stats['topics'] ?></span>Topics</div>
  </div>

  <?php if ($results): ?>
    <div class="card message">
      <strong><?= $results['step'] ?> complete.</strong>
      <pre><?= htmlspecialchars(json_encode($results['detail'], JSON_PRETTY_PRINT)) ?></pre>
    </div>
  <?php endif; ?>

  <div class="pipeline">
    <div class="pipeline-step">
      <div class="step-num">1</div>
      <div class="step-info">
        <h3>Fetch</h3>
        <p>Pull latest articles from all active RSS feeds</p>
      </div>
      <a href="?step=fetch" class="btn">Run</a>
    </div>
    <div class="pipeline-step">
      <div class="step-num">2</div>
      <div class="step-info">
        <h3>Normalize</h3>
        <p>Clean and standardize article content</p>
      </div>
      <a href="?step=normalize" class="btn">Run</a>
    </div>
    <div class="pipeline-step">
      <div class="step-num">3</div>
      <div class="step-info">
        <h3>Tag</h3>
        <p>Claude AI assigns tags and anxiety index to each article</p>
      </div>
      <a href="?step=tag" class="btn">Run</a>
    </div>
    <div class="pipeline-step">
      <div class="step-num">4</div>
      <div class="step-info">
        <h3>Synthesize</h3>
        <p>Perplexity AI groups articles into topics with bullet points</p>
      </div>
      <a href="?step=synthesize" class="btn">Run</a>
    </div>
  </div>

  <h2>Recent Logs</h2>
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
</body>
</html>
