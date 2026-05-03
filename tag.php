<?php

require_once __DIR__ . '/functions.php';

$articles = get_unprocessed_articles();
$count    = 0;
$errors   = 0;

foreach ($articles as $article) {
    try {
        $result = tag_article($article);

        save_article_tags($article['id'], $result['tags']);
        save_article_index($article['id'], 'anxiety', (float)$result['anxiety']);
        mark_article_processed($article['id']);
        $count++;

        if (php_sapi_name() === 'cli') {
            echo "Tagged [{$article['id']}]: {$article['title']}\n";
            echo "  Tags: " . implode(', ', $result['tags']) . " | Anxiety: {$result['anxiety']}\n";
        }
    } catch (Exception $e) {
        $errors++;
        log_action('tag', 'error', "Article [{$article['id']}]: " . $e->getMessage());
    }
}

log_action('tag', 'success', "{$count} articles tagged, {$errors} errors");

if (php_sapi_name() === 'cli') {
    echo "\nDone: {$count} tagged, {$errors} errors\n";
} else {
    header('Content-Type: application/json');
    echo json_encode(['tagged' => $count, 'errors' => $errors]);
}
