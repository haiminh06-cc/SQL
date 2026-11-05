<?php
declare(strict_types=1);
// Run only after the owner stops the isolated demo DB; this script stops nothing.
$base=$argv[1] ?? 'http://127.0.0.1:8000';
if (!preg_match('~^http://127\.0\.0\.1:[0-9]+$~D',$base)) { exit(2); }
$cookie='';
$stateFile=dirname(__DIR__).'/.tools/qa-db-down-session.json';
function fetchPage(string $path,?array $post=null): array {
    global $base,$cookie;
    $headers="Cookie: $cookie\r\nContent-Type: application/x-www-form-urlencoded\r\n";
    $body=file_get_contents($base.$path,false,stream_context_create(['http'=>['method'=>$post===null?'GET':'POST','header'=>$headers,
        'content'=>$post===null?'':http_build_query($post),'follow_location'=>0,'ignore_errors'=>true,'timeout'=>15]]));
    foreach ($http_response_header ?? [] as $header) {if(preg_match('/^Set-Cookie: ([^;]+)/i',$header,$m)){$cookie=$m[1];}}
    preg_match('~HTTP/\S+ (\d+)~',$http_response_header[0] ?? '',$m);
    return ['status'=>(int)($m[1] ?? 0),'body'=>$body ?: ''];
}
$failed=0;$passed=0;
function test(bool $ok,string $message): void {global $failed,$passed;$ok?++$passed:++$failed;echo ($ok?'PASS ':'FAIL ').$message."\n";}
if (($argv[2] ?? '')==='--prepare') {
    $r=fetchPage('/khach-hang.php');preg_match('/name="_csrf" value="([^"]+)"/',$r['body'],$m);
    if ($r['status']!==200 || empty($m[1])) {fwrite(STDERR,"FAIL prepare requires live DB and CSRF form\n");exit(1);}
    file_put_contents($stateFile,json_encode(['cookie'=>$cookie,'csrf'=>html_entity_decode($m[1],ENT_QUOTES,'UTF-8')],JSON_THROW_ON_ERROR));
    echo "PREPARED ignored local session state; stop only the isolated demo DB, then run without --prepare.\n";exit(0);
}
if (!is_file($stateFile)) {fwrite(STDERR,"Run --prepare with live DB first.\n");exit(2);}
$state=json_decode(file_get_contents($stateFile),true,512,JSON_THROW_ON_ERROR);$cookie=$state['cookie'];
$r=fetchPage('/khach-hang.php');
test($r['status']===503 && str_contains($r['body'],'Không thể đọc dữ liệu MySQL.'),'DB down GET503 generic error');
// If DB is actually live, do not accidentally insert a customer.
if ($r['status']!==503) {fwrite(STDERR,"REFUSED: expected unavailable DB\n");exit(1);}
$r=fetchPage('/actions/them-khach-hang.php',['_csrf'=>$state['csrf'],'HoTen'=>'QA DB DOWN probe','SoDienThoai'=>'000']);
test($r['status']===303,'DB down POST303 PRG');
$r=fetchPage('/khach-hang.php');
test(str_contains($r['body'],'Không thể lưu dữ liệu.') && !str_contains($r['body'],'Đã lưu thành công.'),'DB down POST shows failure, no false success');
test(!preg_match('/SQLSTATE|mysql:host=|Stack trace|PDOException|password/i',$r['body']),'DB down response omits technical connection detail');
echo "RESULT PASS=$passed FAIL=$failed\n";exit($failed===0?0:1);
