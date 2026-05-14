<?php

require_once __DIR__ . '/functions.php';

$total_unassigned = (int) db()->query('
    SELECT COUNT(*) FROM articles a
    LEFT JOIN article_topics ato ON ato.article_id = a.id
    WHERE a.processed = 1 AND ato.article_id IS NULL
')->fetchColumn();

if (!$total_unassigned) {
    $msg = 'No unassigned articles. Nothing to synthesize.';
    log_action('synthesize', 'success', $msg);
    cli_log($msg);
    exit;
}

// Unflag all existing topics — only topics created in this run will be flagged as new
db()->exec('UPDATE topics SET is_new = 0');

// ── Tag normalization — collapse synonyms into one canonical form ─────────────
if (!defined('TAG_ALIASES')) {
    define('TAG_ALIASES', [
        'elections'               => 'election',
        'artificial intelligence' => 'ai',
        'artificial-intelligence' => 'ai',
        'tech'                    => 'technology',
        'covid-19'                => 'covid',
        'coronavirus'             => 'covid',
        'cryptocurrency'          => 'crypto',
        'soccer'                  => 'football',
        'us'                      => 'usa',
        'united states'           => 'usa',
        'donald trump'            => 'trump',
    ]);
}

if (!function_exists('normalize_tag')) {
    function normalize_tag(string $tag): string {
        return TAG_ALIASES[$tag] ?? $tag;
    }

    // All raw tags that map to the same canonical (for use in SQL IN clauses)
    function tag_variants(string $canonical): array {
        $variants = [$canonical];
        foreach (TAG_ALIASES as $alias => $norm) {
            if ($norm === $canonical) $variants[] = $alias;
        }
        return array_unique($variants);
    }
}

cli_log("{$total_unassigned} unassigned articles. Grouping by top tags...\n");

// ── Top tags: fetch raw, normalize synonyms, merge counts, keep top 25 ───────
$raw_tags = db()->query("
    SELECT at2.tag, COUNT(DISTINCT a.id) as c
    FROM article_tags at2
    JOIN articles a ON a.id = at2.article_id
    LEFT JOIN article_topics ato ON ato.article_id = a.id
    WHERE a.processed = 1 AND ato.article_id IS NULL
      AND at2.tag NOT LIKE 'country:%'
    GROUP BY at2.tag
    HAVING c >= 5
    ORDER BY c DESC
")->fetchAll();

$merged = [];
foreach ($raw_tags as $row) {
    $canonical = normalize_tag($row['tag']);
    $merged[$canonical] = ($merged[$canonical] ?? 0) + (int)$row['c'];
}
arsort($merged);
$tag_groups = array_slice(
    array_map(fn($tag, $c) => ['tag' => $tag, 'c' => $c], array_keys($merged), $merged),
    0, 25
);

// ── Helpers ───────────────────────────────────────────────────────────────────
if (!function_exists('build_article_lines')) {
function build_article_lines(array $articles): string {
    return implode("\n\n", array_map(function($a) {
        $excerpt = trim(preg_replace('/\s+/', ' ', strip_tags($a['raw_content'] ?? '')));
        $excerpt = $excerpt ? substr($excerpt, 0, 200) : '';
        $line    = "ID:{$a['id']} | {$a['title']}";
        if ($excerpt) $line .= "\n  " . $excerpt;
        return $line;
    }, $articles));
}

// Titles only — used for clustering (much smaller tokens, allows large batches)
function build_title_lines(array $articles): string {
    return implode("\n", array_map(
        fn($a) => "ID:{$a['id']} | {$a['title']}",
        $articles
    ));
}

function existing_topics_lines(): string {
    $topics = db()->query('SELECT id, title FROM topics ORDER BY created_at DESC LIMIT 200')->fetchAll();
    return empty($topics) ? 'None'
        : implode("\n", array_map(fn($t) => "ID:{$t['id']} | {$t['title']}", $topics));
}

function quoted_in(array $values): string {
    return implode(',', array_map(fn($v) => db()->quote($v), $values));
}

function fetch_tag_articles(string $tag1, ?string $tag2 = null, array $exclude_ids = []): array {
    $v1          = quoted_in(tag_variants($tag1));
    $exclude_sql = $exclude_ids
        ? 'AND a.id NOT IN (' . implode(',', array_map('intval', $exclude_ids)) . ')'
        : '';
    $tag2_sql    = $tag2
        ? 'AND EXISTS (SELECT 1 FROM article_tags x WHERE x.article_id = a.id AND x.tag IN (' . quoted_in(tag_variants($tag2)) . '))'
        : '';
    return db()->query("
        SELECT a.id, a.title, a.raw_content
        FROM articles a
        JOIN article_tags at2 ON at2.article_id = a.id
        LEFT JOIN article_topics ato ON ato.article_id = a.id
        WHERE a.processed = 1 AND ato.article_id IS NULL
          AND at2.tag IN ({$v1})
          {$tag2_sql} {$exclude_sql}
        GROUP BY a.id
        ORDER BY COALESCE(a.published_at, a.fetched_at) DESC
    ")->fetchAll();
}

function get_subtags(string $tag1, array $exclude_ids = []): array {
    $v1          = quoted_in(tag_variants($tag1));
    $exclude_sql = $exclude_ids
        ? 'AND a.id NOT IN (' . implode(',', array_map('intval', $exclude_ids)) . ')'
        : '';
    // Fetch raw co-occurring tags, then normalize + merge in PHP
    $rows = db()->query("
        SELECT at2.tag, COUNT(DISTINCT a.id) as c
        FROM article_tags at2
        JOIN articles a ON a.id = at2.article_id
        JOIN article_tags at3 ON at3.article_id = a.id AND at3.tag IN ({$v1})
        LEFT JOIN article_topics ato ON ato.article_id = a.id
        WHERE a.processed = 1 AND ato.article_id IS NULL
          AND at2.tag NOT IN ({$v1})
          AND at2.tag NOT LIKE 'country:%'
          {$exclude_sql}
        GROUP BY at2.tag
        HAVING c >= 5
        ORDER BY c DESC
    ")->fetchAll();

    $merged = [];
    foreach ($rows as $row) {
        $canonical = normalize_tag($row['tag']);
        if (in_array($canonical, tag_variants($tag1))) continue; // skip parent tag variants
        $merged[$canonical] = ($merged[$canonical] ?? 0) + (int)$row['c'];
    }
    arsort($merged);
    return array_slice(
        array_map(fn($t, $c) => ['tag' => $t, 'c' => $c], array_keys($merged), $merged),
        0, 8
    );
}

} // end if(!function_exists('build_article_lines'))

// ── Cluster + bullets for one batch ──────────────────────────────────────────
$cluster_prompt_tpl = get_prompt('cluster');
$total_new = $total_existing = 0;

if (!function_exists('save_clusters')) {
function save_clusters(array $clusters, array $article_ids): array {
    $new_topics_pending = [];
    $saved_new = 0;

    // Words that indicate a truncated title if they appear at the end
    static $trailing = ['for','of','the','a','an','in','on','at','to','with','from',
                        'by','and','or','but','after','before','over','under','about',
                        'as','into','through','during','including','until','against',
                        'between','without','within','along','following','across','amid'];

    // Topics are immutable: every cluster becomes a new topic. Any topic_id from the AI is ignored.
    foreach ($clusters as $cluster) {
        $title        = trim($cluster['title'] ?? '');
        $cluster_ids  = $cluster['ids']      ?? [];
        $content_type = $cluster['type']     ?? 'informative';
        $category     = $cluster['category'] ?? null;

        if (empty($title)) { continue; }
        $last_word = strtolower(preg_replace('/[^a-z]/i', '', substr(strrchr(' '.$title, ' '), 1)));
        if (in_array($last_word, $trailing)) {
            cli_log("      ✕ Skipped truncated title: \"{$title}\"");
            continue;
        }

        if (empty($cluster_ids)) continue;
        $valid_ids = array_values(array_filter($cluster_ids, fn($id) => in_array((int)$id, $article_ids)));
        if (count($valid_ids) < 3) {
            if (!empty($valid_ids)) cli_log("      ✕ Dropped [{$title}] — only " . count($valid_ids) . " article(s)");
            continue;
        }

        $ids_str     = implode(',', array_map('intval', $valid_ids));
        $anxiety_avg = (float) db()->query(
            "SELECT COALESCE(AVG(score), 5) FROM article_indexes WHERE index_name='anxiety' AND article_id IN ({$ids_str})"
        )->fetchColumn();

        $art_titles = db()->query("SELECT title FROM articles WHERE id IN ({$ids_str})")->fetchAll(PDO::FETCH_COLUMN);
        $new_topics_pending[] = [
            'title'        => $title,
            'ids'          => $valid_ids,
            'anxiety_avg'  => $anxiety_avg,
            'content_type' => $content_type,
            'category'     => $category,
            'art_titles'   => $art_titles,
            'max_b'        => min(5, max(2, count($valid_ids))),
        ];
        cli_log("      + [{$title}]");
        $saved_new++;
    }

    // Batch bullets for new topics
    if (!empty($new_topics_pending)) {
        $topics_str = '';
        foreach ($new_topics_pending as $i => $t) {
            $art_list    = implode("\n", array_map(fn($a) => "- {$a}", $t['art_titles']));
            $topics_str .= "TOPIC_ID:{$i} \"{$t['title']}\" ({$t['max_b']} bullets)\n{$art_list}\n\n";
        }
        $batch_prompt = get_prompt('bullets_batch');
        $batch_prompt = str_replace('{{topics}}', $topics_str, $batch_prompt);
        $raw_batch    = call_openai_chat($batch_prompt);
        $batch_result = json_decode(extract_json($raw_batch), true);
        $bullets_by_idx = [];
        if (is_array($batch_result)) {
            foreach ($batch_result as $r) {
                if (isset($r['id'], $r['bullets'])) $bullets_by_idx[(int)$r['id']] = $r['bullets'];
            }
        }
        foreach ($new_topics_pending as $i => $t) {
            $bullets = $bullets_by_idx[$i] ?? ["No summary available."];
            $bullets = array_map('trim', array_slice(dedupe_bullets($bullets), 0, $t['max_b']));
            $new_id  = save_topic($t['title'], $bullets, $t['anxiety_avg'], $t['ids'], $t['content_type'], $t['category']);
            update_topic_geo($new_id);
            cli_log("      ✓ [{$new_id}] {$t['title']}");
            foreach ($bullets as $b) cli_log("        • {$b}");
        }
    }

    return [$saved_new, 0];
}

function run_batch(array $articles, string $tag): void {
    global $cluster_prompt_tpl, $total_new, $total_existing;

    $title_lines = build_title_lines($articles);   // titles only for clustering
    // Topics are immutable — we don't pass existing topics to the AI.
    $prompt = str_replace(
        ['{{existing_topics}}', '{{articles}}', '{{category}}'],
        ['(none — always create new topics)', $title_lines, $tag],
        $cluster_prompt_tpl
    );

    $raw      = call_openai_chat($prompt);
    $clusters = json_decode(extract_json($raw), true);

    if (!$clusters || !is_array($clusters) || empty($clusters)) {
        cli_log("    No clusters found.");
        return;
    }

    cli_log("    Got " . count($clusters) . " clusters:");
    [$n, $e] = save_clusters($clusters, array_column($articles, 'id'));
    $total_new      += $n;
    $total_existing += $e;
}
} // end if(!function_exists('save_clusters'))

// ── Process each tag group ────────────────────────────────────────────────────
$seen_ids = [];

foreach ($tag_groups as $group) {
    $tag1  = $group['tag'];
    $count = (int)$group['c'];
    cli_log("\n[{$tag1}] {$count} articles");

    if ($count <= 50) {
        // Small group — one call
        $articles = fetch_tag_articles($tag1, null, $seen_ids);
        if (count($articles) >= 3) {
            run_batch($articles, $tag1);
            foreach ($articles as $a) $seen_ids[] = (int)$a['id'];
        }
    } else {
        // Large group — split by top co-occurring tags
        $subtags      = get_subtags($tag1, $seen_ids);
        $local_seen   = [];

        foreach ($subtags as $sub) {
            $tag2     = $sub['tag'];
            $articles = fetch_tag_articles($tag1, $tag2, array_merge($seen_ids, $local_seen));
            if (count($articles) < 3) continue;

            cli_log("  [{$tag1} + {$tag2}] " . count($articles) . " articles");
            run_batch($articles, "{$tag1} + {$tag2}");
            foreach ($articles as $a) $local_seen[] = (int)$a['id'];
        }

        // Remaining articles with tag1 not caught by any sub-tag
        $articles = fetch_tag_articles($tag1, null, array_merge($seen_ids, $local_seen));
        if (count($articles) >= 3) {
            cli_log("  [{$tag1} / other] " . count($articles) . " articles");
            run_batch($articles, $tag1);
            foreach ($articles as $a) $local_seen[] = (int)$a['id'];
        }

        $seen_ids = array_merge($seen_ids, $local_seen);
    }
}

// ── Final pass: remaining articles not covered by any top tag ─────────────────
$remaining = db()->query("
    SELECT a.id, a.title, a.raw_content
    FROM articles a
    LEFT JOIN article_topics ato ON ato.article_id = a.id
    WHERE a.processed = 1 AND ato.article_id IS NULL
    ORDER BY COALESCE(a.published_at, a.fetched_at) DESC
")->fetchAll();

if (count($remaining) >= 3) {
    cli_log("\n[misc] " . count($remaining) . " remaining articles");
    run_batch($remaining, 'misc');
}

$msg = "{$total_new} new topics, {$total_existing} updated across " . count($tag_groups) . " tag groups";
log_action('synthesize', 'success', $msg);
cli_log("\nDone: {$msg}");
