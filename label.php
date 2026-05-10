<?php

require_once __DIR__ . '/functions.php';

ignore_user_abort(true);
set_time_limit(0);

$schema = [
    'type' => 'object',
    'additionalProperties' => false,
    'properties' => [
        'title'   => ['type' => 'string',  'description' => 'A short, complete newspaper headline (a single grammatical thought).'],
        'bullets' => ['type' => 'array',   'items' => ['type' => 'string'], 'description' => 'Exactly 3 short bullet points summarizing the key facts. Always return 3 — no more, no fewer.'],
        'anxiety' => ['type' => 'number',  'description' => 'A score 0-10 where 0 is very positive/relaxing and 10 is very alarming/stressful.'],
        'country' => ['type' => 'string',  'description' => 'ISO 3166-1 alpha-2 country code most associated with the story, or "XX" if global/none.'],
        'category'=> ['type' => 'string',  'description' => 'One of: Politics, Geopolitics, Economy, Technology, Science, Health, Society, Crime, Environment, Sports, Entertainment, Travel, Food.'],
        'type'    => ['type' => 'string',  'description' => 'One of: informative, educative, entertainment.'],
    ],
    'required' => ['title','bullets','anxiety','country','category','type'],
];

$system = 'You are a senior news editor. You will be given a list of article titles that all cover the same news story. Produce a JSON object describing one topic for that story.';

// Pull each cluster's article ids + titles
$clusters = db()->query("
    SELECT tc.cluster_id, GROUP_CONCAT(tc.article_id) AS ids
    FROM topic_clusters tc
    GROUP BY tc.cluster_id
    ORDER BY COUNT(*) DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($clusters)) {
    cli_log("  No clusters to label.");
    log_action('label', 'success', '0 topics');
    return;
}

cli_log("  Labeling " . count($clusters) . " clusters...");

// Clear NEW flag on all existing topics so only this run's topics show as NEW.
db()->exec('UPDATE topics SET is_new = 0');

$saved = 0; $failed = 0;
foreach ($clusters as $c) {
    $ids = array_map('intval', explode(',', $c['ids']));
    $sz  = count($ids);

    $sample = array_slice($ids, 0, 12);
    $titles = db()->prepare("SELECT title FROM articles WHERE id IN (" . implode(',', array_fill(0, count($sample), '?')) . ")");
    $titles->execute($sample);
    $title_list = $titles->fetchAll(PDO::FETCH_COLUMN);

    $user = "These articles all cover one news story:\n\n" .
            implode("\n", array_map(fn($t) => "- $t", $title_list));

    $obj = call_openai_chat_json($system, $user, $schema);
    if (!$obj || empty($obj['title'])) {
        cli_log("    [#{$c['cluster_id']} sz=$sz] FAILED");
        $failed++;
        continue;
    }

    // Save topic
    $bullets = is_array($obj['bullets']) ? array_map('trim', $obj['bullets']) : [];
    $bullets = array_slice(dedupe_bullets($bullets), 0, 3);

    $topic_id = save_topic(
        trim_title($obj['title']),
        $bullets,
        (float)$obj['anxiety'],
        $ids,
        $obj['type']    ?? 'informative',
        $obj['category'] ?? null
    );

    // Propagate country tag to articles, then derive topic.geo from the cluster's article_tags
    $cc = strtoupper(trim((string)($obj['country'] ?? '')));
    if (preg_match('/^[A-Z]{2}$/', $cc) && $cc !== 'XX') {
        $tagInsert = db()->prepare("INSERT IGNORE INTO article_tags (article_id, tag) VALUES (?, ?)");
        foreach ($ids as $aid) $tagInsert->execute([$aid, "country:$cc"]);
    }
    update_topic_geo($topic_id);

    cli_log("    ✓ [$topic_id sz=$sz] " . substr($obj['title'], 0, 90));
    $saved++;
}

log_action('label', 'success', "$saved topics saved, $failed failed");
cli_log("[Label] Done. $saved topics saved.");
