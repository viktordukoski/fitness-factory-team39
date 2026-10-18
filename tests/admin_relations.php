<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (PHP_SAPI !== 'cli' || !str_ends_with(env('DB_NAME'), '_test')) {
    fwrite(STDERR, "Run only against an isolated database whose name ends in _test.\n"); exit(2);
}
require APP_ROOT . '/app/admin_forms.php';

// Capture the actual admin controller's view data without a browser or alternate form implementation.
function input(string $key, string $default = '', ?array $from = null): string
{
    $value = ($from ?? $_REQUEST)[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}
function render(string $view, array $data = []): void { $GLOBALS['rendered'] = $data; }
function expect(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function check(string $name, callable $work): void
{
    try { $work(); $GLOBALS['results'][] = ['test'=>$name, 'status'=>'PASS']; }
    catch (Throwable $error) { $GLOBALS['results'][] = ['test'=>$name, 'status'=>'NEEDS FIX', 'reason'=>$error->getMessage()]; }
}
function edit_form(int $id): array
{
    $_GET = ['section'=>'content','edit'=>(string)$id,'lang'=>'en'];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    display_admin(['errors'=>[]]);
    return $GLOBALS['rendered']['record'];
}
function relation_ids(int $id, string $kind): array
{
    $column = $kind === 'trainer' ? 'service_id' : 'trainer_id';
    $owner = $kind === 'trainer' ? 'trainer_id' : 'service_id';
    return array_map('intval', array_column(fetch_all('SELECT '.$column.' FROM trainer_services WHERE '.$owner.'=? ORDER BY '.$column, [$id]), $column));
}

$results = []; $ids = []; $owners = [];
$suffix = bin2hex(random_bytes(6));
$admin = (int)(fetch_one("SELECT id FROM users WHERE email='admin@demo.test'")['id'] ?? 0);
$_SESSION = ['user_id'=>$admin]; $_POST = []; $_GET = ['lang'=>'en'];
try {
    expect($admin > 0, 'Seeded isolated administrator is required.');
    foreach (['trainer','service'] as $kind) {
        foreach (['owner','draft','archived','unlinked-archived'] as $state) {
            execute('INSERT INTO content(kind,slug,published,archived,is_demo,metadata) VALUES(?,?,?,?,1,?)', [$kind,'relations-'.$suffix.'-'.$kind.'-'.$state,$state==='owner'?1:0,str_contains($state,'archived')?1:0,'{}']);
            $id = (int)db()->lastInsertId(); $ids[$kind][$state] = $id;
            execute('INSERT INTO content_translations(content_id,language,title,summary,body,alt) VALUES(?,\'en\',?,?,?,?)', [$id,'Relation fixture '.$kind.' '.$state,'Fixture summary','Fixture body','']);
        }
    }
    foreach (['draft','archived'] as $state) {
        execute('INSERT INTO trainer_services(trainer_id,service_id) VALUES(?,?)', [$ids['trainer']['owner'],$ids['service'][$state]]);
        execute('INSERT INTO trainer_services(trainer_id,service_id) VALUES(?,?)', [$ids['trainer'][$state],$ids['service']['owner']]);
    }
    foreach (['trainer','service'] as $kind) {
        $owner = $ids[$kind]['owner']; $targetKind = $kind === 'trainer' ? 'service' : 'trainer';
        $field = $kind === 'trainer' ? 'related_service_ids' : 'related_trainer_ids';
        check(ucfirst($kind).' edit and unchanged save retain draft and archived links', function () use ($admin,$owner,$kind,$field,$ids,$targetKind): void {
            $expected = [$ids[$targetKind]['draft'],$ids[$targetKind]['archived']]; sort($expected);
            $form = edit_form($owner);
            $shown = array_map('intval', explode(',', $form[$field])); sort($shown);
            expect($shown === $expected, 'Admin form omitted non-public relation IDs.');
            admin_save_content($admin,$owner,$form);
            expect(relation_ids($owner,$kind) === $expected, 'An unchanged save lost a relation.');
        });
        check(ucfirst($kind).' cannot add a new link to archived content', function () use ($admin,$owner,$kind,$field,$ids,$targetKind): void {
            $before = relation_ids($owner,$kind); $form = edit_form($owner);
            $form[$field] .= ','.$ids[$targetKind]['unlinked-archived'];
            try { admin_save_content($admin,$owner,$form); throw new LogicException('New archived link was accepted.'); }
            catch (RuntimeException $error) { expect($error->getMessage() === 'validation', 'Unexpected rejection.'); }
            expect(relation_ids($owner,$kind) === $before, 'Rejected save changed existing relations.');
        });
    }
    check('Public related directories still hide draft and archived records', function () use ($ids): void {
        expect(related_services($ids['trainer']['owner']) === [], 'Draft/archived service became public.');
        expect(related_trainers($ids['service']['owner']) === [], 'Draft/archived trainer became public.');
    });
    check('An administrator can explicitly remove a historical relation', function () use ($admin,$ids): void {
        $owner = $ids['trainer']['owner']; $form = edit_form($owner);
        $form['related_service_ids'] = (string)$ids['service']['draft'];
        admin_save_content($admin,$owner,$form);
        expect(relation_ids($owner,'trainer') === [$ids['service']['draft']], 'Explicit removal failed.');
    });
} finally {
    $allIds = array_merge(array_values($ids['trainer'] ?? []),array_values($ids['service'] ?? []));
    foreach ($allIds as $id) {
        execute('DELETE FROM trainer_services WHERE trainer_id=? OR service_id=?',[$id,$id]);
        execute('DELETE FROM admin_audit_log WHERE entity_type=\'content\' AND entity_id=?',[(string)$id]);
        execute('DELETE FROM content_translations WHERE content_id=?',[$id]);
        execute('DELETE FROM content WHERE id=?',[$id]);
    }
}
echo json_encode(['checks'=>$results,'passed'=>count(array_filter($results,fn($r)=>$r['status']==='PASS')),'total'=>count($results)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit(count(array_filter($results,fn($r)=>$r['status']!=='PASS')) ? 1 : 0);
