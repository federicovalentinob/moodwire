<?php

require_once __DIR__ . '/functions.php';

ignore_user_abort(true);
set_time_limit(0);

// All new topics from this pipeline run
$new_topics = db()->query("
    SELECT t.id, t.title, COUNT(ato.article_id) as article_count
    FROM topics t
    LEFT JOIN article_topics ato ON ato.topic_id = t.id
    WHERE t.is_new = 1
    GROUP BY t.id
    ORDER BY article_count DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (count($new_topics) < 2) {
    cli_log("  Only " . count($new_topics) . " new topics — nothing to merge.");
    log_action('merge', 'success', '0 merges');
    return;
}

cli_log("  Checking " . count($new_topics) . " new topics for duplicates...");

$lines = implode("\n", array_map(fn($t) => "ID:{$t['id']} | {$t['title']}", $new_topics));

$prompt = "You are a news editor. Below is a list of news topic titles.\n\n"
        . "Identify groups of topics that are clearly about the SAME specific news event — e.g. two topics both covering the same political summit, the same court ruling, the same sports match.\n\n"
        . "Rules:\n"
        . "- Only group topics if they are genuinely about the SAME event, not just a shared theme\n"
        . "- Different days or phases of the same multi-day event count as the same event\n"
        . "- A group must have at least 2 topic IDs\n"
        . "- If a topic has no duplicate, leave it out entirely\n\n"
        . "Return ONLY a JSON array of groups, each group being an array of topic IDs.\n"
        . "Example: [[12,15],[8,22,31]]\n"
        . "If no duplicates exist return: []\n\n"
        . "Topics:\n{$lines}";

$raw    = call_openai_chat($prompt, 'You are a JSON API. Return only raw valid JSON. No markdown, no explanation.');
$groups = json_decode(extract_json($raw), true);

if (!is_array($groups) || empty($groups)) {
    cli_log("  No duplicates found.");
    log_action('merge', 'success', '0 merges');
    return;
}

$valid_ids = array_column($new_topics, 'id');
$topic_map = array_column($new_topics, null, 'id');
$merged_count = 0;

foreach ($groups as $group) {
    $group = array_values(array_filter(array_map('intval', $group), fn($id) => in_array($id, $valid_ids)));
    if (count($group) < 2) continue;

    // Primary = most articles
    usort($group, fn($a, $b) => ($topic_map[$b]['article_count'] ?? 0) <=> ($topic_map[$a]['article_count'] ?? 0));
    $primary_id    = $group[0];
    $secondary_ids = array_slice($group, 1);
    $sid_csv       = implode(',', array_map('intval', $secondary_ids));

    cli_log("  Merging [" . implode(',', $secondary_ids) . "] → {$primary_id} ({$topic_map[$primary_id]['title']})");

    // Re-point secondary articles to primary (ignore duplicates)
    $mv = db()->prepare("UPDATE IGNORE article_topics SET topic_id = ? WHERE topic_id = ?");
    foreach ($secondary_ids as $sid) $mv->execute([$primary_id, $sid]);

    // Remove any leftover article_topics rows for secondaries (in case UPDATE IGNORE left some)
    db()->exec("DELETE FROM article_topics WHERE topic_id IN ($sid_csv)");
    db()->exec("DELETE FROM topics WHERE id IN ($sid_csv)");

    // Recalculate anxiety_avg from merged article set
    $new_anxiety = (float) db()->query("
        SELECT COALESCE(AVG(ai.score), 5)
        FROM article_indexes ai
        JOIN article_topics ato ON ato.article_id = ai.article_id
        WHERE ato.topic_id = $primary_id AND ai.index_name = 'anxiety'
    ")->fetchColumn();

    // Regenerate bullets for the merged topic
    $art_titles = db()->query("
        SELECT a.title FROM articles a
        JOIN article_topics ato ON ato.article_id = a.id
        WHERE ato.topic_id = $primary_id
        ORDER BY a.published_at DESC LIMIT 10
    ")->fetchAll(PDO::FETCH_COLUMN);

    $bullet_count = min(5, max(2, count($art_titles)));
    $art_list     = implode("\n", array_map(fn($t) => "- $t", $art_titles));
    $topic_title  = $topic_map[$primary_id]['title'];
    $topics_str   = "TOPIC_ID:0 \"{$topic_title}\" ({$bullet_count} bullets)\n{$art_list}\n\n";

    $batch_prompt = str_replace('{{topics}}', $topics_str, get_prompt('bullets_batch'));
    $raw_batch    = call_openai_chat($batch_prompt);
    $batch_result = json_decode(extract_json($raw_batch), true);

    $bullets = [];
    if (is_array($batch_result)) {
        foreach ($batch_result as $r) {
            if (isset($r['id']) && (int)$r['id'] === 0 && !empty($r['bullets'])) {
                $bullets = $r['bullets'];
                break;
            }
        }
    }
    if (empty($bullets)) $bullets = ["No summary available."];
    $bullets = array_slice(dedupe_bullets(array_map('trim', $bullets)), 0, $bullet_count);

    db()->prepare("UPDATE topics SET anxiety_avg = ? WHERE id = ?")->execute([$new_anxiety, $primary_id]);
    db()->prepare("DELETE FROM topic_bullets WHERE topic_id = ?")->execute([$primary_id]);
    $ins = db()->prepare("INSERT INTO topic_bullets (topic_id, bullet, display_order) VALUES (?, ?, ?)");
    foreach ($bullets as $i => $b) $ins->execute([$primary_id, $b, $i]);
    update_topic_geo($primary_id);

    cli_log("  ✓ Merged " . count($secondary_ids) . " duplicate(s) into topic $primary_id");
    foreach ($bullets as $b) cli_log("    • $b");
    $merged_count++;
}

log_action('merge', 'success', "$merged_count duplicate groups merged");
cli_log("[Merge] Done. $merged_count duplicate group(s) resolved.");
