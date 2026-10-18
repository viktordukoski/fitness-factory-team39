<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (PHP_SAPI !== 'cli' || !str_ends_with(env('DB_NAME'), '_test')) {
    fwrite(STDERR, "Run exclusively against an isolated database whose name ends in _test.\n"); exit(2);
}

// Child processes exercise the real domain action with independent connections.
if (($argv[1] ?? '') === '--worker') {
    [,,$actor,$target,$gate,$ready] = $argv;
    db();
    file_put_contents($ready, 'ready');
    $deadline = microtime(true) + 15;
    while (!is_file($gate) && microtime(true) < $deadline) usleep(10000);
    if (!is_file($gate)) { echo json_encode(['status'=>'error','reason'=>'barrier_timeout']); exit(1); }
    try {
        admin_status((int)$actor, 'users', (int)$target, 'inactive');
        echo json_encode(['status'=>'success']);
    } catch (Throwable $error) {
        echo json_encode(['status'=>'rejected','reason'=>$error->getMessage()]);
    }
    exit;
}

function safety_expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function safety_reject(callable $work, string $reason): void
{
    try { $work(); }
    catch (RuntimeException $error) {
        safety_expect($error->getMessage() === $reason, 'Expected '.$reason.', received '.$error->getMessage());
        return;
    }
    throw new RuntimeException('Expected rejection: '.$reason);
}
function safety_pair(int $first, int $second): array
{
    $gate = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ff-admin-gate-'.bin2hex(random_bytes(8));
    $workers = [];
    try {
        foreach ([[$first,$second],[$second,$first]] as $index=>[$actor,$target]) {
            $ready = $gate.'.'.$index.'.ready';
            $process = proc_open([PHP_BINARY,__FILE__,'--worker',(string)$actor,(string)$target,$gate,$ready], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, APP_ROOT, null, ['bypass_shell'=>true]);
            if (!is_resource($process)) throw new RuntimeException('Could not launch administrator test worker');
            fclose($pipes[0]);
            $workers[] = ['process'=>$process,'pipes'=>$pipes,'ready'=>$ready];
        }
        $deadline = microtime(true) + 10;
        while ((!is_file($workers[0]['ready']) || !is_file($workers[1]['ready'])) && microtime(true) < $deadline) usleep(10000);
        safety_expect(is_file($workers[0]['ready']) && is_file($workers[1]['ready']), 'Workers failed to reach start barrier');
        file_put_contents($gate, 'go');
        $results = [];
        foreach ($workers as &$worker) {
            $output = stream_get_contents($worker['pipes'][1]);
            $error = stream_get_contents($worker['pipes'][2]);
            fclose($worker['pipes'][1]); fclose($worker['pipes'][2]);
            $exit = proc_close($worker['process']);
            $worker['process'] = null;
            safety_expect($exit === 0 && $error === '', 'Administrator worker failed: '.$error);
            $results[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        }
        unset($worker);
        return $results;
    } finally {
        foreach ($workers as $worker) {
            if (is_resource($worker['process'])) { proc_terminate($worker['process']); proc_close($worker['process']); }
            if (is_file($worker['ready'])) unlink($worker['ready']);
        }
        if (is_file($gate)) unlink($gate);
    }
}

$checks = []; $fixtures = []; $originalAdmins = []; $trigger = ''; $rolesChanged = false;
try {
    $originalAdmins = fetch_all('SELECT id,active FROM users WHERE role_id=1 ORDER BY id');
    $suffix = bin2hex(random_bytes(8));
    $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
    foreach (['first','second'] as $label) {
        execute('INSERT INTO users(role_id,name,email,password_hash,active) VALUES(1,?,?,?,1)', ['Admin safety '.$label,'admin-safety-'.$label.'-'.$suffix.'@example.test',$hash]);
        $fixtures[] = (int)db()->lastInsertId();
    }
    [$first,$second] = $fixtures;
    // This suite requires exclusive use of its disposable database. Restore all states below.
    execute('UPDATE users SET active=0 WHERE role_id=1 AND id NOT IN (?,?)', [$first,$second]);
    $rolesChanged = true;

    safety_reject(fn()=>admin_status($first, 'users', $first, 'inactive'), 'last_admin');
    safety_expect((int)fetch_one('SELECT active FROM users WHERE id=?',[$first])['active'] === 1, 'Self-deactivation changed the administrator');
    $checks[] = ['test'=>'An administrator cannot deactivate their own account','status'=>'PASS'];

    admin_status($first, 'users', $second, 'inactive');
    safety_reject(fn()=>admin_status($second, 'users', $first, 'inactive'), 'account_inactive');
    safety_expect((int)fetch_one('SELECT COUNT(*) n FROM users WHERE role_id=1 AND active=1')['n'] === 1, 'Sequential deactivation lost the final administrator');
    $checks[] = ['test'=>'An inactive administrator cannot deactivate the surviving administrator','status'=>'PASS'];
    execute('UPDATE users SET active=1 WHERE id IN (?,?)', [$first,$second]);

    // Requires TRIGGER privilege on this disposable database only, never in hosting.
    // Delay each deactivation at its final UPDATE. In the unfixed code both workers
    // pass the unlocked count while holding different target rows; audit foreign
    // keys can then deadlock. A shared lock makes the second worker recheck its actor.
    $trigger = 'ff_admin_safety_'.$suffix;
    execute('CREATE TRIGGER '.$trigger.' BEFORE UPDATE ON users FOR EACH ROW BEGIN IF OLD.id IN ('.$first.','.$second.') AND OLD.active=1 AND NEW.active=0 THEN DO SLEEP(0.75); END IF; END');
    $rounds = [];
    for ($round=0; $round<3; $round++) {
        execute('UPDATE users SET active=1 WHERE id IN (?,?)', [$first,$second]);
        $workers = safety_pair($first,$second);
        $active = (int)fetch_one('SELECT COUNT(*) n FROM users WHERE role_id=1 AND active=1')['n'];
        $success = count(array_filter($workers,fn($r)=>$r['status']==='success'));
        $inactive = count(array_filter($workers,fn($r)=>$r['status']==='rejected' && ($r['reason']??'')==='account_inactive'));
        $rounds[] = ['workers'=>$workers,'active_administrators'=>$active];
        safety_expect($active===1 && $success===1 && $inactive===1, 'Concurrent cross-deactivation must preserve one active administrator and reject the disabled actor: '.json_encode(end($rounds)));
    }
    $checks[] = ['test'=>'Concurrent cross-deactivation preserves the final administrator in three independent races','status'=>'PASS','rounds'=>$rounds];
} catch (Throwable $error) {
    $checks[] = ['test'=>'Administrator safety regression','status'=>'NEEDS FIX','reason'=>$error->getMessage()];
} finally {
    if ($trigger !== '') execute('DROP TRIGGER IF EXISTS '.$trigger);
    if ($rolesChanged) foreach ($originalAdmins as $administrator) execute('UPDATE users SET active=? WHERE id=?', [$administrator['active'],$administrator['id']]);
    foreach ($fixtures as $id) {
        execute('DELETE FROM admin_audit_log WHERE admin_id=?', [$id]);
        execute('DELETE FROM users WHERE id=?', [$id]);
    }
}
echo json_encode(['runtime'=>PHP_VERSION,'checks'=>$checks,'passed'=>count(array_filter($checks,fn($r)=>$r['status']==='PASS')),'total'=>count($checks)], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit(count(array_filter($checks,fn($r)=>$r['status']!=='PASS')) ? 1 : 0);
