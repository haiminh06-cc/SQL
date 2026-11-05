<?php
declare(strict_types=1);

function frontend_json(array $payload, int $status = 200): never
{
    $body = json_encode($payload,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo $body;
    exit;
}

try {
    require_once __DIR__ . '/../src/frontend.php';
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($method !== 'GET' && $method !== 'POST') {
        header('Allow: GET, POST');
        frontend_json(['ok'=>false,'message'=>'API chỉ nhận GET hoặc POST.'],405);
    }
    if ($method === 'GET') {
        $data = frontend_data(db());
        $data['csrf'] = csrf_token();
        frontend_json(['ok'=>true,'data'=>$data]);
    }
    // API guard deliberately returns JSON instead of the legacy text/redirect guards.
    ensure_app_session();
    $submitted = $_POST['_csrf'] ?? null; $known = $_SESSION['_csrf'] ?? null;
    if (!is_string($submitted) || !is_string($known) || $known === '' || !hash_equals($known,$submitted)) {
        frontend_json(['ok'=>false,'message'=>'Phiên gửi biểu mẫu không hợp lệ. Hãy tải lại trang.'],403);
    }
    frontend_json(frontend_operation(db(),$_POST));
} catch (InvalidArgumentException $error) {
    frontend_json(['ok'=>false,'message'=>$error->getMessage()],422);
} catch (Throwable $error) {
    // No SQL, DSN, bindings or credentials in client response/logs.
    if (function_exists('safe_log')) { safe_log($error); }
    frontend_json(['ok'=>false,'message'=>'Không thể tải hoặc lưu dữ liệu. Hãy kiểm tra kết nối và thử lại.'],503);
}
