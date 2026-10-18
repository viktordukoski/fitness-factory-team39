<?php
declare(strict_types=1);
$required = ['DB_NAME', 'DB_USER', 'DB_PASSWORD', 'APP_KEY'];
foreach ($required as $name) {
    if (trim((string)getenv($name)) === '') {
        fwrite(STDERR, "Required environment value missing: {$name}\n");
        exit(1);
    }
}
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: 'db', getenv('DB_PORT') ?: '3306', getenv('DB_NAME'));
for ($attempt = 1; $attempt <= 60; $attempt++) {
    try {
        $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
        $pdo->query('SELECT 1');
        fwrite(STDOUT, "Database ready.\n");
        exit(0);
    } catch (PDOException $exception) {
        if ($attempt === 60) {
            fwrite(STDERR, "Database was not ready within 120 seconds. Check database health and environment values.\n");
            exit(1);
        }
        sleep(2);
    }
}
