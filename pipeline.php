<?php

require_once __DIR__ . '/functions.php';

function step(string $name, string $script): void {
    echo "\n[{$name}] Starting...\n";
    log_action('pipeline', 'success', "Step {$name} started");
    include __DIR__ . '/' . $script;
    echo "[{$name}] Done.\n";
}

// Step 1: Fetch
step('Fetch', 'fetch.php');

// Step 2: Normalize
step('Normalize', 'normalize.php');

// Step 3: Tag — loop until all articles are tagged
echo "\n[Tag] Starting...\n";
$rounds = 0;
while (count_unprocessed_articles()) {
    include __DIR__ . '/tag.php';
    $rounds++;
    if ($rounds > 20) break; // safety limit
}
echo "[Tag] Done after {$rounds} round(s).\n";

// Step 4: Synthesize — loop until all tagged articles are assigned
echo "\n[Synthesize] Starting...\n";
$rounds = 0;
while (true) {
    $unassigned = db()->query('
        SELECT COUNT(*) FROM articles a
        LEFT JOIN article_topics ato ON ato.article_id = a.id
        WHERE a.processed = 1 AND ato.article_id IS NULL
    ')->fetchColumn();

    if (!$unassigned) break;

    include __DIR__ . '/synthesize.php';
    $rounds++;
    if ($rounds > 20) break; // safety limit
}
echo "[Synthesize] Done after {$rounds} round(s).\n";

log_action('pipeline', 'success', 'Full pipeline completed');
echo "\nPipeline complete.\n";
