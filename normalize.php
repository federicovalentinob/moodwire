<?php

require_once __DIR__ . '/functions.php';

$articles = get_unnormalized_articles();
$count    = 0;

foreach ($articles as $article) {
    $clean = normalize_text($article['raw_content'] ?? $article['title']);
    save_clean_content($article['id'], $clean);
    $count++;
}

log_action('normalize', 'success', "{$count} articles normalized");

if (php_sapi_name() === 'cli') {
    echo "{$count} articles normalized\n";
} else {
    header('Content-Type: application/json');
    echo json_encode(['normalized' => $count]);
}
