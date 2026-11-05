<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/view.php';
render_page('Khách hàng', function (PDO $pdo): void {
    form_start('them-khach-hang.php');
    input_field('HoTen','Họ tên','text','required maxlength="100"');
    input_field('SoDienThoai','Số điện thoại (giữ số 0 đầu)','tel','required maxlength="15"');
    input_field('DiaChi','Địa chỉ (tùy chọn)','text','maxlength="200"'); form_end('Thêm khách hàng');
    data_table(query_rows($pdo,'SELECT MaKH,HoTen,SoDienThoai,DiaChi FROM KHACHHANG ORDER BY MaKH DESC'),['MaKH'=>'Mã khách','HoTen'=>'Họ tên','SoDienThoai'=>'Số điện thoại','DiaChi'=>'Địa chỉ']);
});
