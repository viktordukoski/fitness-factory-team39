<?php
declare(strict_types=1);
// Isolated seed-boundary test: fake storage deliberately has no database connection.
// This exercises the actual initializer and verifies writes without touching test/live data.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$configuration = [];
$administrator = null;
$seedComplete = false;
$writes = [];
$results = [];
function env(string $key, string $default = ''): string { return $GLOBALS['configuration'][$key] ?? $default; }
function demo_mode(): bool { return true; }
function fetch_one(string $sql, array $params = []): ?array
{
    if ($sql === "SELECT id FROM users WHERE email='admin@demo.test'") return $GLOBALS['administrator'];
    if ($sql === "SELECT version FROM schema_migrations WHERE version='seed-demo-v1'") return $GLOBALS['seedComplete'] ? ['version'=>'seed-demo-v1'] : null;
    throw new RuntimeException('Unexpected storage read in isolated test.');
}
function execute(string $sql, array $params = []): void
{
    if (!str_starts_with($sql, 'INSERT IGNORE INTO users(') || !str_contains($sql, "'admin@demo.test'")) {
        throw new RuntimeException('Unexpected storage write in isolated test.');
    }
    $GLOBALS['writes'][] = $sql;
    $GLOBALS['administrator'] ??= ['id'=>1, 'password_hash'=>$params[0]];
}
function expect(bool $condition): void { if (!$condition) throw new RuntimeException('Assertion failed.'); }
function check(string $name, callable $work): void
{
    global $results;
    try { $work(); $results[] = ['test'=>$name, 'status'=>'PASS']; }
    catch (Throwable $error) { $results[] = ['test'=>$name, 'status'=>'NEEDS FIX', 'reason'=>$error->getMessage()]; }
}
function rejects(callable $work): void
{
    try { $work(); } catch (RuntimeException $error) {
        expect(str_starts_with($error->getMessage(), 'DEMO_ADMIN_PASSWORD'));
        return;
    }
    throw new RuntimeException('Expected private-password configuration rejection.');
}
require dirname(__DIR__) . '/database/seed.php';

check('Local origins retain the documented local default', function (): void {
    foreach (['http://localhost:8080', 'http://127.0.0.1:8098', 'http://[::1]:8080'] as $origin) {
        $GLOBALS['configuration'] = ['APP_URL'=>$origin];
        expect(password_verify('FactoryAdmin!2026', demo_admin_initial_hash()));
    }
});
check('Missing hosted secret fails before administrator insertion', function (): void {
    foreach (['https://fitness.example.test', 'https://localhost.example.test', 'invalid-origin'] as $origin) {
        $GLOBALS['configuration'] = ['APP_URL'=>$origin];
        $GLOBALS['administrator'] = null;
        $GLOBALS['writes'] = [];
        rejects(fn()=>seed_demo_administrator());
        expect($GLOBALS['writes'] === [] && $GLOBALS['administrator'] === null);
    }
});
check('Supplied password enforces 16–72 bytes, whitespace and NUL rejection', function (): void {
    foreach ([str_repeat('a',15), str_repeat('a',73), str_repeat(' ',16), str_repeat('a',16)."\0"] as $password) {
        $GLOBALS['configuration'] = ['APP_URL'=>'http://localhost', 'DEMO_ADMIN_PASSWORD'=>$password];
        rejects(fn()=>demo_admin_initial_hash());
    }
    foreach ([bin2hex(random_bytes(8)), bin2hex(random_bytes(36)), str_repeat('é',8)] as $password) {
        $GLOBALS['configuration'] = ['APP_URL'=>'https://fitness.example.test', 'DEMO_ADMIN_PASSWORD'=>$password];
        expect(password_verify($password, demo_admin_initial_hash()));
    }
});
check('Fresh hosted administrator stores only the supplied password hash', function (): void {
    $password = bin2hex(random_bytes(24));
    $GLOBALS['configuration'] = ['APP_URL'=>'https://fitness.example.test', 'DEMO_ADMIN_PASSWORD'=>$password];
    $GLOBALS['administrator'] = null;
    $GLOBALS['writes'] = [];
    seed_demo_administrator();
    $hash = $GLOBALS['administrator']['password_hash'];
    expect(count($GLOBALS['writes']) === 1 && $hash !== $password);
    expect(password_verify($password,$hash) && !password_verify('FactoryAdmin!2026',$hash));
});
check('Existing administrator is preserved on partial-seed retries', function (): void {
    $before = $GLOBALS['administrator'];
    $GLOBALS['writes'] = [];
    foreach (['', 'too-short', bin2hex(random_bytes(24))] as $password) {
        $GLOBALS['configuration'] = ['APP_URL'=>'https://fitness.example.test', 'DEMO_ADMIN_PASSWORD'=>$password];
        seed_demo_administrator();
        expect($GLOBALS['administrator'] === $before && $GLOBALS['writes'] === []);
    }
});
check('Completed demonstration seed does not reset credentials on redeploy', function (): void {
    $before = $GLOBALS['administrator'];
    $GLOBALS['seedComplete'] = true;
    $GLOBALS['writes'] = [];
    $GLOBALS['configuration'] = ['APP_URL'=>'https://fitness.example.test'];
    seed_demonstrations();
    expect($GLOBALS['administrator'] === $before && $GLOBALS['writes'] === []);
});
check('Compose passes the optional private initial secret and template is empty', function (): void {
    $root = dirname(__DIR__);
    expect(str_contains(file_get_contents($root.'/docker-compose.yml'), 'DEMO_ADMIN_PASSWORD: ${DEMO_ADMIN_PASSWORD:-}'));
    expect(preg_match('/^DEMO_ADMIN_PASSWORD=\r?$/m',file_get_contents($root.'/.env.example')) === 1);
});
echo json_encode(['checks'=>$results, 'passed'=>count(array_filter($results,fn($r)=>$r['status']==='PASS')), 'total'=>count($results)], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit(count(array_filter($results,fn($r)=>$r['status']!=='PASS')) ? 1 : 0);
