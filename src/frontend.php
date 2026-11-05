<?php
declare(strict_types=1);
require_once __DIR__ . '/business.php';

function frontend_date(array $data, string $key): string
{
    $value = text_value($data,$key,10);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    if (!$date || $date->format('Y-m-d') !== $value || substr($value,0,4) < '1000') {
        throw new InvalidArgumentException("$key phải là ngày YYYY-MM-DD hợp lệ.");
    }
    return $value;
}

function frontend_services(array $data): array
{
    if (!array_key_exists('Services',$data)) { return []; }
    if (!is_string($data['Services']) || strlen($data['Services']) > 65536) {
        throw new InvalidArgumentException('Services phải là JSON danh sách dịch vụ hợp lệ.');
    }
    try { $items = json_decode($data['Services'],false,16,JSON_THROW_ON_ERROR); }
    catch (JsonException $error) { throw new InvalidArgumentException('Services không phải JSON hợp lệ.'); }
    if (!is_array($items) || count($items) > 100) {
        throw new InvalidArgumentException('Services phải là danh sách tối đa 100 dòng.');
    }
    $result = [];
    foreach ($items as $item) {
        if (!$item instanceof stdClass) { throw new InvalidArgumentException('Mỗi dịch vụ phải có MaDV và SoLuong.'); }
        $row = (array)$item;
        $result[] = ['MaDV'=>positive_id($row,'MaDV'),'SoLuong'=>positive_id($row,'SoLuong')];
    }
    return $result;
}

function frontend_add_pitch(PDO $pdo, array $data): int
{
    $name = text_value($data,'TenSan',50); $type = positive_id($data,'MaLoai'); $price = price_value($data,'GiaThue');
    return transaction_do($pdo,function () use ($pdo,$name,$type,$price): int {
        find_row($pdo,'SELECT MaLoai FROM LOAISAN WHERE MaLoai=?',[$type],'Loại sân không tồn tại.');
        $stmt = $pdo->prepare('INSERT INTO SANBONG (TenSan,MaLoai,GiaThue,TinhTrang) VALUES (?,?,?,?)');
        $stmt->execute([$name,$type,$price,config('rules.pitch_active')]);
        return (int)$pdo->lastInsertId();
    });
}

function frontend_add_team(PDO $pdo, array $data): int
{
    $name = text_value($data,'TenDoi',100);
    $captain = text_value($data,'TenDoiTruong',100,false); $phone = text_value($data,'SoDienThoai',15,false);
    $tournament = null;
    if (array_key_exists('MaGiai',$data) && $data['MaGiai'] !== '') { $tournament = positive_id($data,'MaGiai'); }
    return transaction_do($pdo,function () use ($pdo,$name,$captain,$phone,$tournament): int {
        if ($tournament !== null) {
            find_row($pdo,'SELECT MaGiai FROM GIAIDAU WHERE MaGiai=?',[$tournament],'Giải đấu không tồn tại.');
        }
        $stmt = $pdo->prepare('INSERT INTO DOIBONG (TenDoi,TenDoiTruong,SoDienThoai) VALUES (?,?,?)');
        $stmt->execute([$name,$captain === '' ? null : $captain,$phone === '' ? null : $phone]);
        $id = (int)$pdo->lastInsertId();
        if ($tournament !== null) {
            $stmt = $pdo->prepare('INSERT INTO THAMGIA_GIAIDAU (MaGiai,MaDoi) VALUES (?,?)');
            $stmt->execute([$tournament,$id]);
        }
        return $id;
    });
}

function frontend_add_tournament(PDO $pdo, array $data): int
{
    $name = text_value($data,'TenGiai',100);
    $start = frontend_date($data,'NgayBatDau'); $end = frontend_date($data,'NgayKetThuc');
    if ($end < $start) { throw new InvalidArgumentException('Ngày kết thúc không được trước ngày bắt đầu.'); }
    $stmt = $pdo->prepare('INSERT INTO GIAIDAU (TenGiai,NgayBatDau,NgayKetThuc) VALUES (?,?,?)');
    $stmt->execute([$name,$start,$end]); return (int)$pdo->lastInsertId();
}

function frontend_pay_invoice(PDO $pdo, array $data): int
{
    $id = positive_id($data,'MaHD');
    return transaction_do($pdo,function () use ($pdo,$id): int {
        $row = find_row($pdo,'SELECT MaHD,TrangThaiThanhToan FROM HOADON WHERE MaHD=? FOR UPDATE',[$id],'Hóa đơn không tồn tại.');
        if ($row['TrangThaiThanhToan'] !== config('rules.invoice_unpaid')) {
            throw new InvalidArgumentException('Chỉ thanh toán hóa đơn chưa thanh toán; hóa đơn này đã xử lý.');
        }
        $stmt = $pdo->prepare('UPDATE HOADON SET TrangThaiThanhToan=? WHERE MaHD=?');
        $stmt->execute([config('rules.invoice_paid'),$id]); return $id;
    });
}

function frontend_change_booking(PDO $pdo, array $data, bool $receive): int
{
    $id = positive_id($data,'MaDatSan');
    return transaction_do($pdo,function () use ($pdo,$id,$receive): int {
        $row = editable_booking($pdo,$id);
        if ($receive) {
            if ($row['TrangThai'] !== config('rules.booking_new')) {
                throw new InvalidArgumentException('Chỉ nhận sân cho lượt mới đặt chưa được nhận.');
            }
            $now = date('Y-m-d H:i:s');
            if ($now < $row['ThoiGianBatDau'] || $now >= $row['ThoiGianKetThuc']) {
                throw new InvalidArgumentException('Chỉ nhận sân trong khung giờ của lượt đặt.');
            }
            $pitch = find_row($pdo,'SELECT TinhTrang FROM SANBONG WHERE MaSan=?',[$row['MaSan']],'Sân không tồn tại.');
            if ($pitch['TinhTrang'] !== config('rules.pitch_active')) { throw new InvalidArgumentException('Sân không hoạt động.'); }
            $status = config('rules.booking_received');
        } else {
            if (!in_array($row['TrangThai'],[config('rules.booking_new'),config('rules.booking_received')],true)) {
                throw new InvalidArgumentException('Trạng thái lượt đặt không cho phép hủy.');
            }
            $status = config('rules.booking_cancelled');
        }
        $stmt = $pdo->prepare('UPDATE DAT_SAN SET TrangThai=? WHERE MaDatSan=?');
        $stmt->execute([$status,$id]); return $id;
    });
}

function frontend_operation(PDO $pdo, array $data): array
{
    $operation = text_value($data,'operation',40);
    $id = match ($operation) {
        'add_customer' => add_customer($pdo,$data),
        'add_service' => add_service($pdo,$data),
        'add_pitch' => frontend_add_pitch($pdo,$data),
        'add_booking' => add_booking($pdo,$data,frontend_services($data)),
        'use_service' => use_service($pdo,$data),
        'make_invoice' => make_invoice($pdo,$data),
        'pay_invoice' => frontend_pay_invoice($pdo,$data),
        'receive_booking' => frontend_change_booking($pdo,$data,true),
        'cancel_booking' => frontend_change_booking($pdo,$data,false),
        'add_team' => frontend_add_team($pdo,$data),
        'add_tournament' => frontend_add_tournament($pdo,$data),
        default => throw new InvalidArgumentException('Thao tác không được hỗ trợ.'),
    };
    return ['ok'=>true,'id'=>$id,'message'=>'Đã lưu thành công.'];
}

function frontend_data(PDO $pdo): array
{
    $now = new DateTimeImmutable(); $today = $now->format('Y-m-d');
    $dayStart = $today . ' 00:00:00'; $dayEnd = $now->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    $clock = $now->format('Y-m-d H:i:s');
    // All lists/statistics come from the same database snapshot; a failure is never an empty success.
    $pdo->beginTransaction();
    try {
        $customers = query_rows($pdo,'SELECT * FROM KHACHHANG ORDER BY MaKH DESC');
        $pitches = query_rows($pdo,'SELECT s.*,l.TenLoai,l.SoNguoi,CASE WHEN s.TinhTrang<>? THEN \'maintenance\' WHEN EXISTS (SELECT 1 FROM DAT_SAN d WHERE d.MaSan=s.MaSan AND d.TrangThai IN (?,?) AND d.ThoiGianBatDau<=? AND d.ThoiGianKetThuc>?) THEN \'busy\' ELSE \'free\' END AS StatusCode FROM SANBONG s JOIN LOAISAN l ON l.MaLoai=s.MaLoai ORDER BY s.MaSan',[config('rules.pitch_active'),config('rules.booking_new'),config('rules.booking_received'),$clock,$clock]);
        $bookings = query_rows($pdo,'SELECT d.*,k.HoTen AS TenKhach,s.TenSan,n.HoTen AS TenNhanVien,h.MaHD,ROUND(TIMESTAMPDIFF(MINUTE,d.ThoiGianBatDau,d.ThoiGianKetThuc)*d.GiaThueLucDat/60 + COALESCE((SELECT SUM(c.SoLuong*c.DonGiaLucDung) FROM CHITIET_DICHVU c WHERE c.MaDatSan=d.MaDatSan),0),2) AS TongTien FROM DAT_SAN d JOIN KHACHHANG k ON k.MaKH=d.MaKH JOIN SANBONG s ON s.MaSan=d.MaSan JOIN NHANVIEN n ON n.MaNV=d.MaNV LEFT JOIN HOADON h ON h.MaDatSan=d.MaDatSan ORDER BY d.ThoiGianBatDau DESC,d.MaDatSan DESC');
        $invoices = query_rows($pdo,'SELECT h.*,k.HoTen AS TenKhach,s.TenSan,n.HoTen AS TenNhanVien FROM HOADON h JOIN DAT_SAN d ON d.MaDatSan=h.MaDatSan JOIN KHACHHANG k ON k.MaKH=d.MaKH JOIN SANBONG s ON s.MaSan=d.MaSan JOIN NHANVIEN n ON n.MaNV=h.MaNV ORDER BY h.MaHD DESC');
        // Schema has no payment timestamp: revenue is PAID invoices grouped by invoice issue date.
        $revenue = find_row($pdo,'SELECT CAST(COALESCE(SUM(TongTien),0) AS DECIMAL(22,2)) AS Total FROM HOADON WHERE NgayLap=? AND TrangThaiThanhToan=?',[$today,config('rules.invoice_paid')],'Không tính được doanh thu.');
        $bookingCount = find_row($pdo,'SELECT COUNT(*) AS Total FROM DAT_SAN WHERE TrangThai<>? AND ThoiGianBatDau<? AND ThoiGianKetThuc>?',[config('rules.booking_cancelled'),$dayEnd,$dayStart],'Không tính được lượt đặt.');
        $data = [
            'today'=>$today,'customers'=>$customers,'pitches'=>$pitches,
            'pitchTypes'=>query_rows($pdo,'SELECT * FROM LOAISAN ORDER BY MaLoai'),
            'services'=>query_rows($pdo,'SELECT * FROM DICHVU ORDER BY MaDV'),
            'staff'=>query_rows($pdo,'SELECT * FROM NHANVIEN ORDER BY MaNV'),
            'bookings'=>$bookings,
            'serviceUsage'=>query_rows($pdo,'SELECT c.*,v.TenDV,v.DonViTinh FROM CHITIET_DICHVU c JOIN DICHVU v ON v.MaDV=c.MaDV ORDER BY c.MaDatSan,c.MaDV'),
            'invoices'=>$invoices,
            'teams'=>query_rows($pdo,'SELECT * FROM DOIBONG ORDER BY MaDoi'),
            'tournaments'=>query_rows($pdo,'SELECT * FROM GIAIDAU ORDER BY NgayBatDau DESC,MaGiai DESC'),
            'participation'=>query_rows($pdo,'SELECT * FROM THAMGIA_GIAIDAU ORDER BY MaGiai,MaDoi'),
            'matches'=>query_rows($pdo,'SELECT t.*,g.TenGiai,s.TenSan,n.TenDoi AS TenDoiNha,k.TenDoi AS TenDoiKhach FROM TRANDAU t JOIN GIAIDAU g ON g.MaGiai=t.MaGiai JOIN SANBONG s ON s.MaSan=t.MaSan JOIN DOIBONG n ON n.MaDoi=t.MaDoiNha JOIN DOIBONG k ON k.MaDoi=t.MaDoiKhach ORDER BY t.ThoiGianThiDau DESC,t.MaTran DESC'),
            'stats'=>['revenueToday'=>(string)$revenue['Total'],'bookingsToday'=>(int)$bookingCount['Total'],'freePitches'=>count(array_filter($pitches,fn(array $pitch): bool => $pitch['StatusCode'] === 'free')),'totalPitches'=>count($pitches),'customers'=>count($customers)],
            'rules'=>config('rules'),
        ];
        $pdo->commit(); return $data;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $error;
    }
}
