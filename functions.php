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

function normalize_url(string $url): string {
    $parts = parse_url($url);
    if (empty($parts['host'])) return $url;

    // Strip common tracking/noise query params
    $strip = ['utm_source','utm_medium','utm_campaign','utm_content','utm_term',
              'ref','source','via','campaign','fbclid','gclid','mc_cid','mc_eid'];

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $params);
        foreach ($strip as $k) unset($params[$k]);
        $parts['query'] = !empty($params) ? http_build_query($params) : null;
    }

    $url = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . ($parts['path'] ?? '');
    if (!empty($parts['query'])) $url .= '?' . $parts['query'];
    return rtrim($url, '/');
}

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

    $seen_titles = [];

    foreach ($items as $item) {
        $title = trim((string)($item->title ?? ''));
        $url   = trim((string)($item->link ?? $item->id ?? ''));
        $desc  = trim(strip_tags((string)($item->description ?? $item->summary ?? $item->content ?? '')));
        $pub   = trim((string)($item->published ?? $item->updated ?? $item->pubDate ?? ''));

        if (!$title || !$url) continue;

        // Normalize URL — strip tracking params
        $url = normalize_url($url);

        // Skip duplicate titles within the same feed fetch
        $title_key = strtolower(preg_replace('/[^a-z0-9]/i', '', $title));
        if (isset($seen_titles[$title_key])) continue;
        $seen_titles[$title_key] = true;

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

function fetch_og_image(string $url): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 7,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: en-US,en;q=0.9'],
        CURLOPT_BUFFERSIZE     => 65536,
    ]);
    $html = curl_exec($ch);
    @curl_close($ch);
    if (!$html) return null;

    // og:image — try both attribute orderings
    if (preg_match('/<meta\s[^>]*property=["\']og:image["\'][^>]*content=["\']([^"\']+)["\'](?:[^>]*)?\/?>/i', $html, $m) ||
        preg_match('/<meta\s[^>]*content=["\']([^"\']{10,})["\'][^>]*property=["\']og:image["\'](?:[^>]*)?\/?>/i', $html, $m)) {
        $img = html_entity_decode(trim($m[1]));
        if (filter_var($img, FILTER_VALIDATE_URL)) return download_thumbnail($img);
    }
    // twitter:image fallback
    if (preg_match('/<meta\s[^>]*name=["\']twitter:image(?::src)?["\'][^>]*content=["\']([^"\']+)["\'](?:[^>]*)?\/?>/i', $html, $m) ||
        preg_match('/<meta\s[^>]*content=["\']([^"\']{10,})["\'][^>]*name=["\']twitter:image(?::src)?["\'](?:[^>]*)?\/?>/i', $html, $m)) {
        $img = html_entity_decode(trim($m[1]));
        if (filter_var($img, FILTER_VALIDATE_URL)) return download_thumbnail($img);
    }
    return null;
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
    $tw = 600; $th = 360;
    // Crop-to-fill: scale so image covers target, then centre-crop
    $ratio  = max($tw / $sw, $th / $sh);
    $src_w  = (int)($tw / $ratio);   // how many source px wide
    $src_h  = (int)($th / $ratio);   // how many source px tall
    $src_x  = (int)(($sw - $src_w) / 2);  // centre-crop offset x
    $src_y  = (int)(($sh - $src_h) / 2);  // centre-crop offset y

    $dst = imagecreatetruecolor($tw, $th);
    imagecopyresampled($dst, $src, 0, 0, $src_x, $src_y, $tw, $th, $src_w, $src_h);
    imagejpeg($dst, $path, 85);

    return 'thumbs/' . $filename;
}

// Delete thumbnail files in thumbs/ that are no longer referenced by any article.
// Returns number of files removed.
function purge_orphan_thumbs(): int {
    $dir = __DIR__ . '/thumbs';
    if (!is_dir($dir)) return 0;
    $referenced = db()->query("SELECT image_path FROM articles WHERE image_path IS NOT NULL")
                      ->fetchAll(PDO::FETCH_COLUMN);
    $keep = [];
    foreach ($referenced as $p) {
        $keep[basename($p)] = true;
    }
    $removed = 0;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        if (!isset($keep[$f])) {
            if (@unlink($dir . '/' . $f)) $removed++;
        }
    }
    return $removed;
}

function save_article(int $feed_id, array $article): bool {
    try {
        // Skip if same title already exists in DB
        $exists = db()->prepare('SELECT COUNT(*) FROM articles WHERE title = ?');
        $exists->execute([$article['title']]);
        if ($exists->fetchColumn() > 0) return false;

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
        'max_tokens' => 32000,
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

function cli_log(string $msg): void {
    if (php_sapi_name() === 'cli') echo $msg . "\n";
}

function dedupe_bullets(array $bullets): array {
    $seen = [];
    $out  = [];
    foreach ($bullets as $b) {
        $b = trim($b);
        if (!$b) continue;
        $fp = implode(' ', array_slice(explode(' ', strtolower($b)), 0, 4));
        if (!in_array($fp, $seen)) { $seen[] = $fp; $out[] = $b; }
    }
    return $out;
}

function trim_title(string $title): string {
    // No-op kept for backward compat with legacy synthesize.php callers.
    return trim($title);
}

function save_topic(string $title, array $bullets, float $anxiety_avg, array $article_ids, string $content_type = 'informative', ?string $category = null): int {
    $title = trim($title);
    $valid_types = ['informative', 'educative', 'entertainment'];
    if (!in_array($content_type, $valid_types)) $content_type = 'informative';
    $valid_cats = ['Politics','Geopolitics','Economy','Technology','Science','Health','Society','Crime','Environment','Sports','Entertainment','Travel','Food'];
    if (!in_array($category, $valid_cats)) $category = null;
    db()->prepare('INSERT INTO topics (title, anxiety_avg, content_type, category, is_new) VALUES (?, ?, ?, ?, 1)')->execute([$title, $anxiety_avg, $content_type, $category]);
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

// ─── OpenAI ──────────────────────────────────────────────────────────────────

function call_openai_embeddings(array $inputs): array {
    if (empty($inputs)) return [];
    $payload = json_encode([
        'model' => defined('EMBEDDING_MODEL') ? EMBEDDING_MODEL : 'text-embedding-3-small',
        'input' => $inputs,
    ]);
    $ch = curl_init('https://api.openai.com/v1/embeddings');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . OPENAI_API_KEY,
            'Content-Type: application/json',
        ],
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code !== 200) {
        cli_log("OpenAI embeddings HTTP $code: " . substr($raw, 0, 300));
        return [];
    }
    $resp = json_decode($raw, true);
    return $resp['data'] ?? [];
}

function call_openai_chat_json(string $system, string $user, array $schema, string $schema_name = 'topic_label'): ?array {
    $payload = json_encode([
        'model'    => defined('LABEL_MODEL') ? LABEL_MODEL : 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ],
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name'   => $schema_name,
                'strict' => true,
                'schema' => $schema,
            ],
        ],
    ]);
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . OPENAI_API_KEY,
            'Content-Type: application/json',
        ],
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code !== 200) {
        cli_log("OpenAI chat HTTP $code: " . substr($raw, 0, 300));
        return null;
    }
    $resp    = json_decode($raw, true);
    $content = $resp['choices'][0]['message']['content'] ?? '';
    $obj     = json_decode($content, true);
    return is_array($obj) ? $obj : null;
}

// Pack/unpack a 1-D float vector as little-endian float32 binary (4 bytes/elem).
function pack_vector(array $v): string  { return pack('f*', ...$v); }
function unpack_vector(string $b): array { return array_values(unpack('f*', $b)); }

function l2_normalize(array $v): array {
    $n = 0.0;
    foreach ($v as $x) $n += $x * $x;
    $n = sqrt($n);
    if ($n <= 0) return $v;
    foreach ($v as $i => $x) $v[$i] = $x / $n;
    return $v;
}
