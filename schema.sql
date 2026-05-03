CREATE DATABASE IF NOT EXISTS moodwire CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE moodwire;

CREATE TABLE IF NOT EXISTS feeds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL UNIQUE,
    category VARCHAR(100) DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    last_fetched DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feed_id INT NOT NULL,
    title TEXT NOT NULL,
    url VARCHAR(500) NOT NULL UNIQUE,
    raw_content LONGTEXT,
    clean_content LONGTEXT,
    published_at DATETIME DEFAULT NULL,
    fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed TINYINT(1) DEFAULT 0,
    FOREIGN KEY (feed_id) REFERENCES feeds(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS article_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    tag VARCHAR(100) NOT NULL,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS article_indexes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    index_name VARCHAR(100) NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_article_index (article_id, index_name)
);

CREATE TABLE IF NOT EXISTS topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    anxiety_avg DECIMAL(5,2) DEFAULT NULL,
    prompt_version VARCHAR(50) DEFAULT '1.0',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS topic_bullets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    bullet TEXT NOT NULL,
    display_order INT DEFAULT 0,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS article_topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    topic_id INT NOT NULL,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    UNIQUE KEY unique_article_topic (article_id, topic_id)
);

CREATE TABLE IF NOT EXISTS prompts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    content LONGTEXT NOT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    status ENUM('success', 'error', 'warning') DEFAULT 'success',
    message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Seed default feeds
INSERT IGNORE INTO feeds (name, url, category) VALUES
('The Guardian - World', 'https://www.theguardian.com/world/rss', 'World'),
('Le Monde - International', 'https://www.lemonde.fr/en/international/rss_full.xml', 'World'),
('France 24 - International', 'https://www.france24.com/en/rss', 'World');

-- Seed default prompts
INSERT IGNORE INTO prompts (name, content) VALUES
('tagging', 'You are a news analyst. Given the following article title and description, return a JSON object with:\n- "tags": array of 3-5 short topic tags (e.g. "geopolitics", "climate", "economy")\n- "anxiety": a score from 0 to 10 (0=very positive/relaxing, 10=very stressful/alarming)\n\nArticle title: {{title}}\nArticle description: {{description}}\n\nReturn only valid JSON, no explanation.'),
('synthesis', 'You are a senior news editor. Below is a list of news articles with their tags and anxiety scores.\n\nGroup them into {{num_topics}} main topics. For each topic:\n- Give it a short title\n- Write exactly 3 bullet points: short, informative, no fluff\n- Compute an average anxiety score (0-10)\n- List the article IDs that belong to it\n\nArticles:\n{{articles}}\n\nReturn a JSON array of topics with fields: title, bullets (array of 3 strings), anxiety_avg, article_ids (array).');
