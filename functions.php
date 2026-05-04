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
        $pub   = trim((string)($item->published ?? $item->updated ?? $item->pubDate ?? ''));

        if (!$title || !$url) continue;

        // Extract image URL from media:content, media:thumbnail or enclosure
        $image_url = null;
        $item_xml  = $item->asXML();
        if (preg_match('/media:(?:content|thumbnail)[^>]+url=["\']([^"\']+)["\']/', $item_xml, $m)) {
            $image_url = html_entity_decode($m[1]);
        } elseif (preg_match('/<enclosure[^>]+url=["\']([^"\']+)["\'][^>]+type=["\']image/i', $item_xml, $m)) {
            $image_url = html_entity_decode($m[1]);
        }
        if ($image_url) $image_url = preg_replace('/width=\d+&?/', '', $image_url); // strip width param for best quality

        $articles[] = [
            'title'        => $title,
            'url'          => $url,
            'raw_content'  => $desc,
            'published_at' => $pub ? date('Y-m-d H:i:s', strtotime($pub)) : null,
            'image_url'    => $image_url,
        ];
    }
    return $articles;
}

function download_thumbnail(string $image_url): ?string {
    $ext      = strtolower(pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) $ext = 'jpg';
    $filename = md5($image_url) . '.jpg';
    $path     = __DIR__ . '/thumbs/' . $filename;

    if (file_exists($path)) return 'thumbs/' . $filename;

    $ch = curl_init($image_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
        CURLOPT_REFERER        => parse_url($image_url, PHP_URL_SCHEME) . '://' . parse_url($image_url, PHP_URL_HOST),
    ]);
    $raw = curl_exec($ch);
    @curl_close($ch);
    if (!$raw) return null;

    $src = @imagecreatefromstring($raw);
    if (!$src) return null;

    $sw = imagesx($src); $sh = imagesy($src);
    $tw = 300; $th = 180;
    $ratio = min($tw / $sw, $th / $sh);
    $nw = (int)($sw * $ratio); $nh = (int)($sh * $ratio);

    $dst = imagecreatetruecolor($tw, $th);
    $bg  = imagecolorallocate($dst, 240, 240, 240);
    imagefill($dst, 0, 0, $bg);
    $ox = (int)(($tw - $nw) / 2); $oy = (int)(($th - $nh) / 2);
    imagecopyresampled($dst, $src, $ox, $oy, 0, 0, $nw, $nh, $sw, $sh);
    imagejpeg($dst, $path, 80);

    return 'thumbs/' . $filename;
}

function save_article(int $feed_id, array $article): bool {
    try {
        $image_path = null;
        if (!empty($article['image_url'])) {
            $image_path = download_thumbnail($article['image_url']);
        }
        db()->prepare('INSERT IGNORE INTO articles (feed_id, title, url, raw_content, published_at, image_path)
                       VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([
               $feed_id,
               $article['title'],
               $article['url'],
               $article['raw_content'],
               $article['published_at'],
               $image_path,
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

function trim_title(string $title): string {
    $words = explode(' ', trim($title));
    return implode(' ', array_slice($words, 0, 5));
}

function save_topic(string $title, array $bullets, float $anxiety_avg, array $article_ids, string $content_type = 'informative', ?string $category = null): int {
    $title = trim_title($title);
    $valid_types = ['informative', 'educative', 'entertainment'];
    if (!in_array($content_type, $valid_types)) $content_type = 'informative';
    $valid_cats = ['Politics','Geopolitics','Economy','Technology','Science','Health','Society','Crime','Environment','Sports','Entertainment','Travel','Food'];
    if (!in_array($category, $valid_cats)) $category = null;
    db()->prepare('INSERT INTO topics (title, anxiety_avg, content_type, category) VALUES (?, ?, ?, ?)')->execute([$title, $anxiety_avg, $content_type, $category]);
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

function compute_topic_geo(int $topic_id): ?string {
    static $regions = [
        'North America' => ['US','CA','MX'],
        'Central America' => ['GT','BZ','HN','SV','NI','CR','PA','CU','HT','JM','DO','TT','BB','BS'],
        'South America' => ['BR','AR','CL','CO','PE','VE','EC','BO','PY','UY','GY','SR'],
        'Western Europe' => ['FR','DE','GB','IT','ES','NL','BE','CH','AT','PT','IE','LU','MC','AD','LI'],
        'Northern Europe' => ['SE','NO','DK','FI','IS','EE','LV','LT'],
        'Eastern Europe' => ['PL','CZ','SK','HU','RO','BG','HR','SI','RS','BA','ME','MK','AL','UA','BY','MD'],
        'Middle East' => ['IR','IQ','SA','IL','LB','SY','YE','AE','QA','KW','JO','OM','BH','PS'],
        'North Africa' => ['EG','LY','TN','DZ','MA','SD'],
        'Sub-Saharan Africa' => ['ZA','NG','KE','ET','GH','SN','TZ','UG','CI','CM','AO','MZ','ZM','ZW','ML','BF','NE','TD','SO','ER','RW','MG'],
        'Central Asia' => ['KZ','UZ','TM','KG','TJ','AF'],
        'South Asia' => ['IN','PK','BD','LK','NP','BT','MV'],
        'East Asia' => ['CN','JP','KR','TW','MN','HK'],
        'Southeast Asia' => ['TH','VN','ID','MY','PH','SG','MM','KH','LA','BN','TL'],
        'Oceania' => ['AU','NZ','PG','FJ','SB','VU','WS','TO','KI','FM'],
        'Russia & CIS' => ['RU','GE','AM','AZ'],
    ];

    // Regional groupings (for when articles span multiple sub-regions)
    static $super = [
        'Europe' => ['Western Europe','Northern Europe','Eastern Europe'],
        'Americas' => ['North America','Central America','South America'],
        'Africa' => ['North Africa','Sub-Saharan Africa'],
        'Asia' => ['Central Asia','South Asia','East Asia','Southeast Asia'],
    ];

    // Get country codes for articles in this topic
    $codes = db()->query("
        SELECT DISTINCT UPPER(SUBSTRING(at2.tag, 9)) as code
        FROM article_tags at2
        JOIN article_topics ato ON ato.article_id = at2.article_id
        WHERE ato.topic_id = {$topic_id} AND at2.tag LIKE 'country:%'
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (empty($codes)) return null;

    // Build reverse map: country → region
    $country_to_region = [];
    foreach ($regions as $region => $countries) {
        foreach ($countries as $c) $country_to_region[$c] = $region;
    }

    // Map codes to regions
    $article_regions = [];
    foreach ($codes as $code) {
        $article_regions[] = $country_to_region[$code] ?? null;
    }
    $article_regions = array_values(array_unique(array_filter($article_regions)));

    // If all articles from one country
    $unique_codes = array_unique($codes);
    if (count($unique_codes) === 1) return $unique_codes[0];

    // If all in same region
    if (count($article_regions) === 1) return $article_regions[0];

    // If all in same super-region
    foreach ($super as $super_name => $sub_regions) {
        $all_in_super = count(array_filter($article_regions, fn($r) => in_array($r, $sub_regions))) === count($article_regions);
        if ($all_in_super) return $super_name;
    }

    return 'International';
}

function update_topic_geo(int $topic_id): void {
    $geo = compute_topic_geo($topic_id);
    db()->prepare('UPDATE topics SET geo = ? WHERE id = ?')->execute([$geo, $topic_id]);
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
