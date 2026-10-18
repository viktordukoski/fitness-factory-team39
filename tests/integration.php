<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (PHP_SAPI !== 'cli' || !str_ends_with(env('DB_NAME'), '_test')) {
    fwrite(STDERR, "Run only against an isolated database whose name ends in _test.\n"); exit(2);
}
$_SESSION=[]; $_GET=[];
$results=[];
function check(string $name, callable $test): void {
    global $results;
    try { $test(); $results[]=['test'=>$name,'status'=>'PASS']; }
    catch(Throwable $error) { $results[]=['test'=>$name,'status'=>'NEEDS FIX','reason'=>$error->getMessage()]; }
}
function expect(bool $condition,string $message='Assertion failed'): void { if(!$condition) throw new RuntimeException($message); }
function rejected(callable $action, string $reason): void {
    try { $action(); } catch(RuntimeException $error) { expect($error->getMessage()===$reason,'Expected '.$reason.', got '.$error->getMessage()); return; }
    throw new RuntimeException('Expected rejection: '.$reason);
}
$suffix=bin2hex(random_bytes(5));
$_SERVER['REMOTE_ADDR']='test-'.$suffix;
$admin=(int)(fetch_one("SELECT id FROM users WHERE email='admin@demo.test'")['id'] ?? 0);
$member=0;$order=0;$testSession=0;$booking=0;$newContent=0;
check('Ten sourced package prices and access windows',function(){
    $rows=plans(); expect(count($rows)>=10);
    expect(array_map(fn($r)=>(int)$r['price'],array_slice($rows,0,10))===[300,1000,1600,2000,4350,5400,7700,9600,14400,18000]);
    expect($rows[0]['access_start']===null && $rows[1]['access_end']===null);
    expect($rows[2]['access_start']==='06:00:00'&&$rows[2]['access_end']==='15:00:00');
});
check('Opening-hour intersection including weekend limits',function(){
    expect(effective_access(['access_start'=>'06:00:00','access_end'=>'22:00:00'],6)===['08:00:00','22:00:00']);
    expect(effective_access(['access_start'=>'06:00:00','access_end'=>'22:00:00'],7)===['09:00:00','21:00:00']);
    expect(effective_access(['access_start'=>'06:00:00','access_end'=>'15:00:00'],7)===['09:00:00','15:00:00']);
    expect(effective_access(['access_start'=>null,'access_end'=>null],1)===null);
});
check('Registration password hashing and duplicate rejection',function()use(&$member,$suffix){
    $member=register_user('Test Member','integration-'.$suffix.'@example.test','TestingFactory!2026','mk');
    $row=fetch_one('SELECT * FROM users WHERE id=?',[$member]);
    expect(password_verify('TestingFactory!2026',$row['password_hash'])); expect($row['password_hash']!=='TestingFactory!2026');
    rejected(fn()=>register_user('Test Member','integration-'.$suffix.'@example.test','TestingFactory!2026'),'email_exists');
    rejected(fn()=>register_user('Test Member','invalid','TestingFactory!2026'),'email_invalid');
});
check('Login, role authorization, profile, password and logout',function()use(&$member,$suffix){
    rejected(fn()=>login_user('integration-'.$suffix.'@example.test','wrong'),'login_failed');
    login_user('integration-'.$suffix.'@example.test','TestingFactory!2026');expect(current_user()['id']===$member);
    rejected(fn()=>require_admin(),'forbidden');
    save_profile($member,['name'=>'Updated Test Member','language'=>'de']);expect(current_user()['name']==='Updated Test Member');
    change_password($member,'TestingFactory!2026','TestingFactory!New2026');
    logout_user();expect(current_user()===null);login_user('integration-'.$suffix.'@example.test','TestingFactory!New2026');
});
check('Successful checkout, immutable price and one digital pass',function()use(&$order,&$member,$suffix){
    $plan=plans()[3];$key='integration-success-'.$suffix;
    $order=create_order($member,(int)$plan['id'],'card','success',$key);
    expect(order_item($order,$member)['status']==='paid');expect((int)order_item($order,$member)['total']===2000);
    $again=create_order($member,(int)$plan['id'],'card','success',$key);expect($again===$order);
    execute('UPDATE membership_plans SET price=price+1 WHERE id=?',[$plan['id']]);
    try { expect((int)order_item($order,$member)['total']===2000);expect((int)fetch_one('SELECT price_snapshot FROM order_items WHERE order_id=?',[$order])['price_snapshot']===2000); }
    finally { execute('UPDATE membership_plans SET price=? WHERE id=?',[$plan['price'],$plan['id']]); }
    expect((int)fetch_one('SELECT COUNT(*) n FROM memberships WHERE order_id=?',[$order])['n']===1);
    $pass=fetch_one('SELECT p.* FROM passes p JOIN memberships m ON m.id=p.membership_id WHERE m.order_id=?',[$order]);
    expect($pass!==null);$verified=verify_pass($pass['token']);expect($verified['status']==='active');expect(!array_key_exists('name',$verified));
});
check('Pending and failed payment entitlement boundaries + admin confirmation',function()use(&$member,$admin,$suffix){
    $planId=(int)plans()[3]['id'];
    $failed=create_order($member,$planId,'card','failure','integration-failure-'.$suffix);
    expect(order_item($failed,$member)['status']==='failed');expect(fetch_one('SELECT id FROM memberships WHERE order_id=?',[$failed])===null);
    $pending=create_order($member,$planId,'bank','success','integration-pending-'.$suffix);
    expect(order_item($pending,$member)['status']==='pending');
    expect(fetch_one('SELECT status FROM memberships WHERE order_id=?',[$pending])['status']==='pending');
    expect(fetch_one('SELECT p.id FROM passes p JOIN memberships m ON m.id=p.membership_id WHERE m.order_id=?',[$pending])===null);
    confirm_order($admin,$pending,'paid');confirm_order($admin,$pending,'paid');
    expect((int)fetch_one('SELECT COUNT(*) n FROM passes p JOIN memberships m ON m.id=p.membership_id WHERE m.order_id=?',[$pending])['n']===1);
    rejected(fn()=>confirm_order($admin,$pending,'failed'),'order_transition');
    rejected(fn()=>confirm_order($member,$pending,'paid'),'forbidden');
});
check('Owned orders and private records inaccessible to other account',function()use(&$member,&$order,$admin){
    expect(order_item($order,$admin)===null);expect(count(user_orders($member))===3);
    expect(verify_pass('invalid')===null);
});
check('Six database locales have actual translations',function(){
    $slugs=array_column(require APP_ROOT.'/database/content-data.php',1);
    $rows=fetch_all('SELECT tr.language,COUNT(*) n FROM content_translations tr JOIN content c ON c.id=tr.content_id WHERE c.slug IN ('.implode(',',array_fill(0,count($slugs),'?')).') GROUP BY tr.language',$slugs);expect(count($rows)===6);
    $counts=array_column($rows,'n');expect(count(array_unique($counts))===1);
    foreach(LANGUAGES as $language){$_GET['lang']=$language;expect(count(content('service'))>0);expect(t('home')!=='');}
    $_GET=[];$_SESSION['lang']='en';
});
check('Favorites add/remove, uniqueness and ownership',function()use(&$member){
    $id=(int)content('service')[0]['id'];expect(toggle_favorite($member,$id));expect(count(user_favorites($member))===1);
    expect(!toggle_favorite($member,$id));expect(count(user_favorites($member))===0);
    rejected(fn()=>toggle_favorite($member,(int)content('news')[0]['id']),'not_found');
});
check('Contact validation and stored inquiry, no mail claim',function()use($suffix){
    rejected(fn()=>save_contact(['name'=>'T','email'=>'bad','subject'=>'x','message'=>'x']),'validation');
    $id=save_contact(['name'=>'Test Visitor','email'=>'visitor@example.test','subject'=>'Access inquiry '.$suffix,'message'=>'Please confirm current step-free access.']);
    expect(fetch_one('SELECT status FROM contact_messages WHERE id=?',[$id])['status']==='new');
});
check('Newsletter consent, duplicate and token unsubscribe',function()use($suffix){
    $email='newsletter-'.$suffix.'@example.test';rejected(fn()=>subscribe_newsletter($email,false),'consent_required');
    $subscription=subscribe_newsletter($email,true);rejected(fn()=>subscribe_newsletter($email,true),'already_subscribed');
    unsubscribe_newsletter($subscription['token']);expect(fetch_one('SELECT status FROM newsletter_subscribers WHERE email=?',[$email])['status']==='unsubscribed');
});
check('Admin CRUD, publication and archival on public query',function()use($admin,&$newContent,$suffix){
    $newContent=admin_save($admin,'content',0,['kind'=>'service','slug'=>'integration-'.$suffix,'title'=>'Integration strength','summary'=>'A distinct test programme.','body'=>'This is a fictional integration test.','language'=>'en','category'=>'strength','is_demo'=>1,'published'=>0,'equipment'=>'Demo mat','duration_minutes'=>45,'related_trainer_ids'=>(string)content('trainer')[0]['id']]);
    expect(content_item($newContent)===null);
    admin_save($admin,'content',$newContent,['kind'=>'service','slug'=>'integration-'.$suffix,'title'=>'Integration strength','summary'=>'A distinct test programme.','body'=>'This is a fictional integration test.','language'=>'en','category'=>'strength','is_demo'=>1,'published'=>1]);
    expect(content_item($newContent)['title']==='Integration strength');
    expect(content_item($newContent)['metadata']['equipment']==='Demo mat');expect(count(related_trainers($newContent))===1);
    admin_archive($admin,'content',$newContent);expect(content_item($newContent)===null);
});
check('Create session, book, reject duplicate, preserve capacity',function()use($admin,&$member,&$testSession,&$booking){
    $local=(new DateTimeImmutable('tomorrow',new DateTimeZone('Europe/Skopje')))->setTime(10,0);
    $testSession=admin_save($admin,'sessions',0,['class_type_id'=>content('class_type')[0]['id'],'trainer_id'=>content('trainer')[0]['id'],'room_id'=>fetch_one('SELECT id FROM rooms LIMIT 1')['id'],'starts_at'=>$local->format('Y-m-d\TH:i'),'ends_at'=>$local->modify('+1 hour')->format('Y-m-d\TH:i'),'capacity'=>1,'cancellation_minutes'=>60,'requires_membership'=>1,'status'=>'scheduled']);
    $booking=reserve_class($member,$testSession);expect(booking_item($booking,$member)['status']==='confirmed');
    rejected(fn()=>reserve_class($member,$testSession),'already_booked');
    rejected(fn()=>reserve_class($admin,$testSession),'membership_required');
});
check('Reschedule/cancel session produces member-visible changes and audit',function()use($admin,&$member,&$testSession,&$booking){
    $row=session_item($testSession);
    $data=$row;$data['starts_at']=local_time($row['starts_at'],'Y-m-d\TH:i');$data['ends_at']=local_time($row['ends_at'],'Y-m-d\TH:i');$data['capacity']=0;
    rejected(fn()=>admin_save($admin,'sessions',$testSession,$data),'validation');
    $data['capacity']=1;$data['starts_at']=(new DateTimeImmutable($data['starts_at']))->modify('+1 hour')->format('Y-m-d\TH:i');$data['ends_at']=(new DateTimeImmutable($data['ends_at']))->modify('+1 hour')->format('Y-m-d\TH:i');$data['change_note']='Integration reschedule';
    admin_save($admin,'sessions',$testSession,$data);expect(booking_item($booking,$member)['change_note']==='Integration reschedule');
    cancel_booking($member,$booking);expect(booking_item($booking,$member)['status']==='cancelled');
    reserve_class($member,$testSession);admin_status($admin,'sessions',$testSession,'cancelled');
    expect(booking_item($booking,$member)['status']==='cancelled');rejected(fn()=>reserve_class($member,$testSession),'session_unavailable');
});
check('Past and full-session rejection',function()use(&$member,$admin,$suffix){
    $base=fetch_one('SELECT class_type_id,trainer_id,room_id FROM class_sessions LIMIT 1');
    execute("INSERT INTO class_sessions(class_type_id,trainer_id,room_id,starts_at,ends_at,capacity,requires_membership) VALUES(?,?,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 HOUR),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR),1,0)",array_values($base));
    $past=(int)db()->lastInsertId();rejected(fn()=>reserve_class($member,$past),'session_past');
    execute("INSERT INTO class_sessions(class_type_id,trainer_id,room_id,starts_at,ends_at,capacity,requires_membership) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 49 HOUR),1,0)",array_values($base));
    $full=(int)db()->lastInsertId();reserve_class($admin,$full);rejected(fn()=>reserve_class($member,$full),'class_full');
    $_SESSION['user_id']=$admin;
    $row=admin_record('sessions',$full);$row['starts_at']=local_time($row['starts_at'],'Y-m-d\TH:i');$row['ends_at']=local_time($row['ends_at'],'Y-m-d\TH:i');
    expect((int)$row['booked']===1);
});
check('CSRF enforcement and defensive upload rejection',function()use($admin){
    rejected(fn()=>verify_csrf('bad-token'),'csrf_invalid');verify_csrf(csrf_token());
    $_SESSION['user_id']=$admin;rejected(fn()=>save_image_upload(['name'=>'x.php','tmp_name'=>__FILE__,'error'=>0,'size'=>100]),'upload_invalid');
});
check('Admin cannot reduce capacity below two confirmed bookings',function()use($admin,&$member){
    $local=(new DateTimeImmutable('tomorrow',new DateTimeZone('Europe/Skopje')))->setTime(12,0);
    $data=['class_type_id'=>content('class_type')[0]['id'],'trainer_id'=>content('trainer')[0]['id'],'room_id'=>fetch_one('SELECT id FROM rooms LIMIT 1')['id'],'starts_at'=>$local->format('Y-m-d\TH:i'),'ends_at'=>$local->modify('+1 hour')->format('Y-m-d\TH:i'),'capacity'=>2,'cancellation_minutes'=>60,'requires_membership'=>0,'status'=>'scheduled'];
    $id=admin_save($admin,'sessions',0,$data);reserve_class($member,$id);reserve_class($admin,$id);
    $data['capacity']=1;rejected(fn()=>admin_save($admin,'sessions',$id,$data),'capacity_conflict');expect(session_item($id)['capacity']===2);
    admin_status($admin,'sessions',$id,'cancelled');
});
check('CMS validates hours and changes effective weekend access',function()use($admin){
    $before=site_setting('opening_hours');
    rejected(fn()=>admin_save($admin,'settings',0,['setting_key'=>'opening_hours','value'=>'{"saturday":"25:99–26:99"}']),'validation');
    $changed=json_decode($before,true);$changed['saturday']='10:00–20:00';
    try {
        admin_save($admin,'settings',0,['setting_key'=>'opening_hours','value'=>json_encode($changed,JSON_UNESCAPED_UNICODE)]);
        expect(effective_access(['access_start'=>'06:00:00','access_end'=>'22:00:00'],6)===['10:00:00','20:00:00']);
    } finally { admin_save($admin,'settings',0,['setting_key'=>'opening_hours','value'=>$before]); }
});
check('Published search and category filtering',function(){
    expect(count(content('service',['q'=>'zzzznonexistent']))===0);
    foreach(content('service',['category'=>'strength']) as $row) expect($row['category']==='strength');
});
check('Inactive user cannot authenticate or use entitlement',function()use($admin,&$member,$suffix){
    $pass=fetch_one('SELECT p.token FROM passes p JOIN memberships m ON m.id=p.membership_id WHERE m.user_id=? LIMIT 1',[$member]);
    admin_status($admin,'users',$member,'inactive');rejected(fn()=>login_user('integration-'.$suffix.'@example.test','TestingFactory!New2026'),'login_failed');
    rejected(fn()=>create_order($member,1,'card','success','inactive-'.$suffix.'-key'),'account_inactive');
    expect(verify_pass($pass['token'])===null);
});
echo json_encode(['runtime'=>PHP_VERSION,'checks'=>$results,'passed'=>count(array_filter($results,fn($r)=>$r['status']==='PASS')),'total'=>count($results)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit(count(array_filter($results,fn($r)=>$r['status']!=='PASS'))?1:0);
