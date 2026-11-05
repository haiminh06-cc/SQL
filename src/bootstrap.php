<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/database.php';

date_default_timezone_set((string) config('timezone', 'Asia/Ho_Chi_Minh'));
// Browser receives no PHP warnings, SQL or stack traces. Technical logs must be sanitized.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

if (PHP_SAPI !== 'cli') {
    ensure_app_session();
}
// Database connection remains lazy; pages catch db() exceptions and show safe messages.
