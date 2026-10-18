<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(PHP_SAPI!=='cli'||!str_ends_with(env('DB_NAME'),'_test')){fwrite(STDERR,"Use an empty isolated database ending in _test.\n");exit(2);}
if(fetch_all('SHOW TABLES')){fwrite(STDERR,"This check requires an empty test database.\n");exit(2);}
$checks=[];
function fresh_migrate(): array {
    $process=proc_open([PHP_BINARY,APP_ROOT.'/bin/migrate.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,APP_ROOT,null,['bypass_shell'=>true]);
    if(!is_resource($process))throw new RuntimeException('Could not start migration process');
    fclose($pipes[0]);$output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    return [proc_close($process),$output,$errors];
}
function fresh_expect(string $name,bool $condition): void { $GLOBALS['checks'][]=['test'=>$name,'status'=>$condition?'PASS':'NEEDS FIX']; }
$previousOrigin=env('APP_URL');$previousPassword=getenv('DEMO_ADMIN_PASSWORD');
try {
    putenv('APP_URL=https://fresh-install.example.test');putenv('DEMO_ADMIN_PASSWORD');
    [$exit,$output,$errors]=fresh_migrate();
    fresh_expect('Fresh hosted install without a private admin password fails closed',$exit!==0 && !fetch_one("SELECT id FROM users WHERE role_id=1"));
    $password=bin2hex(random_bytes(24));putenv('DEMO_ADMIN_PASSWORD='.$password);
    [$exit,$output,$errors]=fresh_migrate();
    $administrator=fetch_one("SELECT id,password_hash FROM users WHERE email='admin@demo.test'");
    fresh_expect('Retry completes real schema and hosted seeding with a private hash',$exit===0 && $administrator && password_verify($password,$administrator['password_hash']) && !password_verify('FactoryAdmin!2026',$administrator['password_hash']));
    fresh_expect('Initialization output does not contain the supplied password',!str_contains($output.$errors,$password));
    $tables=['users','content','content_translations','membership_plans','class_sessions','orders','memberships','passes','bookings'];$before=[];
    foreach($tables as $table)$before[$table]=(int)fetch_one('SELECT COUNT(*) n FROM '.$table)['n'];
    putenv('DEMO_ADMIN_PASSWORD');[$exit]=fresh_migrate();$after=[];
    foreach($tables as $table)$after[$table]=(int)fetch_one('SELECT COUNT(*) n FROM '.$table)['n'];
    fresh_expect('Later migrations preserve seeded row counts and private administrator hash',$exit===0 && $before===$after && fetch_one("SELECT password_hash FROM users WHERE email='admin@demo.test'")['password_hash']===$administrator['password_hash']);
    fresh_expect('All ten sourced prices survive first-install retry',array_map(fn($row)=>(int)$row['price'],fetch_all('SELECT price FROM membership_plans ORDER BY id'))===[300,1000,1600,2000,4350,5400,7700,9600,14400,18000]);
} catch(Throwable $error) { $checks[]=['test'=>'Fresh database installation','status'=>'NEEDS FIX','reason'=>get_class($error)]; }
finally { putenv('APP_URL='.$previousOrigin);putenv($previousPassword===false?'DEMO_ADMIN_PASSWORD':'DEMO_ADMIN_PASSWORD='.$previousPassword); }
echo json_encode(['checks'=>$checks,'passed'=>count(array_filter($checks,fn($check)=>$check['status']==='PASS')),'total'=>count($checks)],JSON_PRETTY_PRINT)."\n";
exit(count(array_filter($checks,fn($check)=>$check['status']!=='PASS'))?1:0);
