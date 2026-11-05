<?php
declare(strict_types=1);
// Integration tests: only the explicitly selected local demo, never port 3306.
require_once __DIR__ . '/../src/business.php';
$settings = require __DIR__ . '/../config/database.local.php';
if (!in_array($settings['host'] ?? '', ['127.0.0.1', 'localhost'], true)
    || (int)($settings['port'] ?? 0) !== 33307
    || strcasecmp((string)($settings['database'] ?? ''), 'QuanLySanBong_demo') !== 0) {
    fwrite(STDERR, "REFUSED: tests require local QuanLySanBong_demo on 33307.\n"); exit(2);
}
$pdo = db();
$identity = $pdo->query('SELECT DATABASE() AS dbname,@@port AS port')->fetch();
if (strcasecmp($identity['dbname'], 'QuanLySanBong_demo') !== 0 || (int)$identity['port'] !== 33307) {
    fwrite(STDERR, "REFUSED: server identity mismatch.\n"); exit(2);
}
$passed = 0; $failed = 0; $run = 'QA-' . bin2hex(random_bytes(5));
$ids = ['customers'=>[], 'services'=>[], 'pitches'=>[], 'bookings'=>[]];
function check(bool $condition, string $label): void {
    global $passed, $failed;
    if ($condition) { ++$passed; echo "PASS $label\n"; }
    else { ++$failed; echo "FAIL $label\n"; }
}
function reject(callable $operation, string $label): void {
    try { $operation(); check(false, $label); }
    catch (InvalidArgumentException $error) { check(true, $label); }
}
function sqlrow(string $sql, array $args = []): array {
    global $pdo; $q = $pdo->prepare($sql); $q->execute($args); return $q->fetch() ?: [];
}
function booking(string $start, string $end, array $overrides = []): int {
    global $pdo, $customer, $pitch, $staff, $ids;
    $id = add_booking($pdo, array_replace(['MaKH'=>(string)$customer,'MaSan'=>(string)$pitch,'MaNV'=>(string)$staff,
        'ThoiGianBatDau'=>$start,'ThoiGianKetThuc'=>$end], $overrides));
    $ids['bookings'][] = $id; return $id;
}
$httpBase = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--http=')) { $httpBase = rtrim(substr($arg,7), '/'); }
    else { fwrite(STDERR, "Unknown option. Use --http=http://127.0.0.1:8000\n"); exit(2); }
}
if ($httpBase !== null && !preg_match('~^http://127\.0\.0\.1:[0-9]+(?:/[a-zA-Z0-9_/-]+)?$~D', $httpBase)) {
    fwrite(STDERR, "REFUSED: HTTP tests require a loopback URL.\n"); exit(2);
}
$cookie = '';
function request(string $path, ?array $data = null): array {
    global $httpBase,$cookie;
    $headers = "Cookie: $cookie\r\n";
    if ($data !== null) { $headers .= "Content-Type: application/x-www-form-urlencoded\r\n"; }
    $options = ['http'=>['method'=>$data === null ? 'GET' : 'POST','header'=>$headers,
        'content'=>$data === null ? '' : http_build_query($data),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>15]];
    $body = file_get_contents($httpBase . $path, false, stream_context_create($options));
    $response = $http_response_header ?? [];
    foreach ($response as $header) {
        if (preg_match('/^Set-Cookie: ([^;]+)/i', $header,$m)) { $cookie = $m[1]; }
    }
    preg_match('~HTTP/\S+ (\d+)~', $response[0] ?? '',$m);
    $location = '';
    foreach ($response as $header) { if (stripos($header,'Location: ')===0) { $location=substr($header,10); } }
    return ['status'=>(int)($m[1] ?? 0),'body'=>$body === false ? '' : $body,'location'=>$location];
}
try {
    echo "RUN $run | PHP " . PHP_VERSION . " | demo port " . $identity['port'] . "\n";
    check(!(bool)$pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES), 'PDO native prepares');
    $staff = (int)sqlrow('SELECT MaNV FROM NHANVIEN ORDER BY MaNV LIMIT 1')['MaNV'];
    $type = (int)sqlrow('SELECT MaLoai FROM LOAISAN ORDER BY MaLoai LIMIT 1')['MaLoai'];
    foreach ([config('rules.pitch_active'),config('rules.pitch_active'),config('rules.pitch_maintenance')] as $state) {
        $q=$pdo->prepare('INSERT INTO SANBONG(TenSan,MaLoai,GiaThue,TinhTrang) VALUES (?,?,?,?)');
        $q->execute([$run,$type,'300000.00',$state]); $ids['pitches'][]=(int)$pdo->lastInsertId();
    }
    [$pitch,$otherPitch,$maintenance] = $ids['pitches'];
    $name = "$run Nguyễn O'Neil <b>đội</b>";
    $customer=add_customer($pdo,['HoTen'=>$name,'SoDienThoai'=>'0012345678','DiaChi'=>"Hẻm 'Việt' <script>x</script>"]);
    $ids['customers'][]=$customer;
    $row=sqlrow('SELECT * FROM KHACHHANG WHERE MaKH=?',[$customer]);
    check($row['HoTen']===$name && $row['SoDienThoai']==='0012345678', 'Unicode, apostrophe and leading zero round trip');
    foreach ([['HoTen'=>'','SoDienThoai'=>'0'],['HoTen'=>str_repeat('ế',101),'SoDienThoai'=>'0'],['HoTen'=>['x'],'SoDienThoai'=>'0'],['HoTen'=>'X','SoDienThoai'=>str_repeat('0',16)]] as $bad) {
        reject(fn()=>add_customer($pdo,$bad),'reject invalid customer '.json_encode($bad,JSON_UNESCAPED_UNICODE));
    }
    $service=add_service($pdo,['TenDV'=>$run.' nước','DonViTinh'=>'chai','DonGia'=>'10000']); $ids['services'][]=$service;
    $second=add_service($pdo,['TenDV'=>$run.' miễn phí','DonViTinh'=>'lượt','DonGia'=>'0']); $ids['services'][]=$second;
    check(sqlrow('SELECT DonGia FROM DICHVU WHERE MaDV=?',[$second])['DonGia']==='0.00','zero service price accepted');
    foreach (['-1','1.234','1e3','10000000000','', ['5']] as $bad) {
        reject(fn()=>add_service($pdo,['TenDV'=>'x','DonViTinh'=>'x','DonGia'=>$bad]),'reject invalid price '.json_encode($bad));
    }
    $main=booking('2040-01-01T10:00','2040-01-01T11:30');
    check(sqlrow('SELECT GiaThueLucDat FROM DAT_SAN WHERE MaDatSan=?',[$main])['GiaThueLucDat']==='300000.00','booking stores price snapshot');
    reject(fn()=>booking('2040-01-01T10:30','2040-01-01T12:00'),'overlap rejected');
    reject(fn()=>booking('2040-01-01T10:00','2040-01-01T11:30'),'exact duplicate rejected');
    reject(fn()=>booking('2040-01-01T09:00','2040-01-01T12:00'),'enclosing overlap rejected');
    reject(fn()=>booking('2040-01-01T11:00','2040-01-01T10:00'),'end before start rejected');
    reject(fn()=>booking('2040-01-01T10:00','2040-01-01T10:00'),'equal times rejected');
    reject(fn()=>booking('2040-02-30T10:00','2040-02-30T11:00'),'invalid calendar day rejected');
    reject(fn()=>booking('2040-01-01 12:00','2040-01-01T13:00'),'invalid datetime-local format rejected');
    foreach (['MaKH','MaSan','MaNV'] as $key) { reject(fn()=>booking('2040-01-02T10:00','2040-01-02T11:00',[$key=>'2147483647']),"missing FK $key rejected"); }
    reject(fn()=>booking('2040-01-02T10:00','2040-01-02T11:00',['MaSan'=>(string)$maintenance]),'maintenance pitch rejected');
    $adjacent=booking('2040-01-01T11:30','2040-01-01T12:30'); check($adjacent>0,'right adjacent booking allowed');
    check(booking('2040-01-01T09:00','2040-01-01T10:00')>0,'left adjacent booking allowed');
    check(booking('2040-01-01T10:00','2040-01-01T11:30',['MaSan'=>(string)$otherPitch])>0,'same time different pitch allowed');
    $cancelled=booking('2040-01-03T10:00','2040-01-03T11:00');
    $q=$pdo->prepare('UPDATE DAT_SAN SET TrangThai=? WHERE MaDatSan=?'); $q->execute([config('rules.booking_cancelled'),$cancelled]);
    check(booking('2040-01-03T10:00','2040-01-03T11:00')>0,'cancelled booking does not block slot');
    reject(fn()=>use_service($pdo,['MaDatSan'=>$cancelled,'MaDV'=>$service,'SoLuong'=>'1']),'service on cancelled booking rejected');
    reject(fn()=>make_invoice($pdo,['MaDatSan'=>$cancelled,'MaNV'=>$staff]),'invoice on cancelled booking rejected');
    foreach (['0','-1','1.5','2147483648',['1']] as $bad) {
        reject(fn()=>use_service($pdo,['MaDatSan'=>$main,'MaDV'=>$service,'SoLuong'=>$bad]),'invalid quantity '.json_encode($bad));
    }
    reject(fn()=>use_service($pdo,['MaDatSan'=>$main,'MaDV'=>'2147483647','SoLuong'=>'1']),'nonexistent service rejected');
    use_service($pdo,['MaDatSan'=>$main,'MaDV'=>$service,'SoLuong'=>'1']);
    $q=$pdo->prepare('UPDATE DICHVU SET DonGia=20000 WHERE MaDV=?'); $q->execute([$service]);
    use_service($pdo,['MaDatSan'=>$main,'MaDV'=>$service,'SoLuong'=>'1']);
    use_service($pdo,['MaDatSan'=>$main,'MaDV'=>$second,'SoLuong'=>'3']);
    $detail=sqlrow('SELECT SoLuong,DonGiaLucDung FROM CHITIET_DICHVU WHERE MaDatSan=? AND MaDV=?',[$main,$service]);
    check((int)$detail['SoLuong']===2 && $detail['DonGiaLucDung']==='10000.00','repeat service increments and preserves original price');
    $q=$pdo->prepare('UPDATE SANBONG SET GiaThue=600000 WHERE MaSan=?'); $q->execute([$pitch]);
    $invoice=make_invoice($pdo,['MaDatSan'=>$main,'MaNV'=>$staff]);
    $row=sqlrow('SELECT h.TongTien,h.TrangThaiThanhToan,d.TrangThai FROM HOADON h JOIN DAT_SAN d ON d.MaDatSan=h.MaDatSan WHERE h.MaHD=?',[$invoice]);
    check($row['TongTien']==='470000.00','90min + two service rows = 470000.00, pitch not multiplied by JOIN');
    check($row['TrangThaiThanhToan']===config('rules.invoice_unpaid') && $row['TrangThai']===config('rules.booking_completed'),'invoice unpaid and booking completed');
    reject(fn()=>make_invoice($pdo,['MaDatSan'=>$main,'MaNV'=>$staff]),'duplicate invoice rejected');
    reject(fn()=>use_service($pdo,['MaDatSan'=>$main,'MaDV'=>$service,'SoLuong'=>'1']),'service after invoice rejected');
    $noService=make_invoice($pdo,['MaDatSan'=>$adjacent,'MaNV'=>$staff]);
    check(sqlrow('SELECT TongTien FROM HOADON WHERE MaHD=?',[$noService])['TongTien']==='300000.00','no-service invoice uses historical pitch price');
    reject(fn()=>booking('2040-01-01T10:00','2040-01-01T11:00'),'completed booking still blocks slot');
    $round=booking('2040-01-04T10:00','2040-01-04T10:01',['MaSan'=>(string)$otherPitch]);
    $roundInvoice=make_invoice($pdo,['MaDatSan'=>$round,'MaNV'=>$staff]);
    check(sqlrow('SELECT TongTien FROM HOADON WHERE MaHD=?',[$roundInvoice])['TongTien']==='5000.00','one minute prorated by hour');

    if ($httpBase !== null) {
        echo "HTTP $httpBase\n";
        foreach (['/index.php','/san.php','/khach-hang.php','/dat-san.php','/dich-vu.php','/hoa-don.php'] as $path) {
            check(request($path)['status']===200,"GET $path 200");
        }
        $html=request('/khach-hang.php')['body'];
        check(str_contains($html,e($name)) && !str_contains($html,$name),'HTML escapes persisted Unicode/apostrophe/tags');
        preg_match('/name="_csrf" value="([^"]+)"/', $html,$match); $csrf=html_entity_decode($match[1] ?? '',ENT_QUOTES,'UTF-8');
        check(strlen($csrf)>=32,'form carries CSRF token');
        check(request('/actions/them-khach-hang.php')['status']===405,'GET action 405');
        check(request('/actions/them-khach-hang.php',['HoTen'=>'x'])['status']===403,'missing CSRF 403');
        check(request('/actions/them-khach-hang.php',['_csrf'=>'bad','HoTen'=>'x'])['status']===403,'wrong CSRF 403');
        $post=function(string $path,array $data,string $label) use ($csrf): array {
            $response=request('/actions/'.$path,['_csrf'=>$csrf]+$data);
            check($response['status']===303 && str_starts_with($response['location'],'/'),"POST $label 303 internal redirect"); return $response;
        };
        $httpName=$run.' HTTP Đặng <script>alert(1)</script>';
        $post('them-khach-hang.php',['HoTen'=>$httpName,'SoDienThoai'=>'00001111','DiaChi'=>"O'Việt"],'customer');
        $httpCustomer=(int)(sqlrow('SELECT MaKH FROM KHACHHANG WHERE HoTen=?',[$httpName])['MaKH'] ?? 0); $ids['customers'][]=$httpCustomer;
        check($httpCustomer>0,'HTTP customer persisted');
        $flash=request('/khach-hang.php')['body']; check(str_contains($flash,'Đã lưu thành công.') && str_contains($flash,e($httpName)),'success flash and escaped HTTP record');
        check(!str_contains(request('/khach-hang.php')['body'],'Đã lưu thành công.'),'refresh consumes flash without duplicate write');
        check((int)sqlrow('SELECT COUNT(*) AS n FROM KHACHHANG WHERE HoTen=?',[$httpName])['n']===1,'refresh leaves exactly one customer');
        $post('dat-san.php',['MaKH'=>(string)$httpCustomer,'MaSan'=>(string)$otherPitch,'MaNV'=>(string)$staff,'ThoiGianBatDau'=>'2040-01-10T10:00','ThoiGianKetThuc'=>'2040-01-10T11:30','GiaThueLucDat'=>'1'],'booking');
        $httpBooking=(int)(sqlrow('SELECT MaDatSan FROM DAT_SAN WHERE MaKH=?',[$httpCustomer])['MaDatSan'] ?? 0); $ids['bookings'][]=$httpBooking;
        check($httpBooking>0,'HTTP booking persisted');
        $post('them-dich-vu.php',['TenDV'=>$run.' HTTP nước','DonViTinh'=>'chai','DonGia'=>'10000'],'catalogue service');
        $httpService=(int)(sqlrow('SELECT MaDV FROM DICHVU WHERE TenDV=?',[$run.' HTTP nước'])['MaDV'] ?? 0); $ids['services'][]=$httpService;
        check($httpService>0,'HTTP catalogue service persisted');
        $post('su-dung-dich-vu.php',['MaDatSan'=>(string)$httpBooking,'MaDV'=>(string)$httpService,'SoLuong'=>'2','DonGiaLucDung'=>'1'],'use service');
        $post('lap-hoa-don.php',['MaDatSan'=>(string)$httpBooking,'MaNV'=>(string)$staff,'TongTien'=>'1','TrangThaiThanhToan'=>'DA_THANH_TOAN'],'invoice');
        $httpInvoice=sqlrow('SELECT * FROM HOADON WHERE MaDatSan=?',[$httpBooking]);
        check(($httpInvoice['TongTien'] ?? '')==='470000.00' && $httpInvoice['TrangThaiThanhToan']===config('rules.invoice_unpaid'),'HTTP SQL total/snapshots/status ignore browser overrides');
        $detail=request('/hoa-don.php?MaHD='.$httpInvoice['MaHD']);
        check($detail['status']===200 && str_contains($detail['body'],'470000.00') && str_contains($detail['body'],e($httpName)),'HTTP invoice detail matches SQL');
        $post('lap-hoa-don.php',['MaDatSan'=>(string)$httpBooking,'MaNV'=>(string)$staff],'duplicate invoice');
        check(str_contains(request('/hoa-don.php')['body'],'đã có hóa đơn'),'duplicate invoice shows validation error');
        check((int)sqlrow('SELECT COUNT(*) AS n FROM HOADON WHERE MaDatSan=?',[$httpBooking])['n']===1,'HTTP duplicate leaves one invoice');
        $post('them-khach-hang.php',['HoTen'=>['array'],'SoDienThoai'=>'0'],'invalid array');
        $bad=request('/khach-hang.php')['body']; check(str_contains($bad,'phải là chuỗi đơn.') && !str_contains($bad,'Đã lưu thành công.'),'HTTP invalid array returns error, no false success');
    } else { echo "NOT RUN HTTP (pass --http=http://127.0.0.1:8000)\n"; }
} catch (Throwable $error) {
    ++$failed; fwrite(STDERR,'FAIL uncaught '.get_class($error).': '.$error->getMessage()."\n");
} finally {
    // Delete only IDs created by this run, in FK order. Never truncate/reset shared demo.
    try {
        $pdo->beginTransaction();
        foreach ($ids['bookings'] as $id) {
            foreach (['HOADON','CHITIET_DICHVU','DAT_SAN'] as $table) { $q=$pdo->prepare("DELETE FROM $table WHERE MaDatSan=?"); $q->execute([$id]); }
        }
        foreach (['services'=>['DICHVU','MaDV'],'customers'=>['KHACHHANG','MaKH'],'pitches'=>['SANBONG','MaSan']] as $key=>[$table,$column]) {
            foreach ($ids[$key] as $id) { $q=$pdo->prepare("DELETE FROM $table WHERE $column=?"); $q->execute([$id]); }
        }
        $pdo->commit(); echo "CLEANUP own fixture IDs complete (AUTO_INCREMENT not reset)\n";
    } catch (Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } ++$failed; fwrite(STDERR,"FAIL fixture cleanup; inspect run $run\n"); }
}
echo "RESULT PASS=$passed FAIL=$failed\n";
exit($failed === 0 ? 0 : 1);
