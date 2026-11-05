# Cơ sở dữ liệu

Ứng dụng dùng MySQL 8.0 trở lên, InnoDB và bộ mã `utf8mb4`. Tên database trong các file SQL là `QuanLySanBong_demo`.

## Tạo database

Trong MySQL Workbench, mở và chạy theo thứ tự:

1. `schema.sql`: tạo database và 12 bảng. Chỉ chạy khi database chưa có các bảng này.
2. `seed.sql`: thêm dữ liệu mẫu. Chạy một lần sau khi tạo bảng, trên database chưa có dữ liệu. Nếu có lỗi, chạy `ROLLBACK` và kiểm tra nguyên nhân trước khi tiếp tục.

Không nhập hai file này vào database đang sử dụng.

Dữ liệu mẫu gồm loại sân, nhân viên, khách hàng, sân bóng, dịch vụ và một lượt đặt sân. Các số điện thoại là dữ liệu minh họa. Lượt đặt mẫu kéo dài 90 phút, giá sân 300.000 đồng/giờ, dùng 2 chai nước giá 10.000 đồng/chai; tổng là 470.000 đồng và chưa có hóa đơn. Ngày đặt mẫu cố định trong SQL, có thể chọn ngày khác khi đặt mới trên ứng dụng.

Sau khi nhập dữ liệu, sao chép `config/database.example.php` thành `config/database.local.php` trong thư mục `config` của repo và điền thông tin kết nối của máy mình. Hướng dẫn bật PHP server nằm trong [README của ứng dụng](../README.md).

## Các bảng

- `KHACHHANG`, `NHANVIEN`: thông tin khách hàng và nhân viên.
- `LOAISAN`, `SANBONG`: loại sân, giá thuê và tình trạng sân.
- `DAT_SAN`: khách đặt, sân, nhân viên, thời gian sử dụng và giá thuê lúc đặt.
- `DICHVU`, `CHITIET_DICHVU`: danh mục dịch vụ, số lượng và giá lúc sử dụng.
- `HOADON`: hóa đơn của lượt đặt và trạng thái thanh toán.
- `DOIBONG`, `GIAIDAU`, `THAMGIA_GIAIDAU`, `TRANDAU`: đội bóng, giải đấu, đăng ký tham gia và lịch thi đấu.

`HOADON.MaDatSan` có ràng buộc `UNIQUE`, nên mỗi lượt đặt có tối đa một hóa đơn. Chỉ mục lịch sân giúp tra cứu; việc chặn lịch trùng được xử lý trong mã PHP bằng giao dịch và khóa dòng sân.

Schema lưu hai đội của trận đấu trong `TRANDAU.MaDoiNha` và `TRANDAU.MaDoiKhach`. Bốn bảng giải đấu chưa có dữ liệu mẫu trong `seed.sql`.

## Đối chiếu dữ liệu

`queries.sql` chứa các câu `SELECT` xem sân, khách hàng, lượt đặt, dịch vụ và hóa đơn. Trước khi chạy truy vấn chi tiết, thay giá trị `NULL` của `@MaDatSan` bằng mã lượt đặt thực tế lấy từ ứng dụng hoặc kết quả của `seed.sql`.

Tiền sân tính theo số phút đã đặt và `GiaThueLucDat`. Tiền dịch vụ tính từ `SoLuong` và `DonGiaLucDung`, sau đó cộng với tiền sân. Các giá đã lưu giúp lượt đặt cũ không đổi tiền khi giá danh mục thay đổi.

## Cập nhật schema cũ

`updates.sql` dành cho database thử nghiệm đã nhập schema cũ và còn thiếu ràng buộc một hóa đơn mỗi lượt hoặc chỉ mục lịch sân. Database mới tạo bằng `schema.sql` đã có cả hai, không chạy thêm file này.

Với database cũ, sao lưu trước, đọc các câu kiểm tra và chỉ chạy riêng lệnh `ALTER` còn thiếu. Nếu có hóa đơn trùng lượt đặt, phải xử lý dữ liệu trước khi thêm ràng buộc. Không chạy toàn bộ file bằng `--force`; các lệnh DDL của MySQL có thể tự commit.

## Môi trường kiểm thử

Cấu hình mẫu dùng cổng MySQL `3306`. Các test nghiệp vụ trong repo chỉ chấp nhận database `QuanLySanBong_demo` trên MySQL cục bộ cổng `33307` để tránh chạy nhầm vào dữ liệu đang dùng. Muốn chạy test, cần chuẩn bị MySQL thử nghiệm riêng, nhập schema và dữ liệu mẫu, rồi chỉnh cấu hình local cho đúng môi trường đó. Giữ nguyên các điều kiện bảo vệ trong test.

Báo cáo học phần được nộp riêng.
