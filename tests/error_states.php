<?php
// Isolated process: deliberately unreachable loopback DB, no writes.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('DB_HOST=127.0.0.1');putenv('DB_PORT=1');putenv('DB_USER=unavailable_test');putenv('DB_PASSWORD=');
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/?page=home';$_GET=['page'=>'home'];$_REQUEST=$_GET;$_SESSION=[];
ob_start();require dirname(__DIR__).'/public/index.php';$body=ob_get_clean();
$passed=http_response_code()===503&&str_contains($body,'could not connect')&&!str_contains($body,'SQLSTATE')&&!str_contains($body,'password');
echo json_encode(['test'=>'Unavailable database returns safe 503 recovery page','status'=>$passed?'PASS':'NEEDS FIX'],JSON_PRETTY_PRINT)."\n";
exit($passed?0:1);
