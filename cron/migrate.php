<?php
/**
 * AUTO NEWS - One-time migration (idempotent)
 *
 * adds the `category` column + index to the `articles` table for installs that
 * were created before the topic-category feature. Safe to run multiple times
 * (it checks information_schema first).
 *
 * Usage:  php /home/USER/public_html/cron/migrate.php
 */

require_once dirname(__DIR__) . '/includes/config.php';

function column_exists($pdo, $db, $table, $col) {
    $st = $pdo->prepare(
        "SELECT COUNT(*) c FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $st->execute([$db, $table, $col]);
    return (int)$st->fetch()['c'] > 0;
}

$dbName = DB_NAME;

if (!column_exists(db(), $dbName, 'articles', 'category')) {
    db()->exec("ALTER TABLE articles ADD COLUMN category VARCHAR(120) NULL AFTER source_site");
    echo "Added `category` column to articles.\n";
} else {
    echo "`category` column already exists — skip.\n";
}

// Add index if not present (safe: column now exists)
try {
    db()->exec("ALTER TABLE articles ADD INDEX idx_category (category)");
    echo "Added idx_category index.\n";
} catch (Exception $e) {
    echo "idx_category index already present.\n";
}

echo "Migration done.\n";
