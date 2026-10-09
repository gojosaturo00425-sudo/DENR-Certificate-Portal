<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_POST['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'] ?? '', (string)$_POST['csrf_token'])) {
    http_response_code(400);
    exit('Invalid notification request. Refresh the page and try again.');
}

$user = current_user();
if ($user['role_name'] === 'Employee' && !empty($user['employee_id'])) {
    $stmt = $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=? OR employee_id=?');
    $stmt->execute([(int)$user['id'], (int)$user['employee_id']]);
} else {
    $stmt = $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?');
    $stmt->execute([(int)$user['id']]);
}

$returnTo = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/index.php');
$returnPath = parse_url($returnTo, PHP_URL_PATH) ?: '';
if (!str_starts_with($returnPath, BASE_URL . '/')) {
    $returnTo = BASE_URL . '/index.php';
}
header('Location: ' . $returnTo);
exit;
