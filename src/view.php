<?php
declare(strict_types=1);
require_once __DIR__ . '/business.php';
function page_start(string $title): void
{
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="vi"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title><link rel="stylesheet" href="' . e(url('/assets/demo.css')) . '"><body><header><h1>Quản lý sân bóng — demo backend</h1><p>Các form đặt sân, sử dụng dịch vụ và lập hóa đơn. Giá thuê tính theo giờ, dữ liệu lưu trong MySQL.</p><nav>';
    foreach (['index.php'=>'Trang chủ','san.php'=>'Sân','khach-hang.php'=>'Khách hàng','dat-san.php'=>'Đặt sân','dich-vu.php'=>'Dịch vụ','hoa-don.php'=>'Hóa đơn'] as $path=>$label) {
        echo '<a href="' . e(url('/'.$path)) . '">' . e($label) . '</a> ';
    }
    echo '</nav></header><main><h2>' . e($title) . '</h2>';
    foreach (get_flashes() as $flash) { echo '<p class="flash ' . e($flash['type']) . '">' . e($flash['message']) . '</p>'; }
}
function page_end(): void { echo '</main><footer>Demo môn CSDL · Chưa có đăng nhập hoặc thanh toán online.</footer></body></html>'; }
function render_page(string $title, callable $content): void
{
    ob_start();
    page_start($title);
    try { $content(db()); }
    catch (InvalidArgumentException $error) { echo '<p class="error">' . e($error->getMessage()) . '</p>'; }
    catch (Throwable $error) { safe_log($error); http_response_code(503); echo '<p class="error">Không thể đọc dữ liệu MySQL. Hãy kiểm tra cấu hình demo rồi tải lại.</p>'; }
    page_end();
    ob_end_flush();
}
function form_start(string $action): void { echo '<form method="post" action="' . e(url('/actions/'.$action)) . '">' . csrf_field(); }
function input_field(string $name, string $label, string $type='text', string $attributes=''): void
{
    echo '<label>' . e($label) . '<input name="' . e($name) . '" type="' . e($type) . '" ' . $attributes . '></label>';
}
function select_field(string $name, string $label, array $rows, string $idKey, string $labelKey): void
{
    echo '<label>' . e($label) . '<select name="' . e($name) . '" required><option value="">— Chọn —</option>';
    foreach ($rows as $row) { echo '<option value="' . e($row[$idKey]) . '">#' . e($row[$idKey]) . ' · ' . e($row[$labelKey]) . '</option>'; }
    echo '</select></label>';
}
function form_end(string $button): void { echo '<button>' . e($button) . '</button></form>'; }
function data_table(array $rows, array $columns): void
{
    if (!$rows) { echo '<p>Chưa có dữ liệu.</p>'; return; }
    echo '<div class="scroll"><table><thead><tr>';
    foreach ($columns as $label) { echo '<th>' . e($label) . '</th>'; } echo '</tr></thead><tbody>';
    foreach ($rows as $row) { echo '<tr>'; foreach ($columns as $key=>$label) { echo '<td>' . e($row[$key] ?? '') . '</td>'; } echo '</tr>'; }
    echo '</tbody></table></div>';
}
function booking_options(PDO $pdo): array
{
    $rows = booking_rows($pdo);
    foreach ($rows as &$row) { $row['Nhan'] = $row['TenKhach'] . ' · ' . $row['TenSan'] . ' · ' . $row['ThoiGianBatDau'] . ' · ' . $row['TrangThai']; } unset($row);
    return $rows;
}
