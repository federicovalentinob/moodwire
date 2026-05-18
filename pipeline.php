<?php

// When called via HTTP (cron or browser), require a secret token.
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') {
    if (($_GET['token'] ?? '') !== '7cf291816a035e47049ac7ea') {
        http_response_code(403);
        exit('Forbidden');
    }
}

require_once __DIR__ . '/functions.php';

// Survive client disconnect — fired by admin.php's fire-and-forget self-call.
ignore_user_abort(true);
set_time_limit(0);

// ── Step selection ────────────────────────────────────────────────────────────
// Default: all steps. Pass step names as args to run only those:
//   php pipeline.php embed
//   php pipeline.php fetch embed
//   php pipeline.php cluster label
$all_steps    = ['fetch', 'images', 'normalize', 'cluster', 'label', 'merge'];
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

// Mark articles as processed so cluster/label steps treat them as eligible.
if (array_intersect(['cluster', 'label'], array_keys($run))) {
    db()->exec("UPDATE articles SET processed = 1 WHERE processed = 0");
}

// ── Step 4: Cluster ───────────────────────────────────────────────────────────
if (should_run('cluster', $run)) step('Cluster', 'cluster.php');

// ── Step 5: Label ─────────────────────────────────────────────────────────────
if (should_run('label', $run))   step('Label',   'label.php');

// ── Step 6: Merge ─────────────────────────────────────────────────────────────
if (should_run('merge', $run))   step('Merge',   'merge.php');

log_action('pipeline', 'success', 'Pipeline completed (' . implode('+', array_keys($run)) . ')');
echo "\nPipeline complete.\n";
