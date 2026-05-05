<?php
require_once __DIR__ . '/functions.php';

session_start();
if (!isset($_SESSION['liked']))    $_SESSION['liked']    = []; // {tag => count}
if (!isset($_SESSION['disliked'])) $_SESSION['disliked'] = []; // {tag => count}
if (!isset($_SESSION['signaled']))       $_SESSION['signaled']       = [];
if (!isset($_SESSION['anxiety_total']))  $_SESSION['anxiety_total']  = 0.0;
if (!isset($_SESSION['anxiety_count']))  $_SESSION['anxiety_count']  = 0;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function json_out(mixed $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function err(string $msg, int $code = 400): void {
    http_response_code($code);
    json_out(['error' => $msg]);
}

switch ($action) {

// ── Topics ───────────────────────────────────────────────────────────────────
case 'topics':
    $anxiety  = $_GET['anxiety']  ?? '';
    $category = $_GET['category'] ?? '';
    $type     = $_GET['type']     ?? '';

    $where = ['1=1'];
    if ($anxiety === 'low')    $where[] = 'anxiety_avg <= 3';
    if ($anxiety === 'medium') $where[] = 'anxiety_avg > 3 AND anxiety_avg <= 6';
    if ($anxiety === 'high')   $where[] = 'anxiety_avg > 6';
    if ($category)             $where[] = 'category = ' . db()->quote($category);
    if ($type)                 $where[] = 'content_type = ' . db()->quote($type);

    $limit  = max(1, min(20, (int)($_GET['limit']  ?? 5)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));

    // Fetch all candidate topics ordered by default sort
    $sql_all = 'SELECT t.*,
                       MAX(COALESCE(a.published_at, a.fetched_at)) as latest_article_at
                FROM topics t
                LEFT JOIN article_topics ato ON ato.topic_id = t.id
                LEFT JOIN articles a ON a.id = ato.article_id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY t.id
                HAVING COUNT(ato.article_id) > 0
                ORDER BY t.anxiety_avg DESC, latest_article_at DESC';

    $all_topics = db()->query($sql_all)->fetchAll();
    $total      = count($all_topics);

    // Exclude already-shown topic IDs passed from client
    $exclude = [];
    if (!empty($_GET['exclude'])) {
        $exclude = array_map('intval', explode(',', $_GET['exclude']));
    }
    $all_topics = array_values(array_filter($all_topics, fn($t) => !in_array((int)$t['id'], $exclude)));
    $total = count($all_topics);

    // Slice already-seen topics
    $remaining = array_slice($all_topics, $offset);

    // Personalization: score topics by tag overlap (only if preferences exist)
    $liked    = $_SESSION['liked']    ?? [];
    $disliked = $_SESSION['disliked'] ?? [];
    $has_prefs = !empty($liked) || !empty($disliked);

    $mode = $_GET['mode'] ?? 'mixed'; // 'mixed', 'personalized', 'random'

    if ($has_prefs && $mode !== 'random') {
        // Get tags for each remaining topic
        $ids = array_column($remaining, 'id');
        $ids_str = implode(',', array_map('intval', $ids));
        $tag_rows = db()->query("
            SELECT ato.topic_id, at2.tag
            FROM article_tags at2
            JOIN article_topics ato ON ato.article_id = at2.article_id
            WHERE ato.topic_id IN ({$ids_str}) AND at2.tag NOT LIKE 'country:%'
        ")->fetchAll();

        $topic_tags = [];
        foreach ($tag_rows as $row) $topic_tags[$row['topic_id']][] = $row['tag'];

        // Score each topic
        foreach ($remaining as &$t) {
            $tags  = $topic_tags[$t['id']] ?? [];
            $score = 0;
            foreach ($tags as $tag) {
                $score += ($liked[$tag]    ?? 0);
                $score -= ($disliked[$tag] ?? 0) * 0.8;
            }
            // Also score by category/type
            $score += ($liked[strtolower($t['category'] ?? '')] ?? 0);
            $score -= ($disliked[strtolower($t['category'] ?? '')] ?? 0) * 0.8;
            $t['_score'] = $score;
        }
        unset($t);

        // Split: 50% personalized (by score desc), 50% default order
        $personal = $remaining;
        usort($personal, fn($a,$b) => $b['_score'] <=> $a['_score']);

        $half_p = (int)ceil($limit / 2);
        $half_d = $limit - $half_p;

        $personal_picks = array_slice($personal, 0, $half_p);
        $personal_ids   = array_column($personal_picks, 'id');

        $default_picks  = array_filter($remaining, fn($t) => !in_array($t['id'], $personal_ids));
        $default_picks  = array_values(array_slice($default_picks, 0, $half_d));

        // Interleave: p, d, p, d ...
        $page = [];
        $pi = 0; $di = 0;
        for ($i = 0; $i < $limit; $i++) {
            if ($i % 2 === 0 && isset($personal_picks[$pi])) $page[] = $personal_picks[$pi++];
            elseif (isset($default_picks[$di]))               $page[] = $default_picks[$di++];
            elseif (isset($personal_picks[$pi]))              $page[] = $personal_picks[$pi++];
        }
    } elseif ($mode === 'random') {
        // Pure random from remaining
        shuffle($remaining);
        $page = array_slice($remaining, 0, $limit);
    } else {
        $page = array_slice($remaining, 0, $limit);
    }

    $topics = $page;

    foreach ($topics as &$topic) {
        // Bullets
        $st = db()->prepare('SELECT bullet FROM topic_bullets WHERE topic_id = ? ORDER BY display_order');
        $st->execute([$topic['id']]);
        $topic['bullets'] = $st->fetchAll(PDO::FETCH_COLUMN);

        // Top 7 articles by recency
        $st = db()->prepare('
            SELECT a.id, a.title, a.url, a.image_path, a.published_at,
                   f.name as feed_name,
                   GROUP_CONCAT(DISTINCT at2.tag) as tags,
                   ai.score as anxiety
            FROM articles a
            JOIN article_topics ato ON ato.article_id = a.id
            JOIN feeds f ON f.id = a.feed_id
            LEFT JOIN article_tags at2 ON at2.article_id = a.id
            LEFT JOIN article_indexes ai ON ai.article_id = a.id AND ai.index_name = "anxiety"
            WHERE ato.topic_id = ?
            GROUP BY a.id
            ORDER BY COALESCE(a.published_at, a.fetched_at) DESC
            LIMIT 7
        ');
        $st->execute([$topic['id']]);
        $topic['articles'] = $st->fetchAll();

        $topic['anxiety_avg']  = (float)$topic['anxiety_avg'];
        $topic['anxiety_label']= $topic['anxiety_avg'] >= 7 ? 'High' : ($topic['anxiety_avg'] >= 4 ? 'Medium' : 'Low');
        $topic['anxiety_color']= $topic['anxiety_avg'] >= 7 ? '#dc2626' : ($topic['anxiety_avg'] >= 4 ? '#d97706' : '#16a34a');
        $topic['time_ago']     = time_ago($topic['latest_article_at']);
    }
    json_out(['topics' => $topics, 'total' => (int)$total, 'offset' => $offset, 'limit' => $limit]);

// ── Stats ─────────────────────────────────────────────────────────────────────
case 'stats':
    $row = db()->query("
        SELECT COUNT(*) as topics,
               SUM(anxiety_avg <= 3) as low,
               SUM(anxiety_avg > 3 AND anxiety_avg <= 6) as medium,
               SUM(anxiety_avg > 6) as high
        FROM topics
    ")->fetch();
    $row['articles']   = db()->query('SELECT COUNT(*) FROM articles')->fetchColumn();
    $row['categories'] = db()->query("SELECT category, COUNT(*) as c FROM topics WHERE category IS NOT NULL GROUP BY category ORDER BY c DESC")->fetchAll();
    json_out($row);

// ── Swipe ─────────────────────────────────────────────────────────────────
case 'swipe':
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $topic_id  = (int)($body['topic_id'] ?? 0);
    $direction = $body['direction'] ?? ''; // 'right' or 'left'

    if (!$topic_id || !in_array($direction, ['right','left'])) err('Invalid swipe');

    // Build a signal key to deduplicate
    $signal_key = !empty($body['tags'])
        ? 'article:' . md5(implode(',', (array)$body['tags']))
        : 'topic:' . $topic_id . ':' . $direction;

    $source  = $body['source'] ?? 'swipe'; // 'tap', 'click', or 'swipe'
    $anxiety = isset($body['anxiety']) ? (float)$body['anxiety'] : null;

    if (in_array($signal_key, $_SESSION['signaled'])) {
        json_out(['ok' => true, 'skipped' => true, 'liked' => $_SESSION['liked'], 'disliked' => $_SESSION['disliked'],
                  'anxiety_avg' => $_SESSION['anxiety_count'] > 0 ? round($_SESSION['anxiety_total'] / $_SESSION['anxiety_count'], 2) : null,
                  'anxiety_count' => $_SESSION['anxiety_count']]);
    }
    $_SESSION['signaled'][] = $signal_key;

    // Track anxiety exposure for tap and click (not swipe)
    if (in_array($source, ['tap', 'click']) && $anxiety !== null) {
        $_SESSION['anxiety_total'] += $anxiety;
        $_SESSION['anxiety_count']++;
    }

    // Accept either a topic_id or a raw tags array
    if (!empty($body['tags']) && is_array($body['tags'])) {
        $tags = array_filter(array_map('trim', $body['tags']));
    } else {
        // Collect tags from topic
        $tags = db()->query("
            SELECT DISTINCT at2.tag
            FROM article_tags at2
            JOIN article_topics ato ON ato.article_id = at2.article_id
            WHERE ato.topic_id = {$topic_id}
              AND at2.tag NOT LIKE 'country:%'
            LIMIT 20
        ")->fetchAll(PDO::FETCH_COLUMN);
        $meta = db()->query("SELECT category, content_type FROM topics WHERE id = {$topic_id}")->fetch();
        if ($meta['category'])     $tags[] = strtolower($meta['category']);
        if ($meta['content_type']) $tags[] = $meta['content_type'];
    }

    $list = $direction === 'right' ? 'liked' : 'disliked';

    foreach ($tags as $tag) {
        $tag = trim(strtolower($tag));
        if (!$tag) continue;
        $_SESSION[$list][$tag] = ($_SESSION[$list][$tag] ?? 0) + 1;
    }

    // Simplify: where a tag exists on both sides, subtract the minimum from both
    $all_tags = array_unique(array_merge(
        array_keys($_SESSION['liked']),
        array_keys($_SESSION['disliked'])
    ));
    foreach ($all_tags as $tag) {
        $l = $_SESSION['liked'][$tag]    ?? 0;
        $d = $_SESSION['disliked'][$tag] ?? 0;
        if ($l > 0 && $d > 0) {
            $min = min($l, $d);
            $_SESSION['liked'][$tag]    = $l - $min;
            $_SESSION['disliked'][$tag] = $d - $min;
        }
        if (($_SESSION['liked'][$tag]    ?? 0) === 0) unset($_SESSION['liked'][$tag]);
        if (($_SESSION['disliked'][$tag] ?? 0) === 0) unset($_SESSION['disliked'][$tag]);
    }

    // Sort by count desc, keep top 30
    arsort($_SESSION['liked']);
    arsort($_SESSION['disliked']);
    $_SESSION['liked']    = array_slice($_SESSION['liked'],    0, 30, true);
    $_SESSION['disliked'] = array_slice($_SESSION['disliked'], 0, 30, true);

    json_out(['ok' => true, 'liked' => $_SESSION['liked'], 'disliked' => $_SESSION['disliked'],
              'anxiety_avg'   => $_SESSION['anxiety_count'] > 0 ? round($_SESSION['anxiety_total'] / $_SESSION['anxiety_count'], 2) : null,
              'anxiety_count' => $_SESSION['anxiety_count']]);

// ── Preferences ───────────────────────────────────────────────────────────
case 'preferences':
    json_out(['liked' => $_SESSION['liked'], 'disliked' => $_SESSION['disliked'],
              'anxiety_avg'   => $_SESSION['anxiety_count'] > 0 ? round($_SESSION['anxiety_total'] / $_SESSION['anxiety_count'], 2) : null,
              'anxiety_count' => $_SESSION['anxiety_count']]);

// ── Clear preferences ─────────────────────────────────────────────────────
case 'remove_tag':
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $tag  = trim(strtolower($body['tag'] ?? ''));
    $list = $body['list'] ?? ''; // 'liked' or 'disliked'
    if ($tag && in_array($list, ['liked','disliked'])) {
        unset($_SESSION[$list][$tag]);
    }
    json_out(['liked' => $_SESSION['liked'], 'disliked' => $_SESSION['disliked'],
              'anxiety_avg'   => $_SESSION['anxiety_count'] > 0 ? round($_SESSION['anxiety_total'] / $_SESSION['anxiety_count'], 2) : null,
              'anxiety_count' => $_SESSION['anxiety_count']]);

case 'clear_preferences':
    $_SESSION['liked'] = $_SESSION['disliked'] = $_SESSION['signaled'] = [];
    $_SESSION['anxiety_total'] = 0.0;
    $_SESSION['anxiety_count'] = 0;
    json_out(['ok' => true]);

default:
    err('Unknown action');
}
