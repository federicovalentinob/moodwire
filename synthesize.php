<?php

require_once __DIR__ . '/functions.php';

// ── Step 1: Cluster articles into topics ─────────────────────────────────────

$articles = db()->query('
    SELECT a.id, a.title,
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
    cli_log($msg);
    exit;
}

cli_log('Step 1: Clustering ' . count($articles) . ' articles into topics...');

$article_lines = '';
foreach ($articles as $a) {
    $article_lines .= "ID:{$a['id']} | Anxiety:{$a['anxiety']} | {$a['title']}\n";
}

$cluster_prompt = get_prompt('cluster');
$cluster_prompt = str_replace('{{articles}}', $article_lines, $cluster_prompt);

$system  = 'You are a JSON API. Output only a raw valid JSON array. No markdown, no citations, no explanations, no extra text.';
$raw     = call_perplexity($cluster_prompt, $system);
$clean   = extract_json($raw);
$clusters = json_decode($clean, true);

if (!$clusters || !is_array($clusters)) {
    $msg = 'Failed to parse cluster response: ' . substr($raw, 0, 300);
    log_action('synthesize', 'error', $msg);
    cli_log($msg);
    exit;
}

cli_log('Got ' . count($clusters) . ' topics. Step 2: Generating bullet points...');

// ── Step 2: Generate bullets per topic ───────────────────────────────────────

// Clear old topics
db()->exec('DELETE FROM article_topics');
db()->exec('DELETE FROM topic_bullets');
db()->exec('DELETE FROM topics');

$bullets_prompt_tpl = get_prompt('bullets');
$saved = 0;

foreach ($clusters as $cluster) {
    $title       = $cluster['title'] ?? 'Untitled';
    $article_ids = $cluster['ids']   ?? $cluster['article_ids'] ?? [];

    if (empty($article_ids)) continue;

    // Compute anxiety_avg from DB
    $ids_str     = implode(',', array_map('intval', $article_ids));
    $anxiety_avg = (float) db()->query(
        "SELECT AVG(score) FROM article_indexes WHERE index_name='anxiety' AND article_id IN ({$ids_str})"
    )->fetchColumn();

    // Fetch titles for this cluster
    $ids_sql    = implode(',', array_map('intval', $article_ids));
    $art_titles = db()->query("SELECT id, title FROM articles WHERE id IN ({$ids_sql})")->fetchAll();
    $art_list   = implode("\n", array_map(fn($a) => "- {$a['title']}", $art_titles));

    $prompt = str_replace('{{topic}}',    $title,    $bullets_prompt_tpl);
    $prompt = str_replace('{{articles}}', $art_list, $bullets_prompt_tpl);
    $prompt = str_replace('{{topic}}',    $title,    $prompt);

    $raw    = call_perplexity($prompt, $system);
    $clean  = extract_json($raw);
    $bullets = json_decode($clean, true);

    if (!is_array($bullets) || count($bullets) < 1) {
        // Fallback: split plain text into bullets
        $bullets = array_filter(array_map('trim', preg_split('/\n|•|-/', $raw)));
        $bullets = array_values(array_slice($bullets, 0, 3));
    }

    $bullets = array_slice($bullets, 0, 3);

    $id = save_topic($title, $bullets, $anxiety_avg, $article_ids);
    $saved++;
    cli_log("  [{$id}] {$title} (anxiety: {$anxiety_avg})");
    foreach ($bullets as $b) cli_log("    • {$b}");
}

log_action('synthesize', 'success', "{$saved} topics generated from " . count($articles) . " articles");
cli_log("\nDone: {$saved} topics saved.");

function cli_log(string $msg): void {
    if (php_sapi_name() === 'cli') echo $msg . "\n";
}
