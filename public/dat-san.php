<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/view.php';
render_page('Đặt sân và lịch đặt', function (PDO $pdo): void {
    echo '<p>Giờ địa phương Việt Nam, chính xác đến phút. Hai lượt tiếp giáp được phép; giá thuê được lấy từ DB khi lưu.</p>';
    form_start('dat-san.php');
    select_field('MaKH','Khách hàng',query_rows($pdo,'SELECT MaKH,HoTen FROM KHACHHANG ORDER BY MaKH DESC'),'MaKH','HoTen');
    select_field('MaSan','Sân',query_rows($pdo,'SELECT MaSan,CONCAT(TenSan," · ",TinhTrang," · ",GiaThue," đ/giờ") AS Nhan FROM SANBONG ORDER BY MaSan'),'MaSan','Nhan');
    select_field('MaNV','Nhân viên',query_rows($pdo,'SELECT MaNV,HoTen FROM NHANVIEN ORDER BY MaNV'),'MaNV','HoTen');
    input_field('ThoiGianBatDau','Bắt đầu','datetime-local','required step="60"');
    input_field('ThoiGianKetThuc','Kết thúc','datetime-local','required step="60"'); form_end('Đặt sân');
    data_table(booking_rows($pdo),['MaDatSan'=>'Mã lượt','TenKhach'=>'Khách','TenSan'=>'Sân','TenNhanVien'=>'Nhân viên','ThoiGianBatDau'=>'Bắt đầu','ThoiGianKetThuc'=>'Kết thúc','GiaThueLucDat'=>'Giá/giờ lúc đặt','TrangThai'=>'Trạng thái']);
});
