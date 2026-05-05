<?php

require_once __DIR__ . '/functions.php';

define('BATCH_SIZE', 50);
define('RETRY_BATCH_SIZE', 15); // smaller retry batch when truncated

$total_tagged = 0;
$total_errors = 0;

function save_results(array $articles, array $results): array {
    $missing = [];
    foreach ($articles as $article) {
        $r = $results[$article['id']] ?? null;
        if (!$r) { $missing[] = $article; continue; }
        save_article_tags($article['id'], $r['tags'] ?? []);
        save_article_country($article['id'], $r['country'] ?? null);
        save_article_index($article['id'], 'anxiety', (float)($r['anxiety'] ?? 5));
        mark_article_processed($article['id']);
        global $total_tagged;
        $total_tagged++;
        echo "  [{$article['id']}] " . implode(', ', $r['tags'] ?? []) . " | Anxiety: {$r['anxiety']}\n";
    }
    return $missing;
}

while (true) {
    $articles = get_unprocessed_articles(BATCH_SIZE);
    if (empty($articles)) break;

    echo 'Tagging batch of ' . count($articles) . ' articles...' . "\n";

    // Round 1: full batch
    $results = tag_articles_batch($articles);
    $missing = save_results($articles, $results);

    // Round 2: retry missing as smaller batches
    if (!empty($missing)) {
        echo "  " . count($missing) . " missed — retrying in batches of " . RETRY_BATCH_SIZE . "...\n";
        foreach (array_chunk($missing, RETRY_BATCH_SIZE) as $chunk) {
            $results2 = tag_articles_batch($chunk);
            $still_missing = save_results($chunk, $results2);

            // Round 3: individual fallback for anything still missing
            foreach ($still_missing as $article) {
                echo "  Retrying [{$article['id']}] individually...\n";
                $single = tag_articles_batch([$article]);
                $r = $single[$article['id']] ?? null;
                if (!$r) {
                    $r = ['tags' => ['news'], 'anxiety' => 5];
                    log_action('tag', 'warning', "Defaulted [{$article['id']}]");
                    echo "  Defaulted [{$article['id']}]\n";
                    global $total_errors;
                    $total_errors++;
                }
                save_article_tags($article['id'], $r['tags'] ?? []);
                save_article_country($article['id'], $r['country'] ?? null);
                save_article_index($article['id'], 'anxiety', (float)($r['anxiety'] ?? 5));
                mark_article_processed($article['id']);
                global $total_tagged;
                $total_tagged++;
            }
        }
    }
    echo "\n";
}

log_action('tag', 'success', "{$total_tagged} articles tagged, {$total_errors} errors");
echo "Done: {$total_tagged} tagged, {$total_errors} errors\n";
