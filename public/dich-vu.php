<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/view.php';
render_page('Danh mục và sử dụng dịch vụ', function (PDO $pdo): void {
    echo '<h3>Thêm danh mục dịch vụ</h3>'; form_start('them-dich-vu.php');
    input_field('TenDV','Tên dịch vụ','text','required maxlength="100"');
    input_field('DonViTinh','Đơn vị tính','text','required maxlength="30"');
    input_field('DonGia','Đơn giá VND','number','required min="0" max="9999999999.99" step="0.01"'); form_end('Thêm danh mục');
    $services = query_rows($pdo,'SELECT MaDV,TenDV,DonViTinh,DonGia FROM DICHVU ORDER BY MaDV');
    data_table($services,['MaDV'=>'Mã dịch vụ','TenDV'=>'Tên','DonViTinh'=>'Đơn vị','DonGia'=>'Giá hiện tại']);
    echo '<h3>Ghi nhận dịch vụ cho lượt đặt</h3><p>Thêm lại cùng dịch vụ sẽ cộng số lượng và giữ đơn giá đã ghi nhận. Không cho lượt hủy hoặc đã có hóa đơn.</p>';
    form_start('su-dung-dich-vu.php'); select_field('MaDatSan','Lượt đặt',booking_options($pdo),'MaDatSan','Nhan');
    select_field('MaDV','Dịch vụ',$services,'MaDV','TenDV'); input_field('SoLuong','Số lượng','number','required min="1" max="2147483647" step="1"'); form_end('Ghi nhận sử dụng');
    data_table(query_rows($pdo,'SELECT c.MaDatSan,k.HoTen,s.TenSan,v.TenDV,c.SoLuong,c.DonGiaLucDung,c.SoLuong*c.DonGiaLucDung AS ThanhTien FROM CHITIET_DICHVU c JOIN DICHVU v ON v.MaDV=c.MaDV JOIN DAT_SAN d ON d.MaDatSan=c.MaDatSan JOIN KHACHHANG k ON k.MaKH=d.MaKH JOIN SANBONG s ON s.MaSan=d.MaSan ORDER BY c.MaDatSan DESC,c.MaDV'),['MaDatSan'=>'Mã lượt','HoTen'=>'Khách','TenSan'=>'Sân','TenDV'=>'Dịch vụ','SoLuong'=>'Số lượng','DonGiaLucDung'=>'Đơn giá snapshot','ThanhTien'=>'Thành tiền']);
});
