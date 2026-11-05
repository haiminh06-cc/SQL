<?php
declare(strict_types=1);

function connect_database(array $settings): PDO
{
    foreach (['host', 'port', 'database', 'username', 'password', 'charset'] as $key) {
        if (!array_key_exists($key, $settings)) {
            throw new RuntimeException('Cấu hình database còn thiếu trường bắt buộc.');
        }
    }
    foreach (['host', 'database', 'username', 'password', 'charset'] as $key) {
        if (!is_string($settings[$key])) {
            throw new RuntimeException('Kiểu cấu hình database không hợp lệ.');
        }
    }
    if (!preg_match('/^[A-Za-z0-9_.:\[\]-]+$/D', $settings['host'])
        || !preg_match('/^[A-Za-z0-9_]+$/D', $settings['database'])
        || $settings['charset'] !== 'utf8mb4'
        || trim($settings['username']) === '') {
        throw new RuntimeException('Hãy cấu hình host, database, tài khoản và charset utf8mb4 hợp lệ.');
    }
    $port = filter_var($settings['port'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($port === false) {
        throw new RuntimeException('Cổng database không hợp lệ.');
    }
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('PHP chưa có extension pdo_mysql.');
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $settings['host'], $port, $settings['database']);
    try {
        $pdo = new PDO($dsn, $settings['username'], $settings['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        // DATETIME in the demo is local wall time. Offset avoids MySQL tz-table dependency.
        $pdo->exec("SET time_zone = '+07:00'");
        return $pdo;
    } catch (PDOException $exception) {
        // Do not attach the original exception: DSN/user/server details are private.
        throw new RuntimeException('Không thể kết nối MySQL. Hãy kiểm tra cấu hình local và dịch vụ demo.');
    }
}

function db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }
    $path = __DIR__ . '/../config/database.local.php';
    if (!is_file($path)) {
        throw new RuntimeException('Thiếu config/database.local.php; hãy sao chép cấu hình mẫu và nhập tài khoản demo.');
    }
    $settings = require $path;
    if (!is_array($settings)) {
        throw new RuntimeException('Cấu hình database.local.php phải trả về array.');
    }
    $connection = connect_database($settings);
    return $connection;
}
