<?php

require_once __DIR__ . '/functions.php';

ignore_user_abort(true);
set_time_limit(0);

const EMBED_BATCH_SIZE = 100;

db()->exec("CREATE TABLE IF NOT EXISTS article_embeddings (
    article_id INT PRIMARY KEY,
    vector LONGBLOB NOT NULL,
    embedded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Articles with cleaned content but no vector yet
$rows = db()->query("
    SELECT a.id, a.title, COALESCE(SUBSTRING(a.clean_content,1,500), '') AS body
    FROM articles a
    LEFT JOIN article_embeddings e ON e.article_id = a.id
    WHERE a.processed = 1 AND e.article_id IS NULL
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    cli_log("  Nothing to embed.");
    log_action('embed', 'success', '0 articles embedded (all up to date)');
    return;
}

cli_log("  " . count($rows) . " articles to embed");

$insert  = db()->prepare("INSERT INTO article_embeddings (article_id, vector) VALUES (?, ?)");
$batches = array_chunk($rows, EMBED_BATCH_SIZE);
$total_in = 0;
$total_articles = 0;

foreach ($batches as $i => $batch) {
    $inputs = array_map(fn($a) => trim($a['title'] . "\n\n" . $a['body']), $batch);
    $data   = call_openai_embeddings($inputs);

    if (count($data) !== count($batch)) {
        cli_log("    batch " . ($i+1) . " — got " . count($data) . " back, expected " . count($batch) . " — skipping");
        continue;
    }

    db()->beginTransaction();
    foreach ($data as $j => $emb) {
        $insert->execute([$batch[$j]['id'], pack_vector($emb['embedding'])]);
    }
    db()->commit();

    $tokens = $data[0]['_usage_proxy'] ?? 0; // placeholder; real usage is global per call
    $total_articles += count($batch);
    cli_log("    batch " . ($i+1) . "/" . count($batches) . " — +" . count($batch) . " articles");
}

log_action('embed', 'success', "$total_articles articles embedded");
cli_log("[Embed] Done. $total_articles new embeddings.");
