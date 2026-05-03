<?php

require_once __DIR__ . '/functions.php';

// Delete articles older than 24h
$deleted = db()->exec("DELETE FROM articles WHERE fetched_at < NOW() - INTERVAL 24 HOUR");
if ($deleted > 0) log_action('fetch', 'success', "Purged {$deleted} articles older than 24h");

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
    echo json_encode(['total_new' => $total, 'feeds' => $results]);
}
