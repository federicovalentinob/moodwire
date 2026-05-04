<?php
require_once __DIR__ . '/functions.php';

$categories = ['Politics','Geopolitics','Economy','Technology','Science','Health','Society','Crime','Environment','Sports','Entertainment','Travel','Food'];
$types      = ['informative','educative','entertainment'];
$type_labels = ['informative'=>'Informative','educative'=>'Educative','entertainment'=>'Entertainment'];

$topics = db()->query("
    SELECT t.*, COUNT(ato.article_id) as article_count
    FROM topics t
    LEFT JOIN article_topics ato ON ato.topic_id = t.id
    WHERE t.category IS NOT NULL AND t.content_type IS NOT NULL
    GROUP BY t.id
    ORDER BY t.anxiety_avg DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Moodwire — Topic Map</title>
<link rel="stylesheet" href="style.css">
<style>
  .map-wrap { overflow-x: auto; padding-bottom: 20px; }
  .map-grid {
    display: grid;
    grid-template-columns: 100px repeat(<?= count($categories) ?>, 1fr);
    grid-template-rows: auto repeat(<?= count($types) ?>, auto);
    gap: 2px;
    min-width: 900px;
  }
  .map-corner { background: transparent; }
  .map-col-header {
    background: #1e3a5f;
    color: white;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    text-align: center;
    padding: 8px 4px;
    border-radius: 4px;
  }
  .map-row-header {
    background: #1e3a5f;
    color: white;
    font-size: 11px;
    font-weight: 700;
    writing-mode: vertical-lr;
    transform: rotate(180deg);
    text-align: center;
    padding: 12px 6px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .map-cell {
    background: #f8fafc;
    border-radius: 6px;
    padding: 6px;
    min-height: 80px;
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    align-content: flex-start;
  }
  .map-cell:empty { background: #f1f5f9; }
  .topic-bubble {
    display: block;
    padding: 4px 7px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    color: white;
    cursor: pointer;
    text-decoration: none;
    line-height: 1.3;
    max-width: 100%;
    word-break: break-word;
    transition: opacity 0.15s;
  }
  .topic-bubble:hover { opacity: 0.85; }
  .legend {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
  }
  .legend-item { display: flex; align-items: center; gap: 6px; font-size: 12px; }
  .legend-dot { width: 16px; height: 16px; border-radius: 50%; }

  /* Tooltip */
  .topic-bubble[data-tip] { position: relative; }
  .topic-bubble[data-tip]:hover::after {
    content: attr(data-tip);
    position: absolute;
    bottom: 110%;
    left: 50%;
    transform: translateX(-50%);
    background: #1e293b;
    color: #f1f5f9;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 11px;
    white-space: nowrap;
    z-index: 999;
    pointer-events: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    font-weight: normal;
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

  <div class="legend">
    <strong style="font-size:12px">Anxiety:</strong>
    <div class="legend-item"><div class="legend-dot" style="background:#16a34a"></div> Low (0–3)</div>
    <div class="legend-item"><div class="legend-dot" style="background:#ca8a04"></div> Medium (4–6)</div>
    <div class="legend-item"><div class="legend-dot" style="background:#dc2626"></div> High (7–10)</div>
    <span style="color:#94a3b8;font-size:11px;margin-left:8px">Hover a topic to see its title. Click to view articles.</span>
  </div>

  <div class="map-wrap">
    <div class="map-grid">

      <!-- Corner -->
      <div class="map-corner"></div>

      <!-- Column headers (categories) -->
      <?php foreach ($categories as $cat): ?>
        <div class="map-col-header"><?= $cat ?></div>
      <?php endforeach; ?>

      <!-- Rows (types) -->
      <?php foreach ($types as $type): ?>

        <!-- Row header -->
        <div class="map-row-header"><?= $type_labels[$type] ?></div>

        <!-- Cells -->
        <?php foreach ($categories as $cat): ?>
          <div class="map-cell">
            <?php foreach ($topics as $t):
              if (($t['content_type'] ?? '') !== $type) continue;
              if (($t['category']     ?? '') !== $cat)  continue;
              $anx = (float)$t['anxiety_avg'];
              if      ($anx <= 3) $color = '#16a34a';
              elseif  ($anx <= 6) $color = '#ca8a04';
              else                $color = '#dc2626';
              $opacity = 0.6 + ($anx / 10) * 0.4; // more intense = more anxious
            ?>
              <a href="index.php?category=<?= urlencode($cat) ?>&type=<?= $type ?>"
                 class="topic-bubble"
                 style="background:<?= $color ?>;opacity:<?= round($opacity,2) ?>"
                 data-tip="<?= htmlspecialchars($t['title']) ?> (<?= number_format($anx,1) ?>)">
                <?= htmlspecialchars(implode(' ', array_slice(explode(' ', $t['title']), 0, 3))) ?>…
              </a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>

      <?php endforeach; ?>

    </div>
  </div>
</div>
</body>
</html>
