<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (PHP_SAPI !== 'cli' || !str_ends_with(env('DB_NAME'), '_test')) {
    fwrite(STDERR, "Run only against an isolated database whose name ends in _test.\n"); exit(2);
}
require APP_ROOT . '/app/http.php';
function expect(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function check(string $name, callable $work): void
{
    try { $work(); $GLOBALS['results'][]=['test'=>$name,'status'=>'PASS']; }
    catch (Throwable $error) { $GLOBALS['results'][]=['test'=>$name,'status'=>'NEEDS FIX','reason'=>$error->getMessage()]; }
}
function page_html(string $page, array $parameters = [], string $language = 'en'): string
{
    $_GET=array_merge(['page'=>$page,'lang'=>$language],array_map('strval',$parameters));
    $_REQUEST=$_GET; $_POST=[]; $_SERVER['REQUEST_METHOD']='GET'; $GLOBALS['page']=$page;
    ob_start();
    try { display_page($page,[]); return ob_get_clean(); }
    catch (Throwable $error) { ob_end_clean(); throw $error; }
}
$results=[]; $contentId=0; $sessionId=0; $planId=0;
$suffix=bin2hex(random_bytes(6));
$admin=(int)(fetch_one("SELECT id FROM users WHERE email='admin@demo.test'")['id']??0);
$_SESSION=['user_id'=>$admin]; $_GET=['lang'=>'en']; $_POST=[];
$contentData=['kind'=>'class_type','slug'=>'display-'.$suffix,'title'=>'Display fixture class','summary'=>'A fictional display fixture.','body'=>'Fixture class body.','language'=>'en','category'=>'strength','level'=>'beginner','format'=>'group','image'=>'assets/cardio.webp','alt'=>'Fixture <image> & description','equipment'=>'Fixture mat & straps','preparation'=>'Bring <fixture> & water','specialties'=>'English fixture specialty','languages'=>'English fixture language','duration_minutes'=>45,'is_demo'=>1,'published'=>1];
try {
    expect($admin>0,'Seeded administrator is required.');
    $contentId=admin_save_content($admin,0,$contentData);
    $room=fetch_one('SELECT id FROM rooms WHERE active=1 ORDER BY id LIMIT 1');
    $start=(new DateTimeImmutable('now',new DateTimeZone('Europe/Skopje')))->modify('+2 days')->setTime(10,0);
    $sessionId=admin_save_session($admin,0,['class_type_id'=>$contentId,'room_id'=>$room['id'],'starts_at'=>$start->format('Y-m-d\TH:i'),'ends_at'=>$start->modify('+45 minutes')->format('Y-m-d\TH:i'),'capacity'=>1,'status'=>'scheduled','requires_membership'=>0]);

    check('Class detail uses CMS image, alt, preparation and equipment with escaping',function()use($contentId,$sessionId,$contentData):void{
        $html=page_html('class',['id'=>$sessionId]);
        expect(str_contains($html,'src="'.e(asset('assets/cardio.webp')).'"'),'Stored image ignored.');
        foreach (['alt','preparation','equipment'] as $field) expect(str_contains($html,e($contentData[$field])),'Stored '.$field.' missing or unescaped.');
        expect(!str_contains($html,'<fixture>')&&!str_contains($html,'<image>'),'CMS markup was emitted as HTML.');
        expect(session_item($sessionId)['metadata']['equipment']===$contentData['equipment'],'Session detail query omitted metadata.');
        expect(sessions(['class_type_id'=>$contentId])[0]['metadata']['preparation']===$contentData['preparation'],'Schedule query omitted metadata.');
    });
    check('CMS metadata translations preserve English and shared duration',function()use($admin,$contentId,$sessionId,$contentData):void{
        $translated=$contentData; $translated['language']='mk'; $translated['title']='MK fixture class';
        foreach (['specialties','languages','equipment','preparation'] as $field) $translated[$field]='MK fixture '.$field;
        admin_save_content($admin,$contentId,$translated);
        foreach (['en'=>$contentData,'mk'=>$translated] as $language=>$expected) {
            $_GET['lang']=$language;
            $metadata=content_item($contentId)['metadata'];
            foreach (['specialties','languages','equipment','preparation'] as $field) expect($metadata[$field]===$expected[$field],$language.' metadata was overwritten.');
            expect($metadata['duration_minutes']===45,'Shared duration changed.');
            $html=page_html('class',['id'=>$sessionId],$language);
            expect(str_contains($html,e($expected['preparation'])),'Class did not use '.$language.' preparation.');
        }
    });
    check('Missing metadata translations fall back publicly but stay empty in the CMS',function()use($contentId,$contentData):void{
        $_GET['lang']='de';
        expect(content_item($contentId)['metadata']['equipment']===$contentData['equipment'],'English metadata fallback failed.');
        $html=page_html('admin',['section'=>'content','edit'=>$contentId],'de');
        expect(preg_match('/<textarea name="equipment"[^>]*>\s*<\/textarea>/',$html)===1,'Missing translation form copied fallback text.');
        expect(preg_match('/<input type="text" name="specialties" value=""/',$html)===1,'Missing specialties translation was prefilled.');
    });
    check('Legacy metadata falls back without migration or data loss',function()use($contentId):void{
        $before=fetch_one('SELECT metadata FROM content WHERE id=?',[$contentId])['metadata'];
        try {
            $metadata=json_decode($before,true); $metadata['equipment']='Legacy fixture equipment'; unset($metadata['translations']['en']['equipment']);
            execute('UPDATE content SET metadata=? WHERE id=?',[json_encode($metadata),$contentId]);
            $_GET['lang']='it';
            expect(content_item($contentId)['metadata']['equipment']==='Legacy fixture equipment','Legacy metadata fallback failed.');
        } finally { execute('UPDATE content SET metadata=? WHERE id=?',[$before,$contentId]); }
    });
    check('Empty CMS class fields use accessible fallbacks without stale language data',function()use($admin,$contentId,$sessionId,$contentData):void{
        $empty=$contentData; $empty['language']='mk'; $empty['image']=''; $empty['alt']=''; $empty['preparation']=''; $empty['equipment']='';
        admin_save_content($admin,$contentId,$empty);
        $html=page_html('class',['id'=>$sessionId],'mk');
        expect(str_contains($html,'src="'.e(asset('assets/strength.png')).'"'),'Empty image did not fall back.');
        expect(str_contains($html,e(t('photo_strength'))),'Empty alt did not fall back.');
        expect(str_contains($html,e(t('Comfortable training clothes, clean indoor shoes, water and a towel. Ask the gym directly about requirements for actual visits.'))),'Empty preparation did not fall back.');
        $_GET['lang']='en';
        expect(content_item($contentId)['metadata']['equipment']===$contentData['equipment'],'Clearing Macedonian equipment changed English.');
    });
    $planId=admin_save_plan($admin,0,['slug'=>'display-plan-'.$suffix,'language'=>'en','title'=>'Custom demonstration plan','description'=>'A fictional test offering.','price'=>'1234.56','duration_days'=>14,'kind'=>'pass','source_status'=>'fictional_demo','active'=>1]);
    check('Plan and checkout use plain price labels in every locale',function()use($planId):void{
        foreach (LANGUAGES as $language) foreach (['plan','checkout'] as $page) {
            $html=page_html($page,['id'=>$planId],$language);
            expect(str_contains($html,e(t('price'))),$page.' missing '.$language.' price label.');
            expect(str_contains($html,e(str_replace(' MKD','',money('1234.56')))),$page.' changed the configured price.');
        }
    });
    check('Membership listing displays a custom plan without a provenance badge',function():void{
        $html=page_html('memberships');
        expect(str_contains($html,'Custom demonstration plan</h2>'),'Custom plan card is missing.');
        expect(!str_contains($html,'Demonstration price</span>'),'Obsolete price provenance badge remains.');
    });
    check('Ten package prices and private provenance remain unchanged',function():void{
        $_GET['lang']='en'; $published=array_slice(plans(),0,10);
        expect(array_map(fn($p)=>(int)$p['price'],$published)===[300,1000,1600,2000,4350,5400,7700,9600,14400,18000],'A sourced price changed.');
        foreach ($published as $plan) expect($plan['source_status']==='published_pending_confirmation','Sourced status changed.');
        $html=page_html('plan',['id'=>$published[0]['id']]);
        expect(str_contains($html,e(t('price'))),'Plan lost price label.');
        expect(!str_contains($html,'Instagram'),'Public price page contains obsolete source framing.');
    });
} finally {
    if($sessionId){execute('DELETE FROM admin_audit_log WHERE entity_type=\'session\' AND entity_id=?',[(string)$sessionId]);execute('DELETE FROM class_sessions WHERE id=?',[$sessionId]);}
    if($contentId){execute('DELETE FROM admin_audit_log WHERE entity_type=\'content\' AND entity_id=?',[(string)$contentId]);execute('DELETE FROM content_translations WHERE content_id=?',[$contentId]);execute('DELETE FROM content WHERE id=?',[$contentId]);}
    if($planId){execute('DELETE FROM admin_audit_log WHERE entity_type=\'plan\' AND entity_id=?',[(string)$planId]);execute('DELETE FROM membership_plan_translations WHERE plan_id=?',[$planId]);execute('DELETE FROM membership_plans WHERE id=?',[$planId]);}
}
echo json_encode(['checks'=>$results,'passed'=>count(array_filter($results,fn($r)=>$r['status']==='PASS')),'total'=>count($results)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit(count(array_filter($results,fn($r)=>$r['status']!=='PASS'))?1:0);
