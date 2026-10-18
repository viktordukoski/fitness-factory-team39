<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (PHP_SAPI !== 'cli' || !str_ends_with(env('DB_NAME'),'_test')) {
    fwrite(STDERR,"Run only against an isolated database whose name ends in _test.\n"); exit(2);
}
function concurrent_pair(string $mode, array $users, int $recordId, string $key): array
{
    $gate = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ff-barrier-' . bin2hex(random_bytes(8));
    $workers=[];
    foreach ($users as $index=>$user) {
        $ready=$gate.'.'.$index.'.ready';
        $process=proc_open([PHP_BINARY,__DIR__.'/concurrency-worker.php',$mode,(string)$user,(string)$recordId,$key,$gate,$ready],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,APP_ROOT,null,['bypass_shell'=>true]);
        if (!is_resource($process)) throw new RuntimeException('Could not launch worker');
        fclose($pipes[0]); $workers[]=['process'=>$process,'pipes'=>$pipes,'ready'=>$ready];
    }
    $deadline=microtime(true)+10;
    while ((!is_file($workers[0]['ready']) || !is_file($workers[1]['ready'])) && microtime(true)<$deadline) usleep(10000);
    if (!is_file($workers[0]['ready']) || !is_file($workers[1]['ready'])) throw new RuntimeException('Workers failed to reach barrier');
    file_put_contents($gate,'go');
    $results=[];
    foreach ($workers as $worker) {
        $out=stream_get_contents($worker['pipes'][1]); $err=stream_get_contents($worker['pipes'][2]);
        fclose($worker['pipes'][1]);fclose($worker['pipes'][2]);$exit=proc_close($worker['process']);
        if ($exit!==0 || $err!=='') throw new RuntimeException('Worker failed: '.$err);
        $results[]=json_decode($out,true,512,JSON_THROW_ON_ERROR);
        unlink($worker['ready']);
    }
    unlink($gate); return $results;
}
$checks=[];$sessionId=0;$orderId=0;
try {
    $member=(int)fetch_one("SELECT id FROM users WHERE email='member@demo.test'")['id'];
    $admin=(int)fetch_one("SELECT id FROM users WHERE email='admin@demo.test'")['id'];
    $base=fetch_one('SELECT class_type_id,trainer_id,room_id FROM class_sessions LIMIT 1');
    execute('INSERT INTO class_sessions(class_type_id,trainer_id,room_id,starts_at,ends_at,capacity,requires_membership) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 49 HOUR),1,0)',array_values($base));
    $sessionId=(int)db()->lastInsertId();
    $workers=concurrent_pair('booking',[$member,$admin],$sessionId,'unused');
    $booked=(int)fetch_one('SELECT COUNT(*) n FROM bookings WHERE session_id=? AND status=\'confirmed\'',[$sessionId])['n'];
    $success=count(array_filter($workers,fn($r)=>$r['status']==='success'));
    $full=count(array_filter($workers,fn($r)=>($r['reason']??'')==='class_full'));
    $pass=$booked===1 && $success===1 && $full===1;
    $checks[]=['test'=>'Two independent simultaneous connections compete for final place','status'=>$pass?'PASS':'NEEDS FIX','workers'=>$workers,'confirmed_count'=>$booked];
    $key='concurrency-'.bin2hex(random_bytes(12));
    $plan=(int)fetch_one("SELECT id FROM membership_plans WHERE slug='month-full'")['id'];
    $workers=concurrent_pair('checkout',[$member,$member],$plan,$key);
    $order=fetch_one('SELECT id FROM orders WHERE user_id=? AND idempotency_key=?',[$member,$key]);$orderId=(int)($order['id']??0);
    $counts=fetch_one('SELECT (SELECT COUNT(*) FROM orders WHERE user_id=? AND idempotency_key=?) orders,(SELECT COUNT(*) FROM memberships WHERE order_id=?) memberships,(SELECT COUNT(*) FROM passes p JOIN memberships m ON m.id=p.membership_id WHERE m.order_id=?) passes',[$member,$key,$orderId,$orderId]);
    $pass=count(array_filter($workers,fn($r)=>$r['status']==='success' && $r['id']===$orderId))===2 && array_values($counts)==[1,1,1];
    $checks[]=['test'=>'Simultaneous repeated checkout creates one order, membership and pass','status'=>$pass?'PASS':'NEEDS FIX','workers'=>$workers,'database_counts'=>$counts];
    $tables=['users','content','content_translations','class_sessions','bookings','membership_plans','orders','memberships','passes'];
    $before=[];foreach($tables as $table)$before[$table]=(int)fetch_one('SELECT COUNT(*) n FROM '.$table)['n'];
    $process=proc_open([PHP_BINARY,APP_ROOT.'/bin/migrate.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,APP_ROOT,null,['bypass_shell'=>true]);
    fclose($pipes[0]);$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    $after=[];foreach($tables as $table)$after[$table]=(int)fetch_one('SELECT COUNT(*) n FROM '.$table)['n'];
    $checks[]=['test'=>'Subsequent migration preserves all content and transaction row counts','status'=>$exit===0 && $before===$after?'PASS':'NEEDS FIX','before'=>$before,'after'=>$after,'migration_output'=>trim($output),'migration_error'=>trim($error)];
} catch (Throwable $error) { $checks[]=['test'=>'Concurrency harness','status'=>'NEEDS FIX','reason'=>$error->getMessage()]; }
finally {
    if($sessionId){execute('DELETE FROM bookings WHERE session_id=?',[$sessionId]);execute('DELETE FROM class_sessions WHERE id=?',[$sessionId]);}
    if($orderId){execute('DELETE p FROM passes p JOIN memberships m ON m.id=p.membership_id WHERE m.order_id=?',[$orderId]);execute('DELETE FROM memberships WHERE order_id=?',[$orderId]);execute('DELETE FROM demo_payments WHERE order_id=?',[$orderId]);execute('DELETE FROM order_items WHERE order_id=?',[$orderId]);execute('DELETE FROM orders WHERE id=?',[$orderId]);}
}
echo json_encode(['runtime'=>PHP_VERSION,'checks'=>$checks,'passed'=>count(array_filter($checks,fn($r)=>$r['status']==='PASS')),'total'=>count($checks)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit(count(array_filter($checks,fn($r)=>$r['status']!=='PASS'))?1:0);
