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
$lastCode = null;
for ($attempt = 1; $attempt <= 60; $attempt++) {
    try {
        $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
        $pdo->query('SELECT 1');
        fwrite(STDOUT, "Database ready.\n");
        exit(0);
    } catch (PDOException $exception) {
        // Report only the numeric driver code, never credentials or exception text.
        $code = (int)($exception->errorInfo[1] ?? 0);
        if ($code !== $lastCode) {
            fwrite(STDERR, "Waiting for database (MySQL error {$code}).\n");
            $lastCode = $code;
        }
        if (in_array($code, [1044, 1045, 1049], true)) {
            fwrite(STDERR, "Database access failed (MySQL error {$code}). Check DB_USER/DB_PASSWORD, DB_NAME, database existence and account permissions. Changing environment values does not update accounts in an existing database.\n");
            exit(1);
        }
        if ($attempt === 60) {
            fwrite(STDERR, "Database was not ready after 60 attempts. Check database health and environment values.\n");
            exit(1);
        }
        sleep(2);
    }
}
