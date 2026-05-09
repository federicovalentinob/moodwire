<?php

require_once __DIR__ . '/functions.php';

$threshold = defined('CLUSTER_THRESHOLD') ? (float)CLUSTER_THRESHOLD : 0.70;
$min_size  = defined('CLUSTER_MIN_SIZE')  ? (int)CLUSTER_MIN_SIZE  : 3;

db()->exec("CREATE TABLE IF NOT EXISTS topic_clusters (
    article_id INT PRIMARY KEY,
    cluster_id INT NOT NULL,
    KEY (cluster_id),
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Wipe previous run — we re-cluster fresh each time over the current 24h window.
db()->exec("TRUNCATE TABLE topic_clusters");

// Load embeddings for articles NOT yet assigned to a topic.
// (Same convention as old synthesize: only cluster articles without an existing topic.)
$rows = db()->query("
    SELECT a.id, e.vector
    FROM articles a
    JOIN article_embeddings e ON e.article_id = a.id
    LEFT JOIN article_topics at ON at.article_id = a.id
    WHERE at.article_id IS NULL
")->fetchAll(PDO::FETCH_ASSOC);

$N = count($rows);
if ($N < $min_size) {
    cli_log("  Only $N unassigned articles with embeddings — nothing to cluster.");
    log_action('cluster', 'success', "0 clusters (only $N candidates)");
    return;
}

cli_log("  Loading $N vectors and L2-normalizing...");
$ids  = [];
$vecs = [];
foreach ($rows as $r) {
    $v = l2_normalize(unpack_vector($r['vector']));
    $ids[]  = (int)$r['id'];
    $vecs[] = $v;
}
$D = count($vecs[0]);

cli_log("  Pairwise threshold scan @ cosine >= $threshold (D=$D, N=$N)...");
$t0 = microtime(true);

// union-find
$parent = range(0, $N - 1);
$find = function ($i) use (&$parent, &$find) {
    while ($parent[$i] !== $i) { $parent[$i] = $parent[$parent[$i]]; $i = $parent[$i]; }
    return $i;
};
$union = function ($a, $b) use (&$parent, $find) {
    $ra = $find($a); $rb = $find($b);
    if ($ra !== $rb) $parent[$ra] = $rb;
};

$edges = 0;
for ($i = 0; $i < $N; $i++) {
    $vi = $vecs[$i];
    for ($j = $i + 1; $j < $N; $j++) {
        $vj = $vecs[$j];
        $s = 0.0;
        for ($d = 0; $d < $D; $d++) $s += $vi[$d] * $vj[$d];
        if ($s >= $threshold) { $union($i, $j); $edges++; }
    }
}
cli_log("  Pairwise scan in " . round(microtime(true) - $t0, 1) . "s ($edges edges).");

// Group by component
$groups = [];
for ($i = 0; $i < $N; $i++) $groups[$find($i)][] = $i;

// Filter by min_size
$kept = array_filter($groups, fn($g) => count($g) >= $min_size);

// Renumber clusters 0..K-1, save to DB
$ins = db()->prepare("INSERT INTO topic_clusters (article_id, cluster_id) VALUES (?, ?)");
db()->beginTransaction();
$cid = 0;
$kept_articles = 0;
$sizes = [];
foreach ($kept as $members) {
    foreach ($members as $mi) $ins->execute([$ids[$mi], $cid]);
    $sizes[] = count($members);
    $kept_articles += count($members);
    $cid++;
}
db()->commit();

rsort($sizes);
cli_log("  $cid clusters kept (size >= $min_size): " . implode(',', $sizes));
cli_log("  $kept_articles articles in clusters; " . ($N - $kept_articles) . " remain as outliers.");
log_action('cluster', 'success', "$cid clusters, $kept_articles articles clustered");
cli_log("[Cluster] Done.");
