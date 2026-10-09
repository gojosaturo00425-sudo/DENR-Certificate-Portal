<?php
declare(strict_types=1);

session_start();

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'denr_xii_portal');
define('DB_USER', 'root');
define('DB_PASS', '');

define('BASE_URL', '/denr_xii_portal');

function open_database_connection(): PDO {
    return new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

try {
    $pdo = open_database_connection();
} catch (PDOException $exception) {
    error_log('DENR XII portal database connection failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('The portal database is unavailable. Start MySQL in the XAMPP Control Panel, then reload this page.');
}
