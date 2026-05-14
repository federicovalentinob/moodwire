<?php

require_once __DIR__ . '/functions.php';

ignore_user_abort(true);
set_time_limit(0);

$min_size = defined('CLUSTER_MIN_SIZE') ? (int)CLUSTER_MIN_SIZE : 3;

db()->exec("CREATE TABLE IF NOT EXISTS topic_clusters (
    article_id INT PRIMARY KEY,
    cluster_id INT NOT NULL,
    KEY (cluster_id),
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

db()->exec("TRUNCATE TABLE topic_clusters");

// Load all unassigned articles with their feed category
$rows = db()->query("
    SELECT a.id, a.title, COALESCE(f.category, 'misc') AS category
    FROM articles a
    JOIN feeds f ON f.id = a.feed_id
    LEFT JOIN article_topics ato ON ato.article_id = a.id
    WHERE ato.article_id IS NULL
    ORDER BY COALESCE(a.published_at, a.fetched_at) DESC
")->fetchAll(PDO::FETCH_ASSOC);

$N = count($rows);
if ($N < $min_size) {
    cli_log("  Only $N unassigned articles — nothing to cluster.");
    log_action('cluster', 'success', "0 clusters (only $N candidates)");
    return;
}

// Group by category
$by_category = [];
foreach ($rows as $r) $by_category[$r['category']][] = $r;

cli_log("  $N articles across " . count($by_category) . " categories.");

$ins = db()->prepare("INSERT INTO topic_clusters (article_id, cluster_id) VALUES (?, ?)");
db()->beginTransaction();

$cid = 0; $total_clusters = 0; $total_kept = 0; $used_ids = [];

foreach ($by_category as $category => $articles) {
    $n = count($articles);
    if ($n < $min_size) {
        cli_log("  [$category] $n articles — skipping (< $min_size)");
        continue;
    }

    cli_log("  [$category] $n articles — clustering...");
    $lines  = implode("\n", array_map(fn($r) => "ID:{$r['id']} | {$r['title']}", $articles));
    $system = 'You are a news clustering engine. Return only raw JSON — no markdown, no explanation.';
    $prompt = "Group these {$category} news articles into clusters. Articles covering the same event or story belong in the same cluster.\n\n"
            . "Rules:\n"
            . "- Only include clusters of {$min_size} or more articles\n"
            . "- Each article ID appears in at most one cluster\n"
            . "- Skip articles that don't clearly belong to any group\n\n"
            . "Return a JSON array: [{\"ids\":[1,2,3]}, ...]\n\n"
            . "Articles:\n{$lines}";

    $raw     = call_openai_chat($prompt, $system);
    $decoded = json_decode(extract_json($raw), true);

    if (!is_array($decoded)) {
        cli_log("    Invalid JSON — skipping category.");
        log_action('cluster', 'warning', "[$category] invalid JSON response");
        continue;
    }

    $valid_ids    = array_map('intval', array_column($articles, 'id'));
    $cat_clusters = 0;

    foreach ($decoded as $cluster) {
        $ids = array_values(array_filter(
            array_map('intval', $cluster['ids'] ?? []),
            fn($id) => in_array($id, $valid_ids) && !in_array($id, $used_ids)
        ));
        if (count($ids) < $min_size) continue;
        foreach ($ids as $article_id) { $ins->execute([$article_id, $cid]); $used_ids[] = $article_id; }
        $total_kept += count($ids);
        $cat_clusters++;
        $cid++;
    }

    cli_log("    $cat_clusters clusters found.");
    $total_clusters += $cat_clusters;
}

db()->commit();

cli_log("  Total: $total_clusters clusters, $total_kept articles clustered; " . ($N - $total_kept) . " outliers.");
log_action('cluster', 'success', "$total_clusters clusters, $total_kept articles clustered");
cli_log("[Cluster] Done.");
