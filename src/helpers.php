<?php
declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    static $settings = null;
    if ($settings === null) {
        $settings = require __DIR__ . '/../config/app.php';
        if (!is_array($settings)) {
            throw new RuntimeException('Cấu hình ứng dụng không hợp lệ.');
        }
    }
    $value = $settings;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function e(mixed $value): string
{
    if ($value !== null && !is_scalar($value) && !($value instanceof Stringable)) {
        throw new InvalidArgumentException('Giá trị hiển thị không hợp lệ.');
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = (string) config('base_path', '');
    if ($base !== '' && !preg_match('~^(?:/[A-Za-z0-9_-]+)+$~D', $base)) {
        throw new RuntimeException('base_path phải là đường dẫn nội bộ không có dấu / cuối.');
    }
    // Reject external URLs, backslashes, control/header characters and traversal.
    $decoded = rawurldecode($path);
    if ($path !== '' && ($path[0] !== '/' || str_starts_with($decoded, '//')
        || preg_match('~[\\\\\x00-\x20\x7f]~', $decoded)
        || preg_match('~(?:^|/)\.\.(?:/|$|[?#])~', $decoded))) {
        throw new InvalidArgumentException('Chỉ cho phép đường dẫn nội bộ.');
    }
    return $base . ($path === '' ? '/' : $path);
}

function redirect(string $path, int $status = 303): void
{
    if (!in_array($status, [301, 302, 303, 307, 308], true)) {
        throw new InvalidArgumentException('Mã redirect không hợp lệ.');
    }
    header('Location: ' . url($path), true, $status);
    exit;
}

// Shared internal guard; bootstrap starts HTTP sessions before output.
function ensure_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (headers_sent()) {
        throw new RuntimeException('Session phải bắt đầu trước khi gửi nội dung.');
    }
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
    $started = session_start([
        'use_strict_mode' => 1,
        'use_only_cookies' => 1,
        'cookie_httponly' => 1,
        'cookie_secure' => $secure,
        'cookie_samesite' => 'Lax',
        'cookie_path' => ((string) config('base_path', '')) . '/',
    ]);
    if (!$started) {
        throw new RuntimeException('Không thể bắt đầu session.');
    }
}

function set_flash(string $type, string $message): void
{
    if (!in_array($type, ['success', 'error', 'info'], true)) {
        throw new InvalidArgumentException('Loại thông báo không hợp lệ.');
    }
    ensure_app_session();
    $_SESSION['_flashes'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    ensure_app_session();
    $messages = $_SESSION['_flashes'] ?? [];
    unset($_SESSION['_flashes']);
    return is_array($messages) ? $messages : [];
}

function csrf_token(): string
{
    ensure_app_session();
    if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    ensure_app_session();
    $submitted = $_POST['_csrf'] ?? null;
    $known = $_SESSION['_csrf'] ?? null;
    if (!is_string($submitted) || !is_string($known) || $known === ''
        || !hash_equals($known, $submitted)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Phiên gửi biểu mẫu không hợp lệ. Hãy tải lại trang rồi thử lại.');
    }
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Thao tác này chỉ nhận POST.');
    }
}

function post_string(string $name, string $default = ''): string
{
    if (!array_key_exists($name, $_POST)) {
        return $default;
    }
    if (!is_string($_POST[$name])) {
        throw new InvalidArgumentException('Dữ liệu biểu mẫu phải là chuỗi đơn.');
    }
    return trim($_POST[$name]);
}
