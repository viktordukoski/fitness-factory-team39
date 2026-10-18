<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
require APP_ROOT . '/database/seed.php';

// DDL in MySQL implicitly commits. Each numbered migration is repeat-safe;
// its marker is written only after every statement succeeds.
$locked = false;
try {
    $locked = (int)fetch_one("SELECT GET_LOCK('fitness_factory_migration',60) acquired")['acquired'] === 1;
    if (!$locked) throw new RuntimeException('Another migration is in progress.');
    db()->exec('CREATE TABLE IF NOT EXISTS schema_migrations(version VARCHAR(64) PRIMARY KEY,applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
    if (!fetch_one('SELECT version FROM schema_migrations WHERE version=\'001-initial\'')) {
        $sql = file_get_contents(APP_ROOT . '/database/schema.sql');
        foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement) if (trim($statement) !== '') db()->exec($statement);
        execute('INSERT INTO schema_migrations(version) VALUES(\'001-initial\')');
        fwrite(STDOUT,"Applied schema 001-initial.\n");
    }
    foreach (glob(APP_ROOT . '/database/migrations/*.sql') ?: [] as $file) {
        $version = basename($file,'.sql');
        if (fetch_one('SELECT version FROM schema_migrations WHERE version=?',[$version])) continue;
        foreach (preg_split('/;\s*(?:\r?\n|$)/',file_get_contents($file)) as $statement) if (trim($statement) !== '') db()->exec($statement);
        execute('INSERT INTO schema_migrations(version) VALUES(?)',[$version]);
        fwrite(STDOUT,'Applied migration '.$version.".\n");
    }
    seed_public_content();
    seed_demonstrations();
    execute('UPDATE memberships SET status=\'expired\' WHERE status=\'active\' AND ends_at<UTC_TIMESTAMP()');
    fwrite(STDOUT,"Database ready; existing content and history preserved.\n");
} catch (Throwable $error) {
    // Avoid exposing connection credentials or raw SQL in deployment output.
    fwrite(STDERR,"Database initialization failed (" . get_class($error) . "). Check private application/database logs and configuration.\n");
    error_log('Migration error: ' . $error->getMessage());
    exit(1);
} finally {
    if ($locked) execute("SELECT RELEASE_LOCK('fitness_factory_migration')");
}
