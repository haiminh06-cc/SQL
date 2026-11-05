<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/view.php';
render_page('Danh sách sân', function (PDO $pdo): void {
    echo '<p>Giá VND/giờ. Trạng thái hoạt động/bảo trì không thể hiện sân trống theo thời gian; hãy xem lịch đặt.</p>';
    data_table(query_rows($pdo,'SELECT s.MaSan,s.TenSan,l.TenLoai,l.SoNguoi,s.GiaThue,s.TinhTrang FROM SANBONG s JOIN LOAISAN l ON l.MaLoai=s.MaLoai ORDER BY s.MaSan'),['MaSan'=>'Mã sân','TenSan'=>'Tên sân','TenLoai'=>'Loại sân','SoNguoi'=>'Số người','GiaThue'=>'Giá/giờ','TinhTrang'=>'Tình trạng']);
});
