-- Truy vấn xem dữ liệu và đối chiếu tiền sân, dịch vụ, hóa đơn.
-- Chỉ SELECT; đặt @MaDatSan bằng mã thật lấy từ website/seed, không đoán ID = 1.
USE QuanLySanBong_demo;
SET @MaDatSan = NULL; -- người kiểm thử tự nhập mã thực tế trước các SELECT chi tiết.

SELECT s.MaSan, s.TenSan, l.TenLoai, s.GiaThue, s.TinhTrang
FROM SANBONG s JOIN LOAISAN l ON l.MaLoai = s.MaLoai ORDER BY s.MaSan;
SELECT MaKH, HoTen, SoDienThoai, DiaChi FROM KHACHHANG ORDER BY MaKH DESC;
SELECT d.MaDatSan, k.HoTen AS TenKhach, s.TenSan, n.HoTen AS TenNhanVien,
       d.ThoiGianBatDau, d.ThoiGianKetThuc, d.GiaThueLucDat, d.TrangThai
FROM DAT_SAN d JOIN KHACHHANG k ON k.MaKH = d.MaKH
JOIN SANBONG s ON s.MaSan = d.MaSan JOIN NHANVIEN n ON n.MaNV = d.MaNV
ORDER BY d.ThoiGianBatDau DESC;

SELECT c.MaDatSan, v.TenDV, c.SoLuong, c.DonGiaLucDung,
       c.SoLuong * c.DonGiaLucDung AS ThanhTien
FROM CHITIET_DICHVU c JOIN DICHVU v ON v.MaDV = c.MaDV
WHERE c.MaDatSan = @MaDatSan;

-- Tổng hợp dịch vụ riêng để KHÔNG nhân tiền sân khi có nhiều dịch vụ.
SELECT d.MaDatSan,
       TIMESTAMPDIFF(MINUTE, d.ThoiGianBatDau, d.ThoiGianKetThuc) AS SoPhut,
       ROUND(TIMESTAMPDIFF(MINUTE, d.ThoiGianBatDau, d.ThoiGianKetThuc)
             * d.GiaThueLucDat / 60, 2) AS TienSan,
       COALESCE(c.TienDichVu, 0) AS TienDichVu,
       ROUND(TIMESTAMPDIFF(MINUTE, d.ThoiGianBatDau, d.ThoiGianKetThuc)
             * d.GiaThueLucDat / 60 + COALESCE(c.TienDichVu, 0), 2) AS TongTinhLai,
       h.MaHD, h.NgayLap, h.TongTien AS TongDaLuu, h.TrangThaiThanhToan
FROM DAT_SAN d
LEFT JOIN (SELECT MaDatSan, SUM(SoLuong * DonGiaLucDung) AS TienDichVu
           FROM CHITIET_DICHVU GROUP BY MaDatSan) c ON c.MaDatSan = d.MaDatSan
LEFT JOIN HOADON h ON h.MaDatSan = d.MaDatSan
WHERE d.MaDatSan = @MaDatSan;

-- Đối chiếu ràng buộc một hóa đơn/lượt (mong đợi 0 dòng).
SELECT MaDatSan, COUNT(*) AS SoHoaDon
FROM HOADON GROUP BY MaDatSan HAVING COUNT(*) > 1;
