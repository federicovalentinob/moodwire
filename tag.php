<?php

require_once __DIR__ . '/functions.php';

define('BATCH_SIZE', 10);

$total_tagged = 0;
$total_errors = 0;

while (true) {
    $articles = get_unprocessed_articles(BATCH_SIZE);
    if (empty($articles)) break;

    echo 'Tagging batch of ' . count($articles) . ' articles...' . "\n";

    $results = tag_articles_batch($articles);

    foreach ($articles as $article) {
        $r = $results[$article['id']] ?? null;
        if (!$r) {
            $total_errors++;
            log_action('tag', 'error', "No result for article [{$article['id']}]");
            echo "  No result for [{$article['id']}]: {$article['title']}\n";
            // Mark processed anyway to avoid infinite loop
            mark_article_processed($article['id']);
            continue;
        }
        save_article_tags($article['id'], $r['tags'] ?? []);
        save_article_index($article['id'], 'anxiety', (float)($r['anxiety'] ?? 5));
        mark_article_processed($article['id']);
        $total_tagged++;
        echo "  [{$article['id']}] " . implode(', ', $r['tags'] ?? []) . " | Anxiety: {$r['anxiety']}\n";
    }
    echo "\n";
}

log_action('tag', 'success', "{$total_tagged} articles tagged, {$total_errors} errors");
echo "Done: {$total_tagged} tagged, {$total_errors} errors\n";
