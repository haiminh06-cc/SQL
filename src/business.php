<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function text_value(array $data, string $key, int $max, bool $required = true): string
{
    $value = $data[$key] ?? '';
    if (!is_string($value)) { throw new InvalidArgumentException("$key phải là chuỗi đơn."); }
    $value = trim($value);
    $length = preg_match_all('/./us', $value);
    if ($length === false || ($required && $value === '') || $length > $max) {
        throw new InvalidArgumentException("$key bắt buộc hợp lệ và không quá $max ký tự.");
    }
    return $value;
}
function positive_id(array $data, string $key): int
{
    $value = $data[$key] ?? null;
    if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)
        || strlen((string)$value) > 10 || (float)$value > 2147483647) {
        throw new InvalidArgumentException("$key phải là số nguyên dương hợp lệ.");
    }
    return (int)$value;
}
function price_value(array $data, string $key): string
{
    $value = $data[$key] ?? null;
    if (!is_string($value) || !preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D', trim($value))) {
        throw new InvalidArgumentException("$key phải là số tiền không âm, tối đa 10 chữ số nguyên và 2 số lẻ.");
    }
    return trim($value);
}
function booking_time(array $data, string $key): string
{
    $value = $data[$key] ?? null;
    if (!is_string($value)) { throw new InvalidArgumentException("$key không hợp lệ."); }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
    if (!$date || $date->format('Y-m-d\TH:i') !== $value || substr($value, 0, 4) < '1000') {
        throw new InvalidArgumentException("$key phải đúng ngày giờ, đến phút.");
    }
    return $date->format('Y-m-d H:i:s');
}
function query_rows(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
}
function find_row(PDO $pdo, string $sql, array $params, string $message): array
{
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $row = $stmt->fetch();
    if (!$row) { throw new InvalidArgumentException($message); }
    return $row;
}
function transaction_do(PDO $pdo, callable $operation): int
{
    $pdo->beginTransaction();
    try { $id = $operation(); $pdo->commit(); return $id; }
    catch (Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
}
function add_customer(PDO $pdo, array $data): int
{
    $name = text_value($data, 'HoTen', 100); $phone = text_value($data, 'SoDienThoai', 15);
    $address = text_value($data, 'DiaChi', 200, false);
    $stmt = $pdo->prepare('INSERT INTO KHACHHANG (HoTen,SoDienThoai,DiaChi) VALUES (?,?,?)');
    $stmt->execute([$name,$phone,$address === '' ? null : $address]); return (int)$pdo->lastInsertId();
}
function add_service(PDO $pdo, array $data): int
{
    $name = text_value($data, 'TenDV', 100); $unit = text_value($data, 'DonViTinh', 30); $price = price_value($data, 'DonGia');
    $stmt = $pdo->prepare('INSERT INTO DICHVU (TenDV,DonViTinh,DonGia) VALUES (?,?,?)');
    $stmt->execute([$name,$unit,$price]); return (int)$pdo->lastInsertId();
}
function add_booking(PDO $pdo, array $data, array $services = []): int
{
    $customer = positive_id($data,'MaKH'); $pitch = positive_id($data,'MaSan'); $staff = positive_id($data,'MaNV');
    $start = booking_time($data,'ThoiGianBatDau'); $end = booking_time($data,'ThoiGianKetThuc');
    if ($end <= $start) { throw new InvalidArgumentException('Giờ kết thúc phải sau giờ bắt đầu.'); }
    return transaction_do($pdo, function () use ($pdo,$customer,$pitch,$staff,$start,$end,$services): int {
        $field = find_row($pdo,'SELECT MaSan,GiaThue,TinhTrang FROM SANBONG WHERE MaSan=? FOR UPDATE',[$pitch],'Sân không tồn tại.');
        if ($field['TinhTrang'] !== config('rules.pitch_active')) { throw new InvalidArgumentException('Sân không hoạt động hoặc đang bảo trì.'); }
        find_row($pdo,'SELECT MaKH FROM KHACHHANG WHERE MaKH=?',[$customer],'Khách hàng không tồn tại.');
        find_row($pdo,'SELECT MaNV FROM NHANVIEN WHERE MaNV=?',[$staff],'Nhân viên không tồn tại.');
        $conflicts = query_rows($pdo,'SELECT MaDatSan FROM DAT_SAN WHERE MaSan=? AND TrangThai<>? AND ThoiGianBatDau<? AND ThoiGianKetThuc>? FOR UPDATE',[$pitch,config('rules.booking_cancelled'),$end,$start]);
        if ($conflicts) { throw new InvalidArgumentException('Sân đã có lượt đặt trùng khung giờ.'); }
        $stmt = $pdo->prepare('INSERT INTO DAT_SAN (MaKH,MaSan,MaNV,ThoiGianBatDau,ThoiGianKetThuc,GiaThueLucDat,TrangThai) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$customer,$pitch,$staff,$start,$end,$field['GiaThue'],config('rules.booking_new')]);
        $id = (int)$pdo->lastInsertId();
        foreach ($services as $service) {
            if (!is_array($service)) { throw new InvalidArgumentException('Dịch vụ đặt kèm không hợp lệ.'); }
            apply_booking_service($pdo,$id,positive_id($service,'MaDV'),positive_id($service,'SoLuong'));
        }
        return $id;
    });
}
function editable_booking(PDO $pdo, int $id): array
{
    $row = find_row($pdo,'SELECT * FROM DAT_SAN WHERE MaDatSan=? FOR UPDATE',[$id],'Lượt đặt không tồn tại.');
    if ($row['TrangThai'] === config('rules.booking_cancelled')) { throw new InvalidArgumentException('Lượt đặt đã hủy.'); }
    if (query_rows($pdo,'SELECT MaHD FROM HOADON WHERE MaDatSan=? FOR UPDATE',[$id])) {
        throw new InvalidArgumentException('Lượt đặt đã có hóa đơn, không được thay đổi hoặc lập lại.');
    }
    return $row;
}
// Caller holds the booking row lock (or has just inserted it) inside a transaction.
function apply_booking_service(PDO $pdo, int $booking, int $service, int $quantity): void
{
    $item = find_row($pdo,'SELECT DonGia FROM DICHVU WHERE MaDV=?',[$service],'Dịch vụ không tồn tại.');
    $old = query_rows($pdo,'SELECT SoLuong FROM CHITIET_DICHVU WHERE MaDatSan=? AND MaDV=? FOR UPDATE',[$booking,$service]);
    if ($old) {
        if ((int)$old[0]['SoLuong'] > 2147483647 - $quantity) { throw new InvalidArgumentException('Tổng số lượng vượt giới hạn.'); }
        $stmt = $pdo->prepare('UPDATE CHITIET_DICHVU SET SoLuong=SoLuong+? WHERE MaDatSan=? AND MaDV=?');
        $stmt->execute([$quantity,$booking,$service]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO CHITIET_DICHVU (MaDatSan,MaDV,SoLuong,DonGiaLucDung) VALUES (?,?,?,?)');
        $stmt->execute([$booking,$service,$quantity,$item['DonGia']]);
    }
}
function use_service(PDO $pdo, array $data): int
{
    $booking = positive_id($data,'MaDatSan'); $service = positive_id($data,'MaDV'); $quantity = positive_id($data,'SoLuong');
    return transaction_do($pdo, function () use ($pdo,$booking,$service,$quantity): int {
        editable_booking($pdo,$booking);
        apply_booking_service($pdo,$booking,$service,$quantity);
        return $booking;
    });
}
function make_invoice(PDO $pdo, array $data): int
{
    $booking = positive_id($data,'MaDatSan'); $staff = positive_id($data,'MaNV');
    return transaction_do($pdo, function () use ($pdo,$booking,$staff): int {
        $row = editable_booking($pdo,$booking);
        find_row($pdo,'SELECT MaNV FROM NHANVIEN WHERE MaNV=?',[$staff],'Nhân viên không tồn tại.');
        if ($row['ThoiGianKetThuc'] <= $row['ThoiGianBatDau'] || (float)$row['GiaThueLucDat'] < 0) {
            throw new InvalidArgumentException('Dữ liệu thời gian hoặc giá của lượt đặt không hợp lệ.');
        }
        $total = find_row($pdo,'SELECT ROUND(TIMESTAMPDIFF(MINUTE,d.ThoiGianBatDau,d.ThoiGianKetThuc)*d.GiaThueLucDat/60 + COALESCE((SELECT SUM(c.SoLuong*c.DonGiaLucDung) FROM CHITIET_DICHVU c WHERE c.MaDatSan=d.MaDatSan),0),2) AS TongTien FROM DAT_SAN d WHERE d.MaDatSan=?',[$booking],'Lượt đặt không tồn tại.')['TongTien'];
        if ((float)$total < 0 || (float)$total > 9999999999.99) { throw new InvalidArgumentException('Tổng hóa đơn vượt giới hạn DECIMAL(12,2).'); }
        $stmt = $pdo->prepare('INSERT INTO HOADON (MaDatSan,MaNV,NgayLap,TongTien,TrangThaiThanhToan) VALUES (?,?,?,?,?)');
        $stmt->execute([$booking,$staff,date('Y-m-d'),$total,config('rules.invoice_unpaid')]); $id = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare('UPDATE DAT_SAN SET TrangThai=? WHERE MaDatSan=?'); $stmt->execute([config('rules.booking_completed'),$booking]);
        return $id;
    });
}
function booking_rows(PDO $pdo): array
{
    return query_rows($pdo,'SELECT d.*,k.HoTen AS TenKhach,s.TenSan,n.HoTen AS TenNhanVien FROM DAT_SAN d JOIN KHACHHANG k ON k.MaKH=d.MaKH JOIN SANBONG s ON s.MaSan=d.MaSan JOIN NHANVIEN n ON n.MaNV=d.MaNV ORDER BY d.ThoiGianBatDau DESC,d.MaDatSan DESC');
}
function safe_log(Throwable $error): void
{
    // Never log exception messages, SQL bindings, DSN, or credentials.
    error_log('Backend demo failure [' . get_class($error) . '] code=' . (int)$error->getCode());
}
function handle_action(string $operation, string $returnPage): void
{
    require_post(); verify_csrf();
    try {
        $id = $operation(db(), $_POST); set_flash('success','Đã lưu thành công. Mã bản ghi: ' . $id);
    } catch (InvalidArgumentException $error) { set_flash('error',$error->getMessage()); }
    catch (Throwable $error) { safe_log($error); set_flash('error','Không thể lưu dữ liệu. Hãy kiểm tra kết nối hoặc thử lại.'); }
    redirect($returnPage);
}
