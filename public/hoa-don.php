<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/view.php';
render_page('Lập và xem hóa đơn', function (PDO $pdo): void {
    echo '<p>Tổng do backend tính từ giá đã ghi nhận. Lập hóa đơn chuyển lượt sang hoàn thành; trạng thái thanh toán ban đầu là chưa thanh toán.</p>';
    form_start('lap-hoa-don.php'); select_field('MaDatSan','Lượt đặt',booking_options($pdo),'MaDatSan','Nhan');
    select_field('MaNV','Nhân viên lập',query_rows($pdo,'SELECT MaNV,HoTen FROM NHANVIEN ORDER BY MaNV'),'MaNV','HoTen'); form_end('Lập hóa đơn');
    $invoices = query_rows($pdo,'SELECT h.*,k.HoTen AS TenKhach,s.TenSan,n.HoTen AS TenNhanVien FROM HOADON h JOIN DAT_SAN d ON d.MaDatSan=h.MaDatSan JOIN KHACHHANG k ON k.MaKH=d.MaKH JOIN SANBONG s ON s.MaSan=d.MaSan JOIN NHANVIEN n ON n.MaNV=h.MaNV ORDER BY h.MaHD DESC');
    data_table($invoices,['MaHD'=>'Mã hóa đơn','MaDatSan'=>'Mã lượt','TenKhach'=>'Khách','TenSan'=>'Sân','TenNhanVien'=>'Người lập','NgayLap'=>'Ngày lập','TongTien'=>'Tổng đã lưu','TrangThaiThanhToan'=>'Thanh toán']);
    echo '<p>Chi tiết: '; foreach ($invoices as $invoice) { echo '<a href="' . e(url('/hoa-don.php?MaHD=' . $invoice['MaHD'])) . '">#' . e($invoice['MaHD']) . '</a> '; } echo '</p>';
    if (isset($_GET['MaHD'])) {
        $id = positive_id($_GET,'MaHD');
        $invoice = find_row($pdo,'SELECT h.*,d.ThoiGianBatDau,d.ThoiGianKetThuc,d.GiaThueLucDat,k.HoTen,s.TenSan,ROUND(TIMESTAMPDIFF(MINUTE,d.ThoiGianBatDau,d.ThoiGianKetThuc)*d.GiaThueLucDat/60,2) AS TienSan FROM HOADON h JOIN DAT_SAN d ON d.MaDatSan=h.MaDatSan JOIN KHACHHANG k ON k.MaKH=d.MaKH JOIN SANBONG s ON s.MaSan=d.MaSan WHERE h.MaHD=?',[$id],'Hóa đơn không tồn tại.');
        echo '<h3>Chi tiết hóa đơn #' . e($id) . '</h3>';
        data_table([$invoice],['HoTen'=>'Khách','TenSan'=>'Sân','ThoiGianBatDau'=>'Bắt đầu','ThoiGianKetThuc'=>'Kết thúc','GiaThueLucDat'=>'Giá/giờ snapshot','TienSan'=>'Tiền sân','TongTien'=>'Tổng hóa đơn đã lưu','TrangThaiThanhToan'=>'Thanh toán']);
        $items = query_rows($pdo,'SELECT v.TenDV,c.SoLuong,c.DonGiaLucDung,c.SoLuong*c.DonGiaLucDung AS ThanhTien FROM CHITIET_DICHVU c JOIN DICHVU v ON v.MaDV=c.MaDV WHERE c.MaDatSan=? ORDER BY c.MaDV',[$invoice['MaDatSan']]);
        if (!$items) { echo '<p>Không dùng dịch vụ: tiền dịch vụ = 0.</p>'; }
        else { data_table($items,['TenDV'=>'Dịch vụ','SoLuong'=>'Số lượng','DonGiaLucDung'=>'Đơn giá snapshot','ThanhTien'=>'Thành tiền']); }
    }
});
