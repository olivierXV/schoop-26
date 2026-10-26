<?php
declare(strict_types=1);

define('APP_ROOT', __DIR__);

function config(?string $section = null): array {
    static $cfg = null;
    if ($cfg === null) {
        $file = APP_ROOT . '/config.local.php';
        if (is_file($file)) {
            $cfg = require $file;
        } elseif (getenv('SCHOOP_DB_USER') !== false) {
            $cfg = require APP_ROOT . '/config.sample.php';
            $cfg['db'] = [
                'host' => getenv('SCHOOP_DB_HOST') ?: '127.0.0.1', 'port' => (int)(getenv('SCHOOP_DB_PORT') ?: 3306),
                'user' => getenv('SCHOOP_DB_USER'), 'pass' => getenv('SCHOOP_DB_PASS') ?: '',
                'name' => getenv('SCHOOP_DB_NAME') ?: 'schoop',
            ];
        } else {
            http_response_code(500);
            error_log('SCHOOP: app/config.local.php is missing. Copy config.sample.php and fill it in.');
            exit('Server is not configured.');
        }
        $cfg = array_replace_recursive(require APP_ROOT . '/config.sample.php', $cfg);
    }
    return $section === null ? $cfg : $cfg[$section];
}

// Never show PHP errors to visitors; log them instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Manila');

require APP_ROOT . '/db.php';
require APP_ROOT . '/helpers.php';
require APP_ROOT . '/auth.php';
require APP_ROOT . '/layout.php';
require APP_ROOT . '/grades.php';

set_exception_handler(function (Throwable $e) {
    error_log('SCHOOP error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) http_response_code(500);
    if (defined('IS_API')) { header('Content-Type: application/json'); exit('{"ok":false,"error":"Server error."}'); }
    exit('Something went wrong on our side. Please try again later.');
});

if (!defined('CLI_TOOL')) { send_security_headers(); start_session(); }
