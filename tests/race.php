<?php
declare(strict_types=1);
require_once __DIR__.'/../src/business.php';
$settings=require __DIR__.'/../config/database.local.php';
if (!in_array($settings['host'] ?? '',['127.0.0.1','localhost'],true) || (int)($settings['port'] ?? 0)!==33307
    || strcasecmp((string)($settings['database'] ?? ''),'QuanLySanBong_demo')!==0) { fwrite(STDERR,"REFUSED demo identity\n"); exit(2); }
$pdo=db(); $identity=$pdo->query('SELECT @@port AS port,DATABASE() AS dbname')->fetch();
if ((int)$identity['port']!==33307 || strcasecmp($identity['dbname'],'QuanLySanBong_demo')!==0) { exit(2); }
$passed=0;$failed=0;$customer=0;$pitch=0;$service=0;$run='RACE-'.bin2hex(random_bytes(5));
function assertion(bool $ok,string $label): void { global $passed,$failed; $ok ? ++$passed : ++$failed; echo ($ok?'PASS ':'FAIL ').$label."\n"; }
function pair(array $jobs): array {
    $processes=[];$files=[];$results=[];$at=microtime(true)+1;
    try {
        foreach ($jobs as $job) {
            $job['at']=$at; $out=tempnam(sys_get_temp_dir(),'qa-out-'); $err=tempnam(sys_get_temp_dir(),'qa-err-'); $files[]=[$out,$err];
            $proc=proc_open([PHP_BINARY,'-c',php_ini_loaded_file(),__DIR__.'/race-worker.php',base64_encode(json_encode($job,JSON_THROW_ON_ERROR))],
                [0=>['file',PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null','r'],1=>['file',$out,'w'],2=>['file',$err,'w']],$pipes,dirname(PHP_BINARY));
            if (!is_resource($proc)) { throw new RuntimeException('Cannot launch race worker'); } $processes[]=$proc;
        }
        foreach ($processes as $i=>$proc) {
            $exit=proc_close($proc); $raw=file_get_contents($files[$i][0]); $result=json_decode($raw,true);
            if ($exit!==0 || !is_array($result)) { throw new RuntimeException('Race child failed: '.file_get_contents($files[$i][1])); }
            $results[]=$result;
        }
        return $results;
    } finally { foreach ($files as [$out,$err]) { unlink($out);unlink($err); } }
}
try {
    echo "RUN $run separate PHP workers, simultaneous start barrier\n";
    $staff=(int)$pdo->query('SELECT MaNV FROM NHANVIEN ORDER BY MaNV LIMIT 1')->fetchColumn();
    $type=(int)$pdo->query('SELECT MaLoai FROM LOAISAN ORDER BY MaLoai LIMIT 1')->fetchColumn();
    $customer=add_customer($pdo,['HoTen'=>$run,'SoDienThoai'=>'000123']);
    $q=$pdo->prepare('INSERT INTO SANBONG(TenSan,MaLoai,GiaThue,TinhTrang) VALUES (?,?,?,?)');$q->execute([$run,$type,'300000',config('rules.pitch_active')]);$pitch=(int)$pdo->lastInsertId();
    $service=add_service($pdo,['TenDV'=>$run,'DonViTinh'=>'chai','DonGia'=>'10000']);
    $bookingData=['MaKH'=>$customer,'MaSan'=>$pitch,'MaNV'=>$staff,'ThoiGianBatDau'=>'2041-01-01T10:00','ThoiGianKetThuc'=>'2041-01-01T11:30'];
    $job=['operation'=>'add_booking','data'=>$bookingData]; $results=pair([$job,$job]);
    $statuses=array_column($results,'status');sort($statuses);
    assertion($statuses===['ok','rejected'],'two overlapping bookings: exactly one commits');
    $q=$pdo->prepare('SELECT MaDatSan FROM DAT_SAN WHERE MaKH=?');$q->execute([$customer]);$booking=(int)$q->fetchColumn();
    $job=['operation'=>'use_service','data'=>['MaDatSan'=>$booking,'MaDV'=>$service,'SoLuong'=>'1']];$results=pair([$job,$job]);
    assertion(array_column($results,'status')===['ok','ok'],'two service increments both commit');
    $q=$pdo->prepare('SELECT SoLuong FROM CHITIET_DICHVU WHERE MaDatSan=? AND MaDV=?');$q->execute([$booking,$service]);
    assertion((int)$q->fetchColumn()===2,'concurrent service quantity has no lost update');
    $job=['operation'=>'make_invoice','data'=>['MaDatSan'=>$booking,'MaNV'=>$staff]];$results=pair([$job,$job]);
    $statuses=array_column($results,'status');sort($statuses);assertion($statuses===['ok','rejected'],'two invoices: exactly one commits');
    $q=$pdo->prepare('SELECT COUNT(*) AS n,MAX(TongTien) AS total FROM HOADON WHERE MaDatSan=?');$q->execute([$booking]);$row=$q->fetch();
    assertion((int)$row['n']===1 && $row['total']==='470000.00','one invoice and exact total after concurrent writes');
    $bookingData['ThoiGianBatDau']='2041-01-02T10:00';$bookingData['ThoiGianKetThuc']='2041-01-02T11:30';$second=add_booking($pdo,$bookingData);
    $results=pair([
        ['operation'=>'use_service','data'=>['MaDatSan'=>$second,'MaDV'=>$service,'SoLuong'=>'1']],
        ['operation'=>'make_invoice','data'=>['MaDatSan'=>$second,'MaNV'=>$staff]]
    ]);
    $q=$pdo->prepare('SELECT h.TongTien,(SELECT COALESCE(SUM(c.SoLuong*c.DonGiaLucDung),0) FROM CHITIET_DICHVU c WHERE c.MaDatSan=h.MaDatSan) AS serviceTotal FROM HOADON h WHERE h.MaDatSan=?');$q->execute([$second]);$row=$q->fetch();
    assertion($results[1]['status']==='ok' && in_array($results[0]['status'],['ok','rejected'],true)
        && (float)$row['TongTien']===450000.0+(float)$row['serviceTotal'],'service vs invoice race stays consistent in either lock order');
} catch (Throwable $error) { ++$failed;fwrite(STDERR,'FAIL '.get_class($error).': '.$error->getMessage()."\n"); }
finally {
    try {
        $pdo->beginTransaction();
        $q=$pdo->prepare('SELECT MaDatSan FROM DAT_SAN WHERE MaKH=?');$q->execute([$customer]);$bookings=$q->fetchAll(PDO::FETCH_COLUMN);
        foreach ($bookings as $id) { foreach (['HOADON','CHITIET_DICHVU','DAT_SAN'] as $table) {$q=$pdo->prepare("DELETE FROM $table WHERE MaDatSan=?");$q->execute([$id]);} }
        foreach ([['KHACHHANG','MaKH',$customer],['SANBONG','MaSan',$pitch],['DICHVU','MaDV',$service]] as [$table,$column,$id]) {$q=$pdo->prepare("DELETE FROM $table WHERE $column=?");$q->execute([$id]);}
        $pdo->commit();echo "CLEANUP own fixture IDs complete\n";
    } catch (Throwable $error) {if($pdo->inTransaction()){$pdo->rollBack();}++$failed;fwrite(STDERR,"FAIL cleanup $run\n");}
}
echo "RESULT PASS=$passed FAIL=$failed\n";exit($failed===0?0:1);
