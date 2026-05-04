<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

// ─── JSON extraction ────────────────────────────────────────────────────────

function extract_json(string $raw): string {
    $raw = trim(preg_replace('/^```json\s*|\s*```$/m', '', trim($raw)));
    // Try direct decode first
    if (json_decode($raw) !== null) return $raw;
    // If truncated array, strip last incomplete entry and close the array
    if (str_starts_with($raw, '[')) {
        // Find the last complete object by working backwards from each },
        $pos = strlen($raw);
        while (($pos = strrpos($raw, '}', $pos - strlen($raw) - 1)) !== false) {
            $candidate = substr($raw, 0, $pos + 1);
            // Remove trailing comma then close array
            $repaired = rtrim($candidate, ", \n\t") . ']';
            if (json_decode($repaired) !== null) return $repaired;
            if ($pos === 0) break;
        }
    }
    // Find the first [ or { and its matching closer
    $start = -1;
    $opener = '';
    for ($i = 0; $i < strlen($raw); $i++) {
        if ($raw[$i] === '[' || $raw[$i] === '{') {
            $start = $i;
            $opener = $raw[$i];
            break;
        }
    }
    if ($start === -1) return $raw;
    $closer  = $opener === '[' ? ']' : '}';
    $depth   = 0;
    $in_str  = false;
    $escape  = false;
    for ($i = $start; $i < strlen($raw); $i++) {
        $c = $raw[$i];
        if ($escape) { $escape = false; continue; }
        if ($c === '\\' && $in_str) { $escape = true; continue; }
        if ($c === '"') { $in_str = !$in_str; continue; }
        if ($in_str) continue;
        if ($c === $opener || ($opener === '[' && $c === '{') || ($opener === '{' && $c === '[')) $depth++;
        if ($c === $closer || ($opener === '[' && $c === '}') || ($opener === '{' && $c === ']')) $depth--;
        if ($depth === 0) return substr($raw, $start, $i - $start + 1);
    }
    return substr($raw, $start);
}

// ─── Logging ────────────────────────────────────────────────────────────────

function log_action(string $action, string $status = 'success', string $message = ''): void {
    db()->prepare('INSERT INTO logs (action, status, message) VALUES (?, ?, ?)')
       ->execute([$action, $status, $message]);
}

// ─── Feeds ──────────────────────────────────────────────────────────────────

function get_feeds(bool $active_only = false): array {
    $sql = 'SELECT * FROM feeds' . ($active_only ? ' WHERE active = 1' : '') . ' ORDER BY name';
    return db()->query($sql)->fetchAll();
}

function get_feed(int $id): array|false {
    $st = db()->prepare('SELECT * FROM feeds WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch();
}

function save_feed(string $name, string $url, string $category = '', int $id = 0): int {
    if ($id) {
        db()->prepare('UPDATE feeds SET name=?, url=?, category=? WHERE id=?')
           ->execute([$name, $url, $category, $id]);
        return $id;
    }
    db()->prepare('INSERT INTO feeds (name, url, category) VALUES (?, ?, ?)')
       ->execute([$name, $url, $category]);
    return (int) db()->lastInsertId();
}

function toggle_feed(int $id): void {
    db()->prepare('UPDATE feeds SET active = 1 - active WHERE id = ?')->execute([$id]);
}

function delete_feed(int $id): void {
    db()->prepare('DELETE FROM feeds WHERE id = ?')->execute([$id]);
}

function update_feed_fetched(int $id): void {
    db()->prepare('UPDATE feeds SET last_fetched = NOW() WHERE id = ?')->execute([$id]);
}

// ─── RSS Fetching ────────────────────────────────────────────────────────────

function fetch_rss(string $url): array {
    $ctx = stream_context_create(['http' => [
        'user_agent' => 'Mozilla/5.0 (Moodwire RSS Reader)',
        'timeout'    => 15,
    ]]);
    $xml = @file_get_contents($url, false, $ctx);
    if (!$xml) return [];

    libxml_use_internal_errors(true);
    $feed = simplexml_load_string($xml);
    if (!$feed) return [];

    $articles = [];
    $items = $feed->channel->item ?? $feed->entry ?? [];

    foreach ($items as $item) {
        $title = trim((string)($item->title ?? ''));
        $url   = trim((string)($item->link ?? $item->id ?? ''));
        $desc  = trim(strip_tags((string)($item->description ?? $item->summary ?? $item->content ?? '')));
        $pub   = trim((string)($item->pubDate ?? $item->published ?? $item->updated ?? ''));

        if (!$title || !$url) continue;

        $articles[] = [
            'title'        => $title,
            'url'          => $url,
            'raw_content'  => $desc,
            'published_at' => $pub ? date('Y-m-d H:i:s', strtotime($pub)) : null,
        ];
    }
    return $articles;
}

function save_article(int $feed_id, array $article): bool {
    try {
        db()->prepare('INSERT IGNORE INTO articles (feed_id, title, url, raw_content, published_at)
                       VALUES (?, ?, ?, ?, ?)')
           ->execute([
               $feed_id,
               $article['title'],
               $article['url'],
               $article['raw_content'],
               $article['published_at'],
           ]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ─── Normalization ───────────────────────────────────────────────────────────

function normalize_text(string $text): string {
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strip_tags($text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = preg_replace('/Continue reading\.\.\./i', '', $text);
    return trim($text);
}

function get_unnormalized_articles(): array {
    return db()->query('SELECT * FROM articles WHERE clean_content IS NULL OR clean_content = ""')->fetchAll();
}

function save_clean_content(int $id, string $clean): void {
    db()->prepare('UPDATE articles SET clean_content = ? WHERE id = ?')->execute([$clean, $id]);
}

// ─── Articles ────────────────────────────────────────────────────────────────

function get_unprocessed_articles(int $limit = 10): array {
    return db()->query("SELECT * FROM articles WHERE processed = 0 AND clean_content IS NOT NULL LIMIT {$limit}")->fetchAll();
}

function count_unprocessed_articles(): int {
    return (int) db()->query('SELECT COUNT(*) FROM articles WHERE processed = 0')->fetchColumn();
}

function mark_article_processed(int $id): void {
    db()->prepare('UPDATE articles SET processed = 1 WHERE id = ?')->execute([$id]);
}

function save_article_tags(int $article_id, array $tags): void {
    db()->prepare('DELETE FROM article_tags WHERE article_id = ?')->execute([$article_id]);
    $st = db()->prepare('INSERT INTO article_tags (article_id, tag) VALUES (?, ?)');
    foreach ($tags as $tag) {
        $st->execute([$article_id, strtolower(trim($tag))]);
    }
}

function save_article_index(int $article_id, string $index_name, float $score): void {
    db()->prepare('INSERT INTO article_indexes (article_id, index_name, score)
                   VALUES (?, ?, ?)
                   ON DUPLICATE KEY UPDATE score = ?')
       ->execute([$article_id, $index_name, $score, $score]);
}

function get_prompt(string $name): string {
    $st = db()->prepare('SELECT content FROM prompts WHERE name = ? AND active = 1');
    $st->execute([$name]);
    return $st->fetchColumn() ?: '';
}

function tag_articles_batch(array $articles): array {
    $lines = '';
    foreach ($articles as $a) {
        $desc   = substr($a['clean_content'] ?? '', 0, 150);
        $lines .= "ID:{$a['id']} | {$a['title']} | {$desc}\n";
    }

    $prompt = get_prompt('tagging');
    $prompt = str_replace('{{articles}}', $lines, $prompt);
    $system = 'You are a JSON API. Output only a raw valid JSON array. No explanations, no citations, no markdown.';
    $raw    = call_perplexity($prompt, $system);
    $result = json_decode(extract_json($raw), true);

    if (!is_array($result)) return [];

    $indexed = [];
    foreach ($result as $r) {
        if (isset($r['id'])) $indexed[(int)$r['id']] = $r;
    }
    return $indexed;
}

function save_article_country(int $article_id, ?string $country): void {
    if (!$country) return;
    $country = strtoupper(trim($country));
    if (strlen($country) !== 2) return;
    db()->prepare("INSERT IGNORE INTO article_tags (article_id, tag) VALUES (?, ?)")
       ->execute([$article_id, 'country:' . $country]);
}

// ─── Perplexity API ──────────────────────────────────────────────────────────

function call_perplexity(string $prompt, string $system = ''): string {
    $messages = [];
    if ($system) $messages[] = ['role' => 'system', 'content' => $system];
    $messages[] = ['role' => 'user', 'content' => $prompt];

    $payload = json_encode([
        'model'      => PERPLEXITY_MODEL,
        'max_tokens' => 4096,
        'messages'   => $messages,
    ]);

    $ch = curl_init('https://api.perplexity.ai/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . PERPLEXITY_API_KEY,
        ],
    ]);
    $response = curl_exec($ch);
    @curl_close($ch);

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? '';
}

// ─── Topics ──────────────────────────────────────────────────────────────────

function get_latest_topics(): array {
    $topics = db()->query('
        SELECT t.*,
               MAX(COALESCE(a.published_at, a.fetched_at)) as latest_article_at
        FROM topics t
        LEFT JOIN article_topics ato ON ato.topic_id = t.id
        LEFT JOIN articles a ON a.id = ato.article_id
        GROUP BY t.id
        ORDER BY t.anxiety_avg DESC, t.created_at DESC
    ')->fetchAll();
    foreach ($topics as &$topic) {
        $st = db()->prepare('SELECT bullet FROM topic_bullets WHERE topic_id = ? ORDER BY display_order');
        $st->execute([$topic['id']]);
        $topic['bullets'] = $st->fetchAll(PDO::FETCH_COLUMN);

        $st = db()->prepare('SELECT a.*, f.name as feed_name,
                                    GROUP_CONCAT(DISTINCT at2.tag) as tags,
                                    ai.score as anxiety
                             FROM articles a
                             JOIN article_topics ato ON ato.article_id = a.id
                             JOIN feeds f ON f.id = a.feed_id
                             LEFT JOIN article_tags at2 ON at2.article_id = a.id
                             LEFT JOIN article_indexes ai ON ai.article_id = a.id AND ai.index_name = "anxiety"
                             WHERE ato.topic_id = ?
                             GROUP BY a.id
                             ORDER BY a.published_at DESC, a.fetched_at DESC
                             LIMIT 7');
        $st->execute([$topic['id']]);
        $topic['articles'] = $st->fetchAll();
    }
    return $topics;
}

function save_topic(string $title, array $bullets, float $anxiety_avg, array $article_ids, string $content_type = 'informative'): int {
    $valid_types = ['informative', 'educative', 'entertainment'];
    if (!in_array($content_type, $valid_types)) $content_type = 'informative';
    db()->prepare('INSERT INTO topics (title, anxiety_avg, content_type) VALUES (?, ?, ?)')->execute([$title, $anxiety_avg, $content_type]);
    $topic_id = (int) db()->lastInsertId();

    $st = db()->prepare('INSERT INTO topic_bullets (topic_id, bullet, display_order) VALUES (?, ?, ?)');
    foreach ($bullets as $i => $bullet) {
        $st->execute([$topic_id, $bullet, $i]);
    }

    $st = db()->prepare('INSERT IGNORE INTO article_topics (article_id, topic_id) VALUES (?, ?)');
    foreach ($article_ids as $article_id) {
        $st->execute([$article_id, $topic_id]);
    }
    return $topic_id;
}

// ─── Display helpers ─────────────────────────────────────────────────────────

function regenerate_stale_bullets(): void {
    require_once __DIR__ . '/config.php';

    $system  = 'You are a JSON API. Output only a raw valid JSON array. No markdown, no citations, no extra text.';
    $prompt_tpl = get_prompt('bullets');

    // Find topics whose bullet count exceeds their current article count
    $topics = db()->query('
        SELECT t.id, t.title,
               COUNT(ato.article_id) as article_count,
               (SELECT COUNT(*) FROM topic_bullets WHERE topic_id = t.id) as bullet_count
        FROM topics t
        LEFT JOIN article_topics ato ON ato.topic_id = t.id
        GROUP BY t.id
        HAVING bullet_count > article_count OR article_count = 0
    ')->fetchAll();

    foreach ($topics as $topic) {
        if ((int)$topic['article_count'] === 0) continue;

        $art_titles = db()->query(
            "SELECT a.title FROM articles a
             JOIN article_topics ato ON ato.article_id = a.id
             WHERE ato.topic_id = {$topic['id']}"
        )->fetchAll(PDO::FETCH_COLUMN);

        $art_list  = implode("\n", array_map(fn($t) => "- {$t}", $art_titles));
        $max_b     = min(5, max(1, (int)$topic['article_count']));
        $prompt    = str_replace(
            ['{{topic}}', '{{articles}}', '{{num_bullets}}'],
            [$topic['title'], $art_list, (string)$max_b],
            $prompt_tpl
        );

        $raw     = call_perplexity($prompt, $system);
        $bullets = json_decode(extract_json($raw), true);
        if (!is_array($bullets) || count($bullets) < 1) continue;

        $bullets = array_map(fn($b) => mb_substr(trim($b), 0, 100), array_slice($bullets, 0, $max_b));

        db()->prepare('DELETE FROM topic_bullets WHERE topic_id = ?')->execute([$topic['id']]);
        $st = db()->prepare('INSERT INTO topic_bullets (topic_id, bullet, display_order) VALUES (?, ?, ?)');
        foreach ($bullets as $i => $b) $st->execute([$topic['id'], $b, $i]);

        log_action('fetch', 'success', "Regenerated bullets for topic [{$topic['id']}] {$topic['title']}");
    }
}

function purge_empty_topics(): int {
    return (int) db()->exec("
        DELETE FROM topics WHERE id NOT IN (
            SELECT DISTINCT topic_id FROM article_topics
        )
    ");
}

function time_ago(?string $datetime): string {
    if (!$datetime) return '';
    $diff = max(0, time() - strtotime($datetime));
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

function anxiety_color(float $score): string {
    if ($score <= 3) return '#16a34a';
    if ($score <= 6) return '#d97706';
    return '#dc2626';
}

function anxiety_label(float $score): string {
    if ($score <= 3) return 'Low';
    if ($score <= 6) return 'Medium';
    return 'High';
}
