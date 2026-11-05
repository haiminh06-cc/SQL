# Quản lý sân bóng

Bài tập lớn môn Cơ sở dữ liệu. Ứng dụng dùng PHP, PDO và MySQL để quản lý sân, khách hàng, lịch đặt, dịch vụ và hóa đơn.

## Chạy ứng dụng

Cần PHP 8.1 trở lên có `PDO`, `pdo_mysql` và MySQL 8.0 trở lên. Không cần cài Composer hoặc npm.

1. Trong MySQL Workbench, chạy `database/schema.sql`, sau đó chạy `database/seed.sql` một lần để tạo dữ liệu mẫu. Hai file dùng database `QuanLySanBong_demo`; chỉ nhập vào database mới, không nhập chồng lên dữ liệu đang sử dụng.
2. Sao chép `config/database.example.php` thành `config/database.local.php`. Điền host, port, tên database, tài khoản và mật khẩu MySQL trên máy mình; giữ charset `utf8mb4`. File local đã được bỏ qua trong Git.
3. Mở terminal tại thư mục vừa clone và chạy:

```sh
php -S 127.0.0.1:8000 -t public
```

Mở http://127.0.0.1:8000. Giữ terminal chạy trong lúc sử dụng; nhấn Ctrl+C để tắt.

Ba file `index.html`, `script.js`, `style.css` ở thư mục gốc là giao diện tĩnh ban đầu. Bản kết nối MySQL nằm trong `public/`, cần chạy bằng PHP như trên.

## Chức năng

- Thêm khách hàng, sân bóng và dịch vụ.
- Đặt sân, kiểm tra lịch trùng và sân bảo trì.
- Thêm dịch vụ vào lượt đặt, nhận sân hoặc hủy lượt chưa có hóa đơn.
- Lập hóa đơn và xác nhận đã thu tiền.
- Tạo giải đấu, thêm đội kèm đăng ký giải và xem lịch thi đấu đã có.

Tiền sân được tính theo số phút đã đặt. Giá sân được lưu lúc đặt; giá dịch vụ được lưu ở lần sử dụng đầu tiên. Ví dụ 90 phút ở sân giá 300.000 đồng/giờ và 2 chai nước giá 10.000 đồng/chai có tổng 470.000 đồng.

Mỗi lượt có tối đa một hóa đơn. Sau khi lập hóa đơn, lượt đó không được thêm dịch vụ hoặc hủy. Doanh thu trên trang chủ cộng các hóa đơn đã thanh toán theo ngày lập hóa đơn.

Ứng dụng chưa có đăng nhập, phân quyền, tiền cọc hay thanh toán trực tuyến. Phần giải đấu chưa có form xếp lịch và cập nhật tỷ số. Đây là bản chạy cục bộ phục vụ bài tập, chưa phù hợp để mở công khai trên Internet.

## Thư mục

| Thư mục | Nội dung |
|---|---|
| `public/` | Giao diện, API và các trang form |
| `src/` | Kết nối database, kiểm tra đầu vào và xử lý nghiệp vụ |
| `config/` | Quy ước trạng thái và cấu hình kết nối mẫu |
| `database/` | Schema 12 bảng, dữ liệu mẫu và truy vấn SQL |
| `tests/` | Kiểm thử PHP, HTTP và xử lý đồng thời |

## Kiểm thử

Chỉ chạy test trên database thử nghiệm riêng. Các test `run.php`, `frontend.php` và `race.php` yêu cầu MySQL cục bộ tại cổng `33307`, database `QuanLySanBong_demo`; cấu hình mặc định `3306` dành cho chạy ứng dụng. Không bỏ điều kiện bảo vệ của test để chạy vào dữ liệu đang dùng.

Khi đã cấu hình đúng database thử nghiệm và bật PHP server:

```sh
php tests/run.php --http=http://127.0.0.1:8000
php tests/frontend.php --http=http://127.0.0.1:8000
php tests/race.php
```

Test tự tạo và dọn dữ liệu của lần chạy. Tránh nhập liệu cùng lúc.

`tests/db-down.php` là kiểm thử riêng khi mất kết nối database. Nếu cần chạy, tạo thư mục `.tools` trước, chuẩn bị session bằng `php tests/db-down.php http://127.0.0.1:8000 --prepare` khi database còn hoạt động. Sau đó chỉ dừng database thử nghiệm, giữ PHP server chạy và gọi lại lệnh không có `--prepare`. Script không tự dừng database. Không dùng cách này với database đang sử dụng.

Chi tiết SQL và cách nhập dữ liệu: [database/README.md](database/README.md).

Báo cáo học phần được nộp riêng, không kèm trong repo.
