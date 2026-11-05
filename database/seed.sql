-- Dữ liệu mẫu cho ứng dụng quản lý sân bóng.
-- Chỉ chạy một lần sau schema.sql trên database QuanLySanBong_demo mới, chưa có dữ liệu.
-- Không dùng --force; nếu lỗi hãy ROLLBACK, không tiếp tục chạy các đoạn còn lại.
-- Không nhập vào database đang sử dụng. Các mã được lấy bằng LAST_INSERT_ID().
USE QuanLySanBong_demo;
SET NAMES utf8mb4;
SET time_zone = '+07:00';
START TRANSACTION;

INSERT INTO LOAISAN (TenLoai, SoNguoi) VALUES ('[DEMO] Sân 5 người', 5);
SET @demo_loai_5 = LAST_INSERT_ID();
INSERT INTO LOAISAN (TenLoai, SoNguoi) VALUES ('[DEMO] Sân 7 người', 7);
SET @demo_loai_7 = LAST_INSERT_ID();

INSERT INTO NHANVIEN (HoTen, SoDienThoai, ChucVu)
VALUES ('Nguyễn Đức Kim', '0900000001', 'Nhân viên demo');
SET @demo_nv = LAST_INSERT_ID();
INSERT INTO NHANVIEN (HoTen, SoDienThoai, ChucVu) VALUES
    ('Hoàng Hải Minh', '0900000003', 'Nhân viên demo'),
    ('Hoàng Bình Quân', '0900000004', 'Nhân viên demo');
-- Số điện thoại chỉ là dữ liệu mẫu, không phải số liên hệ của thành viên nhóm.

INSERT INTO KHACHHANG (HoTen, SoDienThoai, DiaChi)
VALUES ('[DEMO] Trần Minh', '0900000002', 'Hà Nội');
SET @demo_kh = LAST_INSERT_ID();

INSERT INTO SANBONG (TenSan, MaLoai, GiaThue, TinhTrang)
VALUES ('[DEMO] Sân A', @demo_loai_5, 300000.00, 'HOAT_DONG');
SET @demo_san_a = LAST_INSERT_ID();
INSERT INTO SANBONG (TenSan, MaLoai, GiaThue, TinhTrang)
VALUES ('[DEMO] Sân B', @demo_loai_7, 450000.00, 'HOAT_DONG');
INSERT INTO SANBONG (TenSan, MaLoai, GiaThue, TinhTrang)
VALUES ('[DEMO] Sân bảo trì', @demo_loai_5, 300000.00, 'BAO_TRI');

INSERT INTO DICHVU (TenDV, DonViTinh, DonGia)
VALUES ('[DEMO] Nước suối', 'Chai', 10000.00);
SET @demo_nuoc = LAST_INSERT_ID();
INSERT INTO DICHVU (TenDV, DonViTinh, DonGia)
VALUES ('[DEMO] Thuê giày', 'Đôi', 30000.00);

-- Mẫu 90 phút có 2 chai nước: đối chiếu 470.000; CHƯA có hóa đơn.
-- Lịch mẫu cố định, có thể chọn ngày khác khi demo luồng đặt mới.
INSERT INTO DAT_SAN
    (MaKH, MaSan, MaNV, ThoiGianBatDau, ThoiGianKetThuc, GiaThueLucDat, TrangThai)
VALUES (@demo_kh, @demo_san_a, @demo_nv,
    '2026-11-01 18:00:00', '2026-11-01 19:30:00', 300000.00, 'DA_DAT');
SET @demo_dat_san = LAST_INSERT_ID();
INSERT INTO CHITIET_DICHVU (MaDatSan, MaDV, SoLuong, DonGiaLucDung)
VALUES (@demo_dat_san, @demo_nuoc, 2, 10000.00);
COMMIT;

SELECT @demo_kh AS MaKH, @demo_nv AS MaNV, @demo_san_a AS MaSan,
       @demo_dat_san AS MaDatSan, @demo_nuoc AS MaDV;
-- Bốn bảng giải đấu vẫn có trong schema; không tự nhận có dữ liệu giải đấu.
