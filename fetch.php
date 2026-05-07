<?php

require_once __DIR__ . '/functions.php';

// Delete articles belonging to topics older than 24h — remove thumbnails first
$stale = db()->query("
    SELECT DISTINCT a.image_path FROM articles a
    JOIN article_topics ato ON ato.article_id = a.id
    JOIN topics t ON t.id = ato.topic_id
    WHERE t.created_at < NOW() - INTERVAL 24 HOUR
    AND a.image_path IS NOT NULL
")->fetchAll(PDO::FETCH_COLUMN);
foreach ($stale as $path) {
    if ($path && file_exists(__DIR__ . '/' . $path)) @unlink(__DIR__ . '/' . $path);
}
$deleted = (int) db()->exec("
    DELETE a FROM articles a
    JOIN article_topics ato ON ato.article_id = a.id
    JOIN topics t ON t.id = ato.topic_id
    WHERE t.created_at < NOW() - INTERVAL 24 HOUR
");

// Safety net: delete orphan articles (never assigned to a topic) older than 24h
$stale_orphan = db()->query("
    SELECT a.image_path FROM articles a
    LEFT JOIN article_topics ato ON ato.article_id = a.id
    WHERE ato.article_id IS NULL AND a.fetched_at < NOW() - INTERVAL 24 HOUR
    AND a.image_path IS NOT NULL
")->fetchAll(PDO::FETCH_COLUMN);
foreach ($stale_orphan as $path) {
    if ($path && file_exists(__DIR__ . '/' . $path)) @unlink(__DIR__ . '/' . $path);
}
$deleted += (int) db()->exec("
    DELETE a FROM articles a
    LEFT JOIN article_topics ato ON ato.article_id = a.id
    WHERE ato.article_id IS NULL AND a.fetched_at < NOW() - INTERVAL 24 HOUR
");

if ($deleted > 0) {
    log_action('fetch', 'success', "Purged {$deleted} articles from expired/orphan topics");
    $purged_topics = purge_empty_topics();
    if ($purged_topics > 0) log_action('fetch', 'success', "Removed {$purged_topics} empty topics");
    regenerate_stale_bullets();
}

$feeds   = get_feeds(active_only: true);
$total   = 0;
$results = [];

foreach ($feeds as $feed) {
    $articles = fetch_rss($feed['url']);
    $saved    = 0;

    foreach ($articles as $article) {
        if (save_article($feed['id'], $article)) $saved++;
    }

    update_feed_fetched($feed['id']);
    log_action('fetch', 'success', "Feed [{$feed['name']}]: {$saved} new articles");
    $results[] = ['feed' => $feed['name'], 'saved' => $saved, 'total' => count($articles)];
    $total += $saved;
}

if (php_sapi_name() === 'cli') {
    foreach ($results as $r) {
        echo "{$r['feed']}: {$r['saved']} new / {$r['total']} fetched\n";
    }
    echo "Total new articles: {$total}\n";
} else {
    header('Content-Type: application/json');
    $total_articles = (int) db()->query('SELECT COUNT(*) FROM articles')->fetchColumn();
    echo json_encode([
        'removed'        => $deleted,
        'total_new'      => $total,
        'total_articles' => $total_articles,
        'feeds'          => $results,
    ]);
}
