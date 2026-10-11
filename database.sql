-- ============================================================
-- AUTO NEWS - Database Schema (MySQL / MariaDB)
-- Set and Forget AI News Rewriter
-- ============================================================

SET NAMES utf8mb4;

-- ---------- Settings (key-value store) ----------
CREATE TABLE IF NOT EXISTS settings (
    skey   VARCHAR(100) NOT NULL,
    svalue TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Admin accounts ----------
CREATE TABLE IF NOT EXISTS admins (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username   VARCHAR(100) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,          -- password_hash()
    email      VARCHAR(190) NULL,
    role       VARCHAR(20) NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- RSS sources ----------
CREATE TABLE IF NOT EXISTS rss_sources (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(190) NOT NULL,
    url          TEXT NOT NULL,
    source_site  VARCHAR(190) NULL,             -- portal name (detik, kompas, ...)
    language     VARCHAR(10) NOT NULL DEFAULT 'id',
    active       TINYINT(1) NOT NULL DEFAULT 1,
    fetch_interval_min INT UNSIGNED NOT NULL DEFAULT 30,  -- per-source fetch throttle
    last_fetched_at DATETIME NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_active_lang (active, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Articles (news) ----------
CREATE TABLE IF NOT EXISTS articles (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    guid         VARCHAR(255) NULL,             -- unique id from RSS, for dedup
    source_id    INT UNSIGNED NULL,
    source_site  VARCHAR(190) NULL,
    title        VARCHAR(500) NOT NULL,
    slug         VARCHAR(600) NOT NULL,
    excerpt      TEXT NULL,
    body         MEDIUMTEXT NULL,
    lang         VARCHAR(10) NOT NULL DEFAULT 'id',
    image        TEXT NULL,                     -- featured image url
    image_alt    VARCHAR(500) NULL,
    meta_title   VARCHAR(500) NULL,
    meta_desc    VARCHAR(300) NULL,
    keywords     VARCHAR(500) NULL,             -- comma separated
    tags         TEXT NULL,
    original_url TEXT NULL,                     -- source link
    status       ENUM('draft','published','trash') NOT NULL DEFAULT 'draft',
    views        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    is_featured  TINYINT(1) NOT NULL DEFAULT 0,
    -- SEO Boosters
    faq_json     MEDIUMTEXT NULL,               -- FAQPage schema json
    checked_at   DATETIME NULL,
    published_at DATETIME NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_guid (guid),
    KEY idx_slug (slug),
    KEY idx_status_pub (status, published_at),
    KEY idx_lang (lang),
    FULLTEXT KEY ft_title_body (title, body, excerpt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Scrape / rewrite logs ----------
CREATE TABLE IF NOT EXISTS scrape_logs (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    type       VARCHAR(30) NOT NULL,            -- rss_fetch | ai_rewrite | error
    source_id  INT UNSIGNED NULL,
    article_id INT UNSIGNED NULL,
    message    TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;