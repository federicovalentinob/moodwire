<?php

require_once __DIR__ . '/functions.php';

$total_tagged  = 0;
$total_errors  = 0;

// Process all untagged articles in batches of 10
while (true) {
    $articles = get_unprocessed_articles(10);
    if (empty($articles)) break;

    foreach ($articles as $article) {
        try {
            $result = tag_article($article);
            save_article_tags($article['id'], $result['tags']);
            save_article_index($article['id'], 'anxiety', (float)$result['anxiety']);
            mark_article_processed($article['id']);
            $total_tagged++;
            echo "Tagged [{$article['id']}]: {$article['title']}\n";
            echo "  Tags: " . implode(', ', $result['tags']) . " | Anxiety: {$result['anxiety']}\n";
        } catch (Exception $e) {
            $total_errors++;
            log_action('tag', 'error', "Article [{$article['id']}]: " . $e->getMessage());
            echo "Error [{$article['id']}]: " . $e->getMessage() . "\n";
        }
    }
}

log_action('tag', 'success', "{$total_tagged} articles tagged, {$total_errors} errors");
echo "\nDone: {$total_tagged} tagged, {$total_errors} errors\n";
