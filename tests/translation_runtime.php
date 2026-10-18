<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('TRANSLATION_AUDIT=true');
require dirname(__DIR__).'/app/bootstrap.php';require APP_ROOT.'/app/http.php';
$_SESSION=['user_id'=>(int)fetch_one("SELECT id FROM users WHERE email='admin@demo.test'")['id']];$_POST=[];$_SERVER['REQUEST_METHOD']='GET';
$routes=['home','about','services','facilities','trainers','classes','memberships','news','gallery','contact','faq','search','privacy','cookies','terms','newsletter','verify','login','register','dashboard','orders','passes','bookings','settings','favorites'];
foreach(LANGUAGES as $locale){
 foreach($routes as $page){$_GET=['page'=>$page,'lang'=>$locale];$_REQUEST=$_GET;$GLOBALS['page']=$page;ob_start();display_page($page,[]);ob_end_clean();}
 foreach(['detail'=>1,'class'=>1,'plan'=>1,'checkout'=>1,'booking'=>1] as $page=>$id){$_GET=['page'=>$page,'lang'=>$locale,'id'=>(string)$id];$_REQUEST=$_GET;$GLOBALS['page']=$page;ob_start();display_page($page,[]);ob_end_clean();}
 foreach(['dashboard','content','sessions','rooms','plans','users','orders','inquiries','subscribers','settings','sources','audit'] as $section){
  foreach(in_array($section,['content','sessions','rooms','plans','settings','sources'])?['','new']:[''] as $edit){$_GET=['page'=>'admin','lang'=>$locale,'section'=>$section,'edit'=>$edit];$_REQUEST=$_GET;$GLOBALS['page']='admin';ob_start();display_page('admin',[]);ob_end_clean();}
 }
}
echo json_encode($GLOBALS['missing_translations']??[],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
