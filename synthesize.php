<?php

require_once __DIR__ . '/functions.php';

// ── Only process articles not yet assigned to a topic ─────────────────────────
$articles = db()->query('
    SELECT a.id, a.title
    FROM articles a
    LEFT JOIN article_topics ato ON ato.article_id = a.id
    WHERE a.processed = 1 AND ato.article_id IS NULL
    ORDER BY COALESCE(a.published_at, a.fetched_at) DESC
    LIMIT 150
')->fetchAll();

if (empty($articles)) {
    $msg = 'No new unassigned articles. Nothing to synthesize.';
    log_action('synthesize', 'success', $msg);
    cli_log($msg);
    exit;
}

cli_log(count($articles) . ' new articles to cluster.');

// ── Build article lines for prompt ───────────────────────────────────────────
$article_lines = implode("\n", array_map(
    fn($a) => "ID:{$a['id']} | {$a['title']}",
    $articles
));

// ── Get existing topics for matching ─────────────────────────────────────────
$existing_topics = db()->query('SELECT id, title FROM topics ORDER BY created_at DESC')->fetchAll();
$existing_lines  = empty($existing_topics)
    ? 'None'
    : implode("\n", array_map(fn($t) => "ID:{$t['id']} | {$t['title']}", $existing_topics));

// ── Step 1: Cluster ───────────────────────────────────────────────────────────
$prompt = get_prompt('cluster');
$prompt = str_replace('{{existing_topics}}', $existing_lines, $prompt);
$prompt = str_replace('{{articles}}',        $article_lines,  $prompt);

$system  = 'You are a JSON API. Output only a raw valid JSON array. No markdown, no citations, no extra text.';

cli_log('Calling Perplexity to cluster articles...');
$raw      = call_perplexity($prompt, $system);
$clusters = json_decode(extract_json($raw), true);

if (!$clusters || !is_array($clusters)) {
    $msg = 'Failed to parse cluster response: ' . substr($raw, 0, 300);
    log_action('synthesize', 'error', $msg);
    cli_log($msg);
    exit;
}

cli_log('Got ' . count($clusters) . ' clusters. Processing...');

function dedupe_bullets(array $bullets): array {
    $seen = [];
    $out  = [];
    foreach ($bullets as $b) {
        $b = trim($b);
        if (!$b) continue;
        // Fingerprint: first 4 words lowercased
        $fp = implode(' ', array_slice(explode(' ', strtolower($b)), 0, 4));
        if (!in_array($fp, $seen)) {
            $seen[] = $fp;
            $out[]  = $b;
        }
    }
    return $out;
}

// ── Step 2: Save clusters + generate bullets ──────────────────────────────────
$bullets_prompt_tpl = get_prompt('bullets');
$saved_new   = 0;
$saved_existing = 0;
$topics_needing_bullets = []; // topic_id => [article titles]

$solo_articles = []; // articles left alone — to be merged later

foreach ($clusters as $cluster) {
    $title        = $cluster['title']    ?? 'Untitled';
    $topic_id     = isset($cluster['topic_id']) && $cluster['topic_id'] ? (int)$cluster['topic_id'] : null;
    $article_ids  = $cluster['ids']      ?? [];
    $content_type = $cluster['type']     ?? 'informative';
    $category     = $cluster['category'] ?? null;

    if (empty($article_ids)) continue;

    // Validate article IDs belong to our unassigned set
    $valid_ids = array_values(array_filter($article_ids, fn($id) => in_array((int)$id, array_column($articles, 'id'))));
    if (empty($valid_ids)) continue;

    // Enforce min 2 articles — queue solos for later merging
    if (count($valid_ids) < 2) {
        $solo_articles[] = (int)$valid_ids[0];
        cli_log("  ⚠ Solo article [{$valid_ids[0]}] queued for merging");
        continue;
    }

    // Compute anxiety_avg
    $ids_str     = implode(',', array_map('intval', $valid_ids));
    $anxiety_avg = (float) db()->query(
        "SELECT COALESCE(AVG(score), 5) FROM article_indexes WHERE index_name='anxiety' AND article_id IN ({$ids_str})"
    )->fetchColumn();

    if ($topic_id) {
        // Assign to existing topic
        $st = db()->prepare('INSERT IGNORE INTO article_topics (article_id, topic_id) VALUES (?, ?)');
        foreach ($valid_ids as $aid) $st->execute([$aid, $topic_id]);

        // Update topic anxiety_avg
        db()->prepare('UPDATE topics SET anxiety_avg = (SELECT AVG(ai.score) FROM article_indexes ai JOIN article_topics ato ON ato.article_id = ai.article_id WHERE ato.topic_id = ? AND ai.index_name = "anxiety") WHERE id = ?')
            ->execute([$topic_id, $topic_id]);

        update_topic_geo($topic_id);
        $topics_needing_bullets[$topic_id] = $title;
        $saved_existing++;
        cli_log("  → Assigned to existing [{$topic_id}] {$title}");
    } else {
        // Create new topic — get bullet points first
        $art_titles = db()->query("SELECT title FROM articles WHERE id IN ({$ids_str})")->fetchAll(PDO::FETCH_COLUMN);
        $art_list   = implode("\n", array_map(fn($t) => "- {$t}", $art_titles));
        $max_b = min(5, max(1, count($valid_ids))); $bp_prompt = str_replace(['{{topic}}', '{{articles}}', '{{num_bullets}}'], [$title, $art_list, (string)$max_b], $bullets_prompt_tpl);

        $raw_b   = call_perplexity($bp_prompt, $system);
        $bullets = json_decode(extract_json($raw_b), true);

        if (!is_array($bullets) || count($bullets) < 1) {
            $bullets = array_filter(array_map('trim', preg_split('/\n|•|-/', $raw_b)));
            $bullets = array_values(array_slice($bullets, 0, 3));
        }
        $bullets = dedupe_bullets($bullets); $max_b = min(5, max(1, count($valid_ids))); $bullets = array_map(fn($b) => mb_substr(trim($b), 0, 100), array_slice($bullets, 0, $max_b));

        $new_id = save_topic($title, $bullets, $anxiety_avg, $valid_ids, $content_type, $category);
        update_topic_geo($new_id);
        $saved_new++;
        cli_log("  + New topic [{$new_id}] {$title} (anxiety: " . round($anxiety_avg, 1) . ")");
        foreach ($bullets as $b) cli_log("    • {$b}");
    }
}

// ── Step 3: Merge solo articles into closest existing topic ───────────────────
if (!empty($solo_articles)) {
    cli_log("\nMerging " . count($solo_articles) . " solo article(s) into existing topics...");
    // Find the topic with most articles as a catch-all fallback
    $fallback = db()->query('SELECT id, title FROM topics ORDER BY (SELECT COUNT(*) FROM article_topics WHERE topic_id=topics.id) DESC LIMIT 1')->fetch();
    if ($fallback) {
        $st = db()->prepare('INSERT IGNORE INTO article_topics (article_id, topic_id) VALUES (?, ?)');
        foreach ($solo_articles as $aid) {
            $st->execute([$aid, $fallback['id']]);
            cli_log("  → Merged [{$aid}] into [{$fallback['id']}] {$fallback['title']}");
        }
        $topics_needing_bullets[$fallback['id']] = $fallback['title'];
    }
}

// ── Step 4: Regenerate bullets for existing topics that got new articles ──────
if (!empty($topics_needing_bullets)) {
    cli_log("\nRegenerating bullets for " . count($topics_needing_bullets) . " updated topics...");
    foreach ($topics_needing_bullets as $topic_id => $title) {
        $art_titles = db()->query(
            "SELECT a.title FROM articles a JOIN article_topics ato ON ato.article_id = a.id WHERE ato.topic_id = {$topic_id}"
        )->fetchAll(PDO::FETCH_COLUMN);

        if (empty($art_titles)) continue;

        $art_list  = implode("\n", array_map(fn($t) => "- {$t}", $art_titles));
        $topic_art_count = db()->query("SELECT COUNT(*) FROM article_topics WHERE topic_id = {$topic_id}")->fetchColumn(); $max_b = min(5, max(1, (int)$topic_art_count)); $bp_prompt = str_replace(['{{topic}}', '{{articles}}', '{{num_bullets}}'], [$title, $art_list, (string)$max_b], $bullets_prompt_tpl);
        $raw_b     = call_perplexity($bp_prompt, $system);
        $bullets   = json_decode(extract_json($raw_b), true);

        if (!is_array($bullets) || count($bullets) < 1) {
            $bullets = array_filter(array_map('trim', preg_split('/\n|•|-/', $raw_b)));
            $bullets = array_values(array_slice($bullets, 0, 3));
        }
        $topic_art_count = db()->query("SELECT COUNT(*) FROM article_topics WHERE topic_id = {$topic_id}")->fetchColumn(); $bullets = dedupe_bullets($bullets); $max_b = min(5, max(1, (int)$topic_art_count)); $bullets = array_map(fn($b) => mb_substr(trim($b), 0, 100), array_slice($bullets, 0, $max_b));

        db()->prepare('DELETE FROM topic_bullets WHERE topic_id = ?')->execute([$topic_id]);
        $st = db()->prepare('INSERT INTO topic_bullets (topic_id, bullet, display_order) VALUES (?, ?, ?)');
        foreach ($bullets as $i => $b) $st->execute([$topic_id, $b, $i]);

        cli_log("  ↺ Updated bullets for [{$topic_id}] {$title}");
    }
}

$msg = "{$saved_new} new topics created, {$saved_existing} existing topics updated";
log_action('synthesize', 'success', $msg);
cli_log("\nDone: {$msg}");

function cli_log(string $msg): void {
    if (php_sapi_name() === 'cli') echo $msg . "\n";
}
