<?php
require_once __DIR__ . '/functions.php';

session_start();
if (!isset($_SESSION['prefs']))         $_SESSION['prefs']         = []; // {tag => signed int}
if (!isset($_SESSION['countries']))     $_SESSION['countries']     = []; // {CC => signed int}
if (!isset($_SESSION['signaled']))       $_SESSION['signaled']       = [];
if (!isset($_SESSION['anxiety_window'])) $_SESSION['anxiety_window'] = []; // last N anxieties (FIFO)

const ANXIETY_WINDOW_SIZE = 20;

function anx_push(float $v): void {
    $_SESSION['anxiety_window'][] = $v;
    if (count($_SESSION['anxiety_window']) > ANXIETY_WINDOW_SIZE) {
        $_SESSION['anxiety_window'] = array_slice($_SESSION['anxiety_window'], -ANXIETY_WINDOW_SIZE);
    }
}
function anx_avg(): ?float {
    $w = $_SESSION['anxiety_window'] ?? [];
    return $w ? round(array_sum($w) / count($w), 2) : null;
}
function anx_count(): int {
    return count($_SESSION['anxiety_window'] ?? []);
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function prefs(): array { return $_SESSION['prefs'] ?? []; }

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

    $where = ['1=1'];
    if ($anxiety === 'low')    $where[] = 'anxiety_avg <= 3';
    if ($anxiety === 'medium') $where[] = 'anxiety_avg > 3 AND anxiety_avg <= 6';
    if ($anxiety === 'high')   $where[] = 'anxiety_avg > 6';
    if ($category)             $where[] = 'category = ' . db()->quote($category);

    $limit = max(1, min(20, (int)($_GET['limit'] ?? 5)));

    $sql_all = 'SELECT t.*,
                       MAX(COALESCE(a.published_at, a.fetched_at)) as latest_article_at
                FROM topics t
                LEFT JOIN article_topics ato ON ato.topic_id = t.id
                LEFT JOIN articles a ON a.id = ato.article_id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY t.id
                HAVING COUNT(ato.article_id) > 0
                ORDER BY latest_article_at DESC, t.id DESC';

    $all_topics = db()->query($sql_all)->fetchAll();

    $exclude = [];
    if (!empty($_GET['exclude'])) {
        $exclude = array_map('intval', explode(',', $_GET['exclude']));
    }
    $remaining = array_values(array_filter($all_topics, fn($t) => !in_array((int)$t['id'], $exclude)));

    // Personalization
    $liked     = prefs();
    $has_prefs = !empty($liked);
    $mode      = $_GET['mode'] ?? 'mixed';

    if ($has_prefs && $mode !== 'random') {
        $ids     = array_column($remaining, 'id');
        $ids_str = implode(',', array_map('intval', $ids));
        $tag_rows = db()->query("
            SELECT ato.topic_id, at2.tag
            FROM article_tags at2
            JOIN article_topics ato ON ato.article_id = at2.article_id
            WHERE ato.topic_id IN ({$ids_str})")->fetchAll();

        $topic_tags = [];
        foreach ($tag_rows as $row) $topic_tags[$row['topic_id']][] = $row['tag'];

        foreach ($remaining as &$t) {
            $score = 0;
            foreach ($topic_tags[$t['id']] ?? [] as $tag) {
                if (str_starts_with($tag, 'country:')) {
                    $cc = strtoupper(substr($tag, 8));
                    $score += ($_SESSION['countries'][$cc] ?? 0) * 0.6;
                } else {
                    $score += ($liked[$tag] ?? 0);
                }
            }
            $score += ($liked[strtolower($t['category'] ?? '')] ?? 0);
            $t['_score'] = $score;
        }
        unset($t);

        $personal = $remaining;
        usort($personal, fn($a,$b) => $b['_score'] <=> $a['_score']);

        $half_p = (int)ceil($limit / 2);
        $half_d = $limit - $half_p;

        $personal_picks = array_map(fn($t) => array_merge($t, ['personalized' => true]),
                          array_slice($personal, 0, $half_p));
        $personal_ids   = array_column($personal_picks, 'id');

        $default_picks  = array_map(fn($t) => array_merge($t, ['personalized' => false]),
                          array_values(array_slice(
                              array_filter($remaining, fn($t) => !in_array($t['id'], $personal_ids)),
                          0, $half_d)));

        $page = []; $pi = 0; $di = 0;
        for ($i = 0; $i < $limit; $i++) {
            if ($i % 2 === 0 && isset($personal_picks[$pi])) $page[] = $personal_picks[$pi++];
            elseif (isset($default_picks[$di]))               $page[] = $default_picks[$di++];
            elseif (isset($personal_picks[$pi]))              $page[] = $personal_picks[$pi++];
        }
    } elseif ($mode === 'random') {
        shuffle($remaining);
        $page = array_slice($remaining, 0, $limit);
    } else {
        $page = array_slice($remaining, 0, $limit);
    }

    foreach ($page as &$topic) {
        $st = db()->prepare('SELECT bullet FROM topic_bullets WHERE topic_id = ? ORDER BY display_order');
        $st->execute([$topic['id']]);
        $topic['bullets'] = $st->fetchAll(PDO::FETCH_COLUMN);

        $st = db()->prepare('
            SELECT a.id, a.title, a.url, a.image_path,
                   COALESCE(a.published_at, a.fetched_at) as article_date,
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
        $topic['articles'] = array_map(function($a) {
            $a['time_ago'] = time_ago($a['article_date']);
            return $a;
        }, $st->fetchAll());

        $topic['anxiety_avg']   = (float)$topic['anxiety_avg'];
        $topic['anxiety_label'] = $topic['anxiety_avg'] >= 7 ? 'High' : ($topic['anxiety_avg'] >= 4 ? 'Medium' : 'Low');
        $topic['anxiety_color'] = $topic['anxiety_avg'] >= 7 ? '#dc2626' : ($topic['anxiety_avg'] >= 4 ? '#d97706' : '#16a34a');
        $topic['time_ago']      = time_ago($topic['latest_article_at']);
    }
    $current_anxiety_avg = count($all_topics)
        ? round(array_sum(array_column($all_topics, 'anxiety_avg')) / count($all_topics), 2)
        : null;
    json_out([
        'topics'              => $page,
        'total'               => count($all_topics),
        'offset'              => 0,
        'limit'               => $limit,
        'current_anxiety_avg' => $current_anxiety_avg,
    ]);

// ── Stats ─────────────────────────────────────────────────────────────────────
case 'stats':
    $row = db()->query("
        SELECT COUNT(*) as topics,
               SUM(anxiety_avg <= 3) as low,
               SUM(anxiety_avg > 3 AND anxiety_avg <= 6) as medium,
               SUM(anxiety_avg > 6) as high,
               ROUND(AVG(anxiety_avg), 2) as global_anxiety_avg
        FROM topics
    ")->fetch();
    $row['articles']     = db()->query('SELECT COUNT(*) FROM articles')->fetchColumn();
    $row['categories']   = db()->query("SELECT category, COUNT(*) as c FROM topics WHERE category IS NOT NULL GROUP BY category ORDER BY c DESC")->fetchAll();
    $last_fetched_utc = db()->query('SELECT MAX(fetched_at) FROM articles')->fetchColumn();
    $row['last_updated'] = $last_fetched_utc ? gmdate('Y-m-d\TH:i:s\Z', strtotime($last_fetched_utc . ' UTC')) : null;
    json_out($row);

// ── Swipe ─────────────────────────────────────────────────────────────────────
case 'swipe':
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $topic_id  = (int)($body['topic_id'] ?? 0);
    $anxiety   = isset($body['anxiety']) ? (float)$body['anxiety'] : null;
    $direction = $body['direction'] ?? 'right';

    if (!$topic_id) err('Invalid swipe');

    $signal_key = 'topic:' . $topic_id;
    if (in_array($signal_key, $_SESSION['signaled'])) {
        json_out(['ok' => true, 'skipped' => true, 'liked' => prefs(), 'countries' => $_SESSION['countries'],
                  'anxiety_avg'   => anx_avg(),
                  'anxiety_count' => anx_count()]);
    }
    $_SESSION['signaled'][] = $signal_key;

    $tags = db()->query("
        SELECT DISTINCT at2.tag
        FROM article_tags at2
        JOIN article_topics ato ON ato.article_id = at2.article_id
        WHERE ato.topic_id = {$topic_id}
        LIMIT 20
    ")->fetchAll(PDO::FETCH_COLUMN);
    $meta = db()->query("SELECT category FROM topics WHERE id = {$topic_id}")->fetch();
    if ($meta['category']) $tags[] = strtolower($meta['category']);

    $delta = $direction === 'right' ? +1 : -1;

    if ($direction === 'right' && $anxiety !== null) {
        anx_push($anxiety);
    }

    foreach ($tags as $tag) {
        $tag = trim(strtolower($tag));
        if (!$tag) continue;
        if (str_starts_with($tag, 'country:')) {
            $cc = strtoupper(substr($tag, 8));
            if (strlen($cc) === 2) $_SESSION['countries'][$cc] = ($_SESSION['countries'][$cc] ?? 0) + $delta;
            continue;
        }
        $_SESSION['prefs'][$tag] = ($_SESSION['prefs'][$tag] ?? 0) + $delta;
    }

    arsort($_SESSION['prefs']);
    json_out(['ok' => true, 'liked' => prefs(), 'countries' => $_SESSION['countries'],
              'anxiety_avg'   => anx_avg(),
              'anxiety_count' => anx_count()]);

// ── View (open card to read bullets) — bumps anxiety once per topic ────────────
case 'view':
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $topic_id = (int)($body['topic_id'] ?? 0);
    $anxiety  = isset($body['anxiety']) ? (float)$body['anxiety'] : null;
    if (!$topic_id || $anxiety === null) err('Invalid view');

    $signal_key = 'view:' . $topic_id;
    if (!in_array($signal_key, $_SESSION['signaled'])) {
        $_SESSION['signaled'][] = $signal_key;
        anx_push($anxiety);
    }
    json_out([
        'ok' => true,
        'anxiety_avg'   => anx_avg(),
        'anxiety_count' => anx_count(),
    ]);

// ── Preferences ───────────────────────────────────────────────────────────────
case 'preferences':
    json_out(['liked' => prefs(), 'countries' => $_SESSION['countries'],
              'anxiety_avg'   => anx_avg(),
              'anxiety_count' => anx_count()]);

// ── Remove tag ────────────────────────────────────────────────────────────────
case 'remove_tag':
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $tag  = trim(strtolower($body['tag'] ?? ''));
    $list = $body['list'] ?? '';
    if ($tag && $list === 'countries') {
        unset($_SESSION['countries'][strtoupper($tag)]);
    } elseif ($tag && $list === 'liked') {
        unset($_SESSION['prefs'][$tag]);
    }
    json_out(['liked' => prefs(), 'countries' => $_SESSION['countries'],
              'anxiety_avg'   => anx_avg(),
              'anxiety_count' => anx_count()]);

// ── Clear preferences ─────────────────────────────────────────────────────────
case 'clear_preferences':
    $_SESSION['prefs'] = $_SESSION['countries'] = $_SESSION['signaled'] = [];
    $_SESSION['anxiety_window'] = [];
    json_out(['ok' => true]);

default:
    err('Unknown action');
}
