<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/business.php';
$settings=require __DIR__.'/../config/database.local.php';
if ((int)($settings['port'] ?? 0)!==33307 || strcasecmp((string)($settings['database'] ?? ''),'QuanLySanBong_demo')!==0
    || !in_array($settings['host'] ?? '',['127.0.0.1','localhost'],true)) { exit(2); }
$payload=json_decode(base64_decode($argv[1] ?? '',true) ?: '',true,512,JSON_THROW_ON_ERROR);
$pdo=db();
$identity=$pdo->query('SELECT @@port AS port,DATABASE() AS dbname')->fetch();
if ((int)$identity['port']!==33307 || strcasecmp($identity['dbname'],'QuanLySanBong_demo')!==0) { exit(2); }
while (microtime(true)<$payload['at']) { usleep(1000); }
try {
    if (!in_array($payload['operation'],['add_booking','make_invoice','use_service'],true)) { exit(2); }
    $id=$payload['operation']($pdo,$payload['data']);
    echo json_encode(['status'=>'ok','id'=>$id],JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $error) {
    echo json_encode(['status'=>'rejected'],JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    echo json_encode(['status'=>'error','class'=>get_class($error),'code'=>$error->getCode()],JSON_THROW_ON_ERROR); exit(1);
}
