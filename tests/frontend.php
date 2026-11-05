<?php
declare(strict_types=1);
// HTTP integration regression. Never stop/reconfigure the running servers.
require_once __DIR__ . '/../src/frontend.php';
$base = 'http://127.0.0.1:8000';
foreach (array_slice($argv,1) as $arg) {
    if (!str_starts_with($arg,'--http=')) { fwrite(STDERR,"Use --http=http://127.0.0.1:8000\n"); exit(2); }
    $base = rtrim(substr($arg,7),'/');
}
if ($base !== 'http://127.0.0.1:8000') { fwrite(STDERR,"REFUSED non-demo HTTP URL\n"); exit(2); }
$cfg = require __DIR__ . '/../config/database.local.php';
if (!in_array($cfg['host'] ?? '',['localhost','127.0.0.1'],true) || (int)($cfg['port'] ?? 0)!==33307 || strcasecmp((string)($cfg['database'] ?? ''),'QuanLySanBong_demo')!==0) { fwrite(STDERR,"REFUSED non-demo DB config\n"); exit(2); }
$pdo=db(); $identity=$pdo->query('SELECT DATABASE() dbname,@@port port')->fetch();
if ((int)$identity['port']!==33307 || strcasecmp($identity['dbname'],'QuanLySanBong_demo')!==0) { fwrite(STDERR,"REFUSED server identity mismatch\n"); exit(2); }
$run='FEQA-'.bin2hex(random_bytes(5)); $pass=0; $fail=0; $cookie=''; $csrf='';
$ids=['customers'=>[],'pitches'=>[],'services'=>[],'teams'=>[],'tournaments'=>[],'bookings'=>[]];
function fecheck(bool $condition,string $label): void { global $pass,$fail; if($condition){$pass++;echo "PASS $label\n";}else{$fail++;echo "FAIL $label\n";} }
function ferow(string $sql,array $params=[]): array {global $pdo;$q=$pdo->prepare($sql);$q->execute($params);return $q->fetch() ?: [];}
function fehttp(?array $post=null,string $method='',bool $multipart=false): array {
    global $base,$cookie; $method=$method ?: ($post===null?'GET':'POST');
    $headers="Cookie: $cookie\r\n"; $content='';
    if($post!==null){
        if($multipart){$boundary='FEQA'.bin2hex(random_bytes(8));$headers.="Content-Type: multipart/form-data; boundary=$boundary\r\n";foreach($post as $k=>$v){$content.="--$boundary\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";}$content.="--$boundary--\r\n";}
        else{$headers.="Content-Type: application/x-www-form-urlencoded\r\n";$content=http_build_query($post);}
    }
    $body=file_get_contents($base.'/api.php',false,stream_context_create(['http'=>['method'=>$method,'header'=>$headers,'content'=>$content,'ignore_errors'=>true,'follow_location'=>0,'timeout'=>20]]));
    $response=$http_response_header ?? [];foreach($response as $h){if(preg_match('/^Set-Cookie: ([^;]+)/i',$h,$m)){$cookie=$m[1];}}
    preg_match('~HTTP/\S+ (\d+)~',$response[0] ?? '',$m);
    $json=json_decode($body===false?'':$body,true,32,JSON_THROW_ON_ERROR);
    return ['status'=>(int)($m[1] ?? 0),'json'=>$json,'body'=>$body,'headers'=>$response];
}
function feget(): array {$r=fehttp();if($r['status']!==200 || ($r['json']['ok'] ?? false)!==true){throw new RuntimeException('GET API unavailable');}return $r['json']['data'];}
function fepost(string $operation,array $data=[],int $expected=200,bool $multipart=false): array {global $csrf;$r=fehttp(['_csrf'=>$csrf,'operation'=>$operation]+$data,'POST',$multipart);fecheck($r['status']===$expected && ($r['json']['ok'] ?? null)===($expected===200),"POST $operation HTTP$expected");return $r['json'];}
function fecreate(string $operation,array $data,string $bucket,bool $multipart=false): int {global $ids;$r=fepost($operation,$data,200,$multipart);$id=(int)($r['id'] ?? 0);if($id<=0){throw new RuntimeException("Cannot create $operation fixture");}$ids[$bucket][]=$id;return $id;}
try {
    echo "RUN $run | HTTP $base | DB33307 | PHP ".PHP_VERSION."\n";
    $first=fehttp();fecheck($first['status']===200 && ($first['json']['ok'] ?? false)===true,'GET JSON success');$data=$first['json']['data'];$csrf=$data['csrf'];
    foreach(['customers','pitches','pitchTypes','services','staff','bookings','serviceUsage','invoices','teams','tournaments','participation','matches'] as $list){fecheck(is_array($data[$list] ?? null),"GET list $list");}
    fecheck(strlen($csrf)>=32 && isset($data['stats'],$data['rules']),'GET CSRF stats rules');
    fecheck(count(array_filter($first['headers'],fn($h)=>stripos($h,'Cache-Control: no-store')===0))>0,'API no-store');
    fecheck(fehttp(null,'PUT')['status']===405,'unsupported HTTP method405');
    foreach([[],['_csrf'=>'wrong'],['_csrf'=>['array']]] as $bad){$r=fehttp($bad+['operation'=>'add_customer','HoTen'=>$run,'SoDienThoai'=>'0']);fecheck($r['status']===403 && $r['json']['ok']===false,'invalid CSRF JSON403');}
    fepost('unsupported',[],422);$r=fehttp(['_csrf'=>$csrf,'operation'=>['add_customer']]);fecheck($r['status']===422 && $r['json']['ok']===false,'array operation rejected422');fepost('add_customer',['HoTen'=>['array'],'SoDienThoai'=>'0'],422);fepost('add_pitch',['TenSan'=>'x','MaLoai'=>['1'],'GiaThue'=>'1'],422);fepost('pay_invoice',['MaHD'=>['1']],422);fepost('add_team',['TenDoi'=>['array']],422);fepost('add_tournament',['TenGiai'=>'x','NgayBatDau'=>['2044-01-01'],'NgayKetThuc'=>'2044-01-02'],422);
    $staff=(int)$data['staff'][0]['MaNV'];$type=(int)$data['pitchTypes'][0]['MaLoai'];
    $name=$run." Đặng O'Neil <img src=x onerror=alert(1)>";
    $customer=fecreate('add_customer',['HoTen'=>$name,'SoDienThoai'=>'0012345678','DiaChi'=>'<script>alert(1)</script>'],'customers',true);
    fecheck((ferow('SELECT HoTen,SoDienThoai FROM KHACHHANG WHERE MaKH=?',[$customer])===['HoTen'=>$name,'SoDienThoai'=>'0012345678']),'multipart Unicode/XSS payload and zero phone persisted');
    $pitch=fecreate('add_pitch',['TenSan'=>$run,'MaLoai'=>$type,'GiaThue'=>'300000','TinhTrang'=>'BAO_TRI'],'pitches');
    fecheck(ferow('SELECT GiaThue,TinhTrang FROM SANBONG WHERE MaSan=?',[$pitch])===['GiaThue'=>'300000.00','TinhTrang'=>config('rules.pitch_active')],'pitch DB price and server status');
    $service=fecreate('add_service',['TenDV'=>$run.' nước','DonViTinh'=>'chai','DonGia'=>'10000'],'services');
    $free=fecreate('add_service',['TenDV'=>$run.' free','DonViTinh'=>'lượt','DonGia'=>'0'],'services');
    fepost('add_service',['TenDV'=>$run,'DonViTinh'=>'chai','DonGia'=>'-1'],422);
    fepost('add_service',['TenDV'=>$run,'DonViTinh'=>'chai','DonGia'=>['1']],422);
    $booking=['MaKH'=>$customer,'MaSan'=>$pitch,'MaNV'=>$staff,'ThoiGianBatDau'=>'2044-01-01T23:00','ThoiGianKetThuc'=>'2044-01-02T00:30'];
    foreach(['bad','{}','[1]','[{"MaDV":1,"SoLuong":0}]',['array']] as $bad){fepost('add_booking',$booking+['Services'=>$bad],422);}
    $before=(int)ferow('SELECT COUNT(*) n FROM DAT_SAN WHERE MaSan=?',[$pitch])['n'];
    fepost('add_booking',$booking+['Services'=>json_encode([['MaDV'=>$service,'SoLuong'=>1],['MaDV'=>2147483647,'SoLuong'=>1]])],422);
    fecheck((int)ferow('SELECT COUNT(*) n FROM DAT_SAN WHERE MaSan=?',[$pitch])['n']===$before && (int)ferow('SELECT COUNT(*) n FROM CHITIET_DICHVU c JOIN DAT_SAN d ON d.MaDatSan=c.MaDatSan WHERE d.MaSan=?',[$pitch])['n']===0,'invalid later service rolls back booking AND earlier service');
    $id=fecreate('add_booking',$booking+['Services'=>json_encode([['MaDV'=>$service,'SoLuong'=>1],['MaDV'=>$service,'SoLuong'=>1],['MaDV'=>$free,'SoLuong'=>1]]),'GiaThueLucDat'=>'1','TongTien'=>'1'],'bookings',true);
    fecheck(ferow('SELECT GiaThueLucDat FROM DAT_SAN WHERE MaDatSan=?',[$id])['GiaThueLucDat']==='300000.00' && (int)ferow('SELECT SoLuong FROM CHITIET_DICHVU WHERE MaDatSan=? AND MaDV=?',[$id,$service])['SoLuong']===2,'composite overnight booking snapshot and repeated quantity');
    fepost('add_booking',$booking,422);fepost('receive_booking',['MaDatSan'=>$id],422);
    $q=$pdo->prepare('UPDATE DICHVU SET DonGia=20000 WHERE MaDV=?');$q->execute([$service]);
    fepost('use_service',['MaDatSan'=>$id,'MaDV'=>$service,'SoLuong'=>'1','DonGiaLucDung'=>'1']);
    fecheck(ferow('SELECT SoLuong,DonGiaLucDung FROM CHITIET_DICHVU WHERE MaDatSan=? AND MaDV=?',[$id,$service])===['SoLuong'=>3,'DonGiaLucDung'=>'10000.00'],'use_service increments with historical price');
    // Restore own fixture quantity so invoice expectation is the documented 470000.
    $q=$pdo->prepare('UPDATE CHITIET_DICHVU SET SoLuong=2 WHERE MaDatSan=? AND MaDV=?');$q->execute([$id,$service]);
    $revenue=feget()['stats']['revenueToday'];
    $invoice=fepost('make_invoice',['MaDatSan'=>$id,'MaNV'=>$staff,'TongTien'=>'1','TrangThaiThanhToan'=>config('rules.invoice_paid')]);$invoice=(int)($invoice['id'] ?? 0);
    $row=ferow('SELECT h.TongTien,h.TrangThaiThanhToan,d.TrangThai FROM HOADON h JOIN DAT_SAN d ON d.MaDatSan=h.MaDatSan WHERE h.MaHD=?',[$invoice]);
    fecheck($row===['TongTien'=>'470000.00','TrangThaiThanhToan'=>config('rules.invoice_unpaid'),'TrangThai'=>config('rules.booking_completed')],'make invoice DB total470000 unpaid/completed ignores tamper');
    fecheck(feget()['stats']['revenueToday']===$revenue,'unpaid invoice does not count as revenue');
    foreach(['make_invoice'=>['MaDatSan'=>$id,'MaNV'=>$staff],'cancel_booking'=>['MaDatSan'=>$id],'receive_booking'=>['MaDatSan'=>$id],'use_service'=>['MaDatSan'=>$id,'MaDV'=>$service,'SoLuong'=>1]] as $op=>$payload){fepost($op,$payload,422);}
    fepost('pay_invoice',['MaHD'=>$invoice]);fecheck(ferow('SELECT TrangThaiThanhToan FROM HOADON WHERE MaHD=?',[$invoice])['TrangThaiThanhToan']===config('rules.invoice_paid'),'payment persisted');
    $paid=feget();fecheck(abs((float)$paid['stats']['revenueToday']-(float)$revenue-470000)<0.001,'paid invoice adds exact revenue470000');fepost('pay_invoice',['MaHD'=>$invoice],422);
    fecheck((int)ferow('SELECT COUNT(*) n FROM HOADON WHERE MaDatSan=?',[$id])['n']===1,'duplicate API invoice leaves one row');
    $now=new DateTimeImmutable();$current=['MaKH'=>$customer,'MaSan'=>$pitch,'MaNV'=>$staff,'ThoiGianBatDau'=>$now->modify('-10 minutes')->format('Y-m-d\TH:i'),'ThoiGianKetThuc'=>$now->modify('+50 minutes')->format('Y-m-d\TH:i')];
    $live=fecreate('add_booking',$current,'bookings');$snapshot=feget();$p=array_values(array_filter($snapshot['pitches'],fn($p)=>(int)$p['MaSan']===$pitch))[0];fecheck($p['StatusCode']==='busy','current booking pitch busy');
    fepost('receive_booking',['MaDatSan'=>$live]);fecheck(ferow('SELECT TrangThai FROM DAT_SAN WHERE MaDatSan=?',[$live])['TrangThai']===config('rules.booking_received'),'receive within slot persisted');
    fepost('receive_booking',['MaDatSan'=>$live],422);fepost('cancel_booking',['MaDatSan'=>$live]);fepost('cancel_booking',['MaDatSan'=>$live],422);fepost('make_invoice',['MaDatSan'=>$live,'MaNV'=>$staff],422);fepost('use_service',['MaDatSan'=>$live,'MaDV'=>$service,'SoLuong'=>1],422);
    $snapshot=feget();$p=array_values(array_filter($snapshot['pitches'],fn($p)=>(int)$p['MaSan']===$pitch))[0];fecheck($p['StatusCode']==='free','cancelled booking frees occupancy');
    $q=$pdo->prepare('UPDATE SANBONG SET TinhTrang=? WHERE MaSan=?');$q->execute([config('rules.pitch_maintenance'),$pitch]);$snapshot=feget();$p=array_values(array_filter($snapshot['pitches'],fn($p)=>(int)$p['MaSan']===$pitch))[0];fecheck($p['StatusCode']==='maintenance','maintenance never free');$q->execute([config('rules.pitch_active'),$pitch]);
    $again=fecreate('add_booking',$current,'bookings');fecheck($again>0,'cancelled slot can be booked again');
    fepost('add_tournament',['TenGiai'=>$run,'NgayBatDau'=>'2044-02-30','NgayKetThuc'=>'2044-03-01'],422);fepost('add_tournament',['TenGiai'=>$run,'NgayBatDau'=>'2044-02-02','NgayKetThuc'=>'2044-02-01'],422);
    $tournament=fecreate('add_tournament',['TenGiai'=>$run,'NgayBatDau'=>'2044-02-01','NgayKetThuc'=>'2044-02-02'],'tournaments');
    $team=fecreate('add_team',['TenDoi'=>$run,'TenDoiTruong'=>'Đội trưởng','SoDienThoai'=>'00123','MaGiai'=>$tournament],'teams');
    fecheck((int)ferow('SELECT COUNT(*) n FROM THAMGIA_GIAIDAU WHERE MaDoi=? AND MaGiai=?',[$team,$tournament])['n']===1,'team tournament FK membership persisted');
    fepost('add_team',['TenDoi'=>$run.' bad','MaGiai'=>2147483647],422);fecheck((int)ferow('SELECT COUNT(*) n FROM DOIBONG WHERE TenDoi=?',[$run.' bad'])['n']===0,'invalid membership leaves no team');
    $final=feget();$roundtrip=array_values(array_filter($final['customers'],fn($c)=>(int)$c['MaKH']===$customer))[0];fecheck($roundtrip['HoTen']===$name,'API round trip keeps text for DOM-safe render');
    $sqlRevenue=ferow('SELECT CAST(COALESCE(SUM(TongTien),0) AS DECIMAL(22,2)) total FROM HOADON WHERE NgayLap=? AND TrangThaiThanhToan=?',[$final['today'],config('rules.invoice_paid')])['total'];fecheck($sqlRevenue===$final['stats']['revenueToday'],'GET revenue equals independent paid-only SQL');
    $count=ferow('SELECT COUNT(*) n FROM DAT_SAN WHERE TrangThai<>? AND ThoiGianBatDau<? AND ThoiGianKetThuc>?',[config('rules.booking_cancelled'),date('Y-m-d',strtotime('+1 day')).' 00:00:00',date('Y-m-d').' 00:00:00'])['n'];fecheck((int)$count===$final['stats']['bookingsToday'],'today count equals day-overlap SQL');
    $js=file_get_contents(__DIR__.'/../public/assets/app.js');fecheck(!preg_match('/innerHTML|outerHTML|insertAdjacentHTML|document\.write\s*\(/',$js) && str_contains($js,'el.textContent = String(text)') && str_contains($js,'document.createTextNode'),'JS review: persisted strings use text nodes not HTML sinks');
    fecheck(str_contains($js,'available = false;') && str_contains($js,'!available || busy || loading'),'JS review: API failure disables mutations');
    $api=file_get_contents(__DIR__.'/../public/api.php');fecheck(str_contains($api,"} catch (Throwable \$error)") && str_contains($api,"],503)") && !str_contains(substr($api,strpos($api,'} catch (Throwable')),'getMessage()'),'DB failure catch reviewed generic503, no server interruption');
} catch(Throwable $error){$fail++;fwrite(STDERR,'FAIL uncaught '.get_class($error).': '.$error->getMessage()."\n");}
finally {
    try{
        $pdo->beginTransaction();
        // Include unexpected booking commits only on this run's own pitch/customer.
        if($ids['customers']) { foreach($ids['pitches'] as $pitchId){$q=$pdo->prepare('SELECT MaDatSan FROM DAT_SAN WHERE MaSan=? AND MaKH IN ('.implode(',',array_fill(0,count($ids['customers']),'?')).')');$q->execute([$pitchId,...$ids['customers']]);$ids['bookings']=array_merge($ids['bookings'],array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)));} }
        foreach(array_unique($ids['bookings']) as $id){foreach(['HOADON','CHITIET_DICHVU','DAT_SAN'] as $table){$q=$pdo->prepare("DELETE FROM $table WHERE MaDatSan=?");$q->execute([$id]);}}
        foreach($ids['teams'] as $id){$q=$pdo->prepare('DELETE FROM THAMGIA_GIAIDAU WHERE MaDoi=?');$q->execute([$id]);}
        foreach(['teams'=>['DOIBONG','MaDoi'],'tournaments'=>['GIAIDAU','MaGiai'],'services'=>['DICHVU','MaDV'],'pitches'=>['SANBONG','MaSan'],'customers'=>['KHACHHANG','MaKH']] as $bucket=>[$table,$column]){foreach($ids[$bucket] as $id){$q=$pdo->prepare("DELETE FROM $table WHERE $column=?");$q->execute([$id]);}}
        $pdo->commit();echo "CLEANUP own IDs complete; seed/user data and AUTO_INCREMENT retained\n";
    }catch(Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}$fail++;fwrite(STDERR,"FAIL cleanup: inspect $run fixtures\n");}
}
echo "RESULT PASS=$pass FAIL=$fail\n";exit($fail===0?0:1);