# Moodwire

RSS news aggregator with Perplexity AI topic synthesis, anxiety indexing and a mobile card feed UI.

## Stack
- PHP 8.5, MySQL 8 (local), no framework
- Perplexity `sonar` model for all AI calls
- Credentials in `.env` (gitignored) — DB password: `+g9P.YVtkJi5`
- GitHub: https://github.com/federicovalentinob/moodwire

## Local dev
```
cd "/Users/federicobenincasa/pCloud Drive/My files/Travail/moodwire"
php -S localhost:8000
```

## Pipeline (run via run.php or pipeline.php)
1. `fetch.php` — fetch RSS, delete articles >24h old (by published_at), purge empty topics, batch-regenerate stale bullets (1 API call)
2. `normalize.php` — clean raw content
3. `tag.php` — batch 50 articles/call → tags + country (ISO2) + anxiety (0–10), 3-round fallback on truncation
4. `synthesize.php` — cluster unassigned articles into 20–25 topics (1 call), generate all bullets in 1 batch call
5. `pipeline.php` — runs all steps; triggered by "Run Full Pipeline" button

## Key rules
- Articles published >24h ago rejected at save time
- Min 3 articles per topic, min 2 bullets, max 5 bullets
- Max 7 articles shown per topic (most recent first)
- Topics purged when all articles expire
- Bullets regenerated as batch when topic loses articles
- Dedup articles by URL + title within 24h window

## DB tables
`feeds, articles, article_tags, article_indexes, article_topics, topics, topic_bullets, prompts, logs`

## Reader UI (index.php)
- Mobile-first card feed, light theme
- 5-card stack: swipe left=dislike, right=like, up=save for later
- Swipe-based preference tracking: liked/disliked/countries tags in PHP session
- Infinite scroll replaced by stack: replacement card loads on each swipe
- 50% personalized / 50% random for replacement cards
- API backend: `api.php` serves all data as JSON

## Feeds
15 feeds: Guardian, Le Monde, France 24, Google News (World, Business, Tech, Science, Sports, Food, Travel, Sexuality, Europe, Music, Entertainment, Geopolitics)
