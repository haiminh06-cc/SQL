-- MIGRATION THỦ CÔNG cho bản schema gốc đã được nhập vào DB DEMO RIÊNG.
-- schema.sql mới ĐÃ có các ràng buộc này: KHÔNG chạy file này sau schema.sql.
-- Không tự áp dụng vào QuanLySanBong đang dùng; cần nhóm đồng ý + backup trước.
-- Chạy từng giai đoạn; KHÔNG chạy cả file bằng --force. DDL implicit COMMIT.
-- Không idempotent: chỉ chạy mỗi ALTER MỘT LẦN khi xác minh còn thiếu index.
USE QuanLySanBong_demo;

-- 1. Xác nhận đúng DB, engine và dữ liệu trùng. Nếu có bất kỳ dòng trùng, DỪNG.
SELECT DATABASE() AS DatabaseDangChon;
SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('DAT_SAN', 'HOADON');
SELECT MaDatSan, COUNT(*) AS SoHoaDon
FROM HOADON GROUP BY MaDatSan HAVING COUNT(*) > 1;
-- Không tự xóa/gộp hóa đơn. Nhóm xử lý nghiệp vụ các dòng trùng trước.

-- 2. Đọc toàn bộ index để xác nhận chưa có index tương đương (dù tên khác).
SHOW INDEX FROM HOADON;
SHOW INDEX FROM DAT_SAN;

-- 3. Chỉ chọn/chạy riêng ALTER còn thiếu. UNIQUE tự từ chối nếu dữ liệu trùng.
ALTER TABLE HOADON ADD UNIQUE KEY uq_hoadon_dat_san (MaDatSan);
ALTER TABLE DAT_SAN ADD INDEX idx_dat_san_lich
    (MaSan, ThoiGianBatDau, ThoiGianKetThuc);
-- Nếu rerun sẽ lỗi duplicate key name, không phải lý do DROP index hoặc dữ liệu.
-- Bước UNIQUE thành công nhưng bước index lỗi: kiểm tra lại, chỉ chạy bước thiếu.
