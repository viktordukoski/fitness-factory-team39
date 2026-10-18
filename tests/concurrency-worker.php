<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (PHP_SAPI !== 'cli' || !str_ends_with(env('DB_NAME'),'_test')) exit(2);
[$script,$mode,$userId,$recordId,$key,$gate,$ready] = $argv;
file_put_contents($ready,'ready');
$deadline = microtime(true)+15;
while (!is_file($gate) && microtime(true)<$deadline) usleep(10000);
if (!is_file($gate)) { echo json_encode(['status'=>'error','reason'=>'barrier_timeout']); exit(1); }
try {
    $id = $mode === 'booking' ? reserve_class((int)$userId,(int)$recordId) : create_order((int)$userId,(int)$recordId,'card','success',$key);
    echo json_encode(['status'=>'success','id'=>$id]);
} catch (Throwable $error) { echo json_encode(['status'=>'rejected','reason'=>$error->getMessage()]); }
