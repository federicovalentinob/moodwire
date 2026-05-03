<?php

require_once __DIR__ . '/functions.php';

// Build article list for the prompt
$articles = db()->query('
    SELECT a.id, a.title, a.clean_content,
           GROUP_CONCAT(DISTINCT at2.tag ORDER BY at2.tag SEPARATOR ", ") as tags,
           ai.score as anxiety
    FROM articles a
    LEFT JOIN article_tags at2 ON at2.article_id = a.id
    LEFT JOIN article_indexes ai ON ai.article_id = a.id AND ai.index_name = "anxiety"
    WHERE a.processed = 1
    GROUP BY a.id
    ORDER BY a.fetched_at DESC
    LIMIT 100
')->fetchAll();

if (empty($articles)) {
    $msg = 'No processed articles found. Run tag.php first.';
    log_action('synthesize', 'error', $msg);
    echo $msg . "\n";
    exit;
}

// Format articles for the prompt
$article_lines = '';
foreach ($articles as $a) {
    $article_lines .= "ID:{$a['id']} | {$a['title']} | Tags: {$a['tags']} | Anxiety: {$a['anxiety']}\n";
    if (!empty($a['clean_content'])) {
        $article_lines .= "  " . substr($a['clean_content'], 0, 200) . "\n";
    }
    $article_lines .= "\n";
}

$prompt = get_prompt('synthesis');
$prompt = str_replace('{{num_topics}}', NUM_TOPICS, $prompt);
$prompt = str_replace('{{articles}}',   $article_lines, $prompt);

if (php_sapi_name() === 'cli') echo "Calling Perplexity API...\n";

$raw  = call_perplexity($prompt);
$json = preg_replace('/^```json\s*|\s*```$/m', '', trim($raw));
$topics = json_decode($json, true);

if (!$topics || !is_array($topics)) {
    $msg = 'Failed to parse Perplexity response: ' . $raw;
    log_action('synthesize', 'error', $msg);
    echo $msg . "\n";
    exit;
}

foreach ($topics as $topic) {
    $id = save_topic(
        $topic['title'],
        $topic['bullets'],
        (float)($topic['anxiety_avg'] ?? 5),
        $topic['article_ids'] ?? []
    );

    if (php_sapi_name() === 'cli') {
        echo "Topic [{$id}]: {$topic['title']} (anxiety: {$topic['anxiety_avg']})\n";
        foreach ($topic['bullets'] as $b) echo "  • {$b}\n";
        echo "\n";
    }
}

log_action('synthesize', 'success', count($topics) . ' topics generated');

if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode(['topics' => count($topics)]);
}
