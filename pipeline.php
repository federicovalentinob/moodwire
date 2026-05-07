<?php

require_once __DIR__ . '/functions.php';

// ── Step selection ────────────────────────────────────────────────────────────
// Default: all steps. Pass step names as args to run only those:
//   php pipeline.php synthesize
//   php pipeline.php fetch tag
//   php pipeline.php tag synthesize
$all_steps    = ['fetch', 'images', 'normalize', 'tag', 'synthesize'];
$args         = array_slice($argv ?? [], 1);
$wanted       = $args ? array_intersect($all_steps, $args) : $all_steps;
$run          = array_fill_keys($wanted, true);

function should_run(string $step, array $run): bool {
    return isset($run[$step]);
}

function step(string $name, string $script): void {
    echo "\n[{$name}] Starting...\n";
    log_action('pipeline', 'success', "Step {$name} started");
    include __DIR__ . '/' . $script;
    echo "[{$name}] Done.\n";
}

// ── Step 1: Fetch ─────────────────────────────────────────────────────────────
// Skip if last fetch was less than 15 minutes ago (unless explicitly requested)
if (should_run('fetch', $run)) {
    $last = db()->query("SELECT MAX(last_fetched) FROM feeds")->fetchColumn();
    $age  = $last ? (time() - strtotime($last)) : PHP_INT_MAX;
    $explicit = count($args) > 0 && in_array('fetch', $args);
    if (!$explicit && $age < 900) {
        echo "\n[Fetch] Skipped — last fetch was " . round($age/60) . "m ago (< 15m).\n";
    } else {
        step('Fetch', 'fetch.php');
        step('Images', 'fetch_images.php');
        step('Normalize', 'normalize.php');
    }
} else {
    echo "\n[Fetch/Images/Normalize] Skipped.\n";
}

// ── Step 4: Tag ───────────────────────────────────────────────────────────────
if (should_run('tag', $run)) {
    $unprocessed = count_unprocessed_articles();
    if (!$unprocessed) {
        echo "\n[Tag] Skipped — no unprocessed articles.\n";
    } else {
        echo "\n[Tag] Starting...\n";
        $rounds = 0;
        while (count_unprocessed_articles()) {
            include __DIR__ . '/tag.php';
            $rounds++;
            if ($rounds > 20) break;
        }
        echo "[Tag] Done after {$rounds} round(s).\n";
    }
}

// ── Step 5: Synthesize ────────────────────────────────────────────────────────
if (should_run('synthesize', $run)) {
    $unassigned = (int) db()->query('
        SELECT COUNT(*) FROM articles a
        LEFT JOIN article_topics ato ON ato.article_id = a.id
        WHERE a.processed = 1 AND ato.article_id IS NULL
    ')->fetchColumn();

    if (!$unassigned) {
        echo "\n[Synthesize] Skipped — no unassigned articles.\n";
    } else {
        echo "\n[Synthesize] Starting...\n";
        $rounds = 0;
        $prev   = -1;
        while ($rounds < 15) {
            $unassigned = (int) db()->query('
                SELECT COUNT(*) FROM articles a
                LEFT JOIN article_topics ato ON ato.article_id = a.id
                WHERE a.processed = 1 AND ato.article_id IS NULL
            ')->fetchColumn();
            if ($unassigned === 0)      { echo "All articles assigned.\n"; break; }
            if ($unassigned === $prev)  { echo "No progress — stopping.\n"; break; }
            $prev = $unassigned;
            echo "[Synthesize round " . ($rounds + 1) . "] {$unassigned} unassigned...\n";
            include __DIR__ . '/synthesize.php';
            $rounds++;
        }
        echo "[Synthesize] Done after {$rounds} round(s).\n";
    }
}

log_action('pipeline', 'success', 'Pipeline completed (' . implode('+', array_keys($run)) . ')');
echo "\nPipeline complete.\n";
