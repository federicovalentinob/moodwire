<?php
require_once __DIR__ . '/functions.php';

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

    $sql = 'SELECT t.*,
                   MAX(COALESCE(a.published_at, a.fetched_at)) as latest_article_at
            FROM topics t
            LEFT JOIN article_topics ato ON ato.topic_id = t.id
            LEFT JOIN articles a ON a.id = ato.article_id
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY t.id
            HAVING COUNT(ato.article_id) > 0
            ORDER BY t.anxiety_avg DESC, latest_article_at DESC';

    $topics = db()->query($sql)->fetchAll();

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
    json_out($topics);

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

default:
    err('Unknown action');
}
