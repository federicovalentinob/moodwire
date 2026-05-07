<?php
require_once __DIR__ . '/functions.php';

$BATCH   = 60;   // articles per run
$TIMEOUT = 7;    // seconds per request
$CONCUR  = 8;    // parallel requests

$articles = db()->query("
    SELECT id, url FROM articles
    WHERE image_path IS NULL
      AND url NOT LIKE '%news.google.com%'
    ORDER BY fetched_at DESC
    LIMIT {$BATCH}
")->fetchAll();

if (!$articles) {
    if (php_sapi_name() === 'cli') echo "No articles need images.\n";
    return;
}

// Parallel HTML fetch via curl_multi
$mh      = curl_multi_init();
$handles = [];

foreach ($articles as $a) {
    $ch = curl_init($a['url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $TIMEOUT,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: en-US,en;q=0.9'],
        CURLOPT_BUFFERSIZE     => 65536,
    ]);
    $handles[$a['id']] = $ch;
}

// Process in batches of $CONCUR
$chunks  = array_chunk($handles, $CONCUR, true);
$updated = 0;

foreach ($chunks as $chunk) {
    foreach ($chunk as $ch) curl_multi_add_handle($mh, $ch);

    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    foreach ($chunk as $article_id => $ch) {
        $html = curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);
        @curl_close($ch);

        if (!$html) continue;

        $img_url = null;
        // og:image — both attribute orderings
        if (preg_match('/<meta\s[^>]*property=["\']og:image["\'][^>]*content=["\']([^"\']+)["\'][^>]*\/?>/i', $html, $m) ||
            preg_match('/<meta\s[^>]*content=["\']([^"\']{10,})["\'][^>]*property=["\']og:image["\'][^>]*\/?>/i', $html, $m)) {
            $candidate = html_entity_decode(trim($m[1]));
            if (filter_var($candidate, FILTER_VALIDATE_URL)) $img_url = $candidate;
        }
        // twitter:image fallback
        if (!$img_url) {
            if (preg_match('/<meta\s[^>]*name=["\']twitter:image(?::src)?["\'][^>]*content=["\']([^"\']+)["\'][^>]*\/?>/i', $html, $m) ||
                preg_match('/<meta\s[^>]*content=["\']([^"\']{10,})["\'][^>]*name=["\']twitter:image(?::src)?["\'][^>]*\/?>/i', $html, $m)) {
                $candidate = html_entity_decode(trim($m[1]));
                if (filter_var($candidate, FILTER_VALIDATE_URL)) $img_url = $candidate;
            }
        }

        if (!$img_url) continue;

        // Skip Google-hosted images (generic/branding thumbnails, not real article photos)
        if (str_contains($img_url, 'lh3.googleusercontent.com') ||
            str_contains($img_url, 'news.google.com')) continue;

        $path = download_thumbnail($img_url);
        if ($path) {
            db()->prepare('UPDATE articles SET image_path = ? WHERE id = ?')
               ->execute([$path, $article_id]);
            $updated++;
        }
    }
}

curl_multi_close($mh);

$total = count($articles);
log_action('fetch_images', 'success', "OG images: {$updated}/{$total} fetched");

if (php_sapi_name() === 'cli') {
    echo "Images fetched: {$updated}/{$total}\n";
} else {
    echo json_encode(['images_fetched' => $updated, 'attempted' => $total]);
}
