<?php

require_once __DIR__ . '/functions.php';

$topics      = get_latest_topics();
$filter_tag  = trim($_GET['tag']     ?? '');
$filter_anx  = trim($_GET['anxiety'] ?? '');
$filter_type = trim($_GET['type']    ?? '');

// Collect all unique tags across articles
$all_tags = db()->query('SELECT DISTINCT tag FROM article_tags ORDER BY tag')->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Moodwire</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
  <nav class="nav">
    <a href="index.php" class="nav-logo">Moodwire</a>
    <a href="feeds.php">Feeds</a>
    <a href="run.php">Run Pipeline</a>
  </nav>

  <div class="filters">
    <a href="index.php" class="filter-btn <?= !$filter_tag && !$filter_anx ? 'active' : '' ?>">All</a>

    <span class="filter-sep">Anxiety:</span>
    <a href="?anxiety=low"    class="filter-btn anxiety-low    <?= $filter_anx==='low'    ? 'active':'' ?>">Low (0–3)</a>
    <a href="?anxiety=medium" class="filter-btn anxiety-medium <?= $filter_anx==='medium' ? 'active':'' ?>">Medium (4–6)</a>
    <a href="?anxiety=high"   class="filter-btn anxiety-high   <?= $filter_anx==='high'   ? 'active':'' ?>">High (7–10)</a>

    <span class="filter-sep">Type:</span>
    <a href="?type=informative"   class="filter-btn <?= $filter_type==='informative'   ? 'active':'' ?>">Informative</a>
    <a href="?type=educative"     class="filter-btn <?= $filter_type==='educative'     ? 'active':'' ?>">Educative</a>
    <a href="?type=entertainment" class="filter-btn <?= $filter_type==='entertainment' ? 'active':'' ?>">Entertainment</a>

  </div>

  <?php if (empty($topics)): ?>
    <div class="empty">
      No topics yet. <a href="run.php">Run the pipeline</a> to get started.
    </div>
  <?php endif; ?>

  <?php foreach ($topics as $topic):
    $anx   = (float)$topic['anxiety_avg'];
    $color = anxiety_color($anx);
    $label = anxiety_label($anx);

    $filtered_articles = $topic['articles'];

    // Filter by anxiety
    if ($filter_anx) {
        if ($filter_anx === 'low'    && $anx > 3)           continue;
        if ($filter_anx === 'medium' && ($anx < 4 || $anx > 6)) continue;
        if ($filter_anx === 'high'   && $anx < 7)           continue;
    }

    // Filter by content type
    if ($filter_type && ($topic['content_type'] ?? '') !== $filter_type) continue;

    $content_type = $topic['content_type'] ?? 'informative';
    $type_labels  = ['informative' => 'Informative', 'educative' => 'Educative', 'entertainment' => 'Entertainment'];

    // Filter by tag
    if ($filter_tag) {
        $filtered_articles = array_filter($filtered_articles, function($a) use ($filter_tag) {
            $tags = array_map('trim', explode(',', $a['tags'] ?? ''));
            return in_array($filter_tag, $tags);
        });
        if (empty($filtered_articles)) continue;
    }
  ?>
  <div class="topic-card">
    <div class="topic-header">
      <h2 class="topic-title">
        <?= htmlspecialchars($topic['title']) ?>
        <?php if ($topic['latest_article_at']): ?>
          <span class="topic-age"><?= time_ago($topic['latest_article_at']) ?></span>
        <?php endif; ?>
      </h2>
      <div style="display:flex;gap:6px;align-items:center">
        <span class="type-badge type-<?= $content_type ?>"><?= $type_labels[$content_type] ?></span>
        <span class="anxiety-badge" style="background:<?= $color ?>"><?= $label ?> <?= number_format($anx, 1) ?></span>
      </div>
    </div>

    <ul class="bullets">
      <?php foreach ($topic['bullets'] as $bullet): ?>
        <li><?= htmlspecialchars($bullet) ?></li>
      <?php endforeach; ?>
    </ul>

    <?php if (!empty($filtered_articles)): ?>
    <div class="articles">
      <?php foreach ($filtered_articles as $a):
        $a_anx   = (float)($a['anxiety'] ?? 5);
        $a_color = anxiety_color($a_anx);
        $a_tags  = array_filter(array_map('trim', explode(',', $a['tags'] ?? '')));
      ?>
        <a href="<?= htmlspecialchars($a['url']) ?>" target="_blank" class="article-row">
          <span class="article-anx" style="background:<?= $a_color ?>"><?= number_format($a_anx, 0) ?></span>
          <span class="article-title"><?= htmlspecialchars($a['title']) ?></span>
          <span class="article-source"><?= htmlspecialchars($a['feed_name']) ?></span>
          <span class="article-tags">
            <?php foreach ($a_tags as $t):
              if (str_starts_with($t, 'country:')): ?>
                <span class="tag-country"><?= htmlspecialchars(substr($t, 8)) ?></span>
              <?php else: ?>
                <span class="tag"><?= htmlspecialchars($t) ?></span>
              <?php endif; ?>
            <?php endforeach; ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
</body>
</html>
