<?php
require_once __DIR__ . '/config.php';

function current_user(): ?array {
    $user = $_SESSION['user'] ?? null;

    // Sessions can outlive code changes or contain legacy data. Treat an
    // invalid session value as signed out instead of throwing a TypeError.
    if (!is_array($user)) {
        unset($_SESSION['user']);
        return null;
    }

    foreach (['id', 'username', 'role_id', 'role_name', 'employee_id'] as $key) {
        if (!array_key_exists($key, $user)) {
            unset($_SESSION['user']);
            return null;
        }
    }

    return $user;
}

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'role_id' => $user['role_id'],
        'role_name' => $user['role_name'],
        'employee_id' => $user['employee_id'],
    ];
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login(): void {
    if (!current_user()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function require_roles(array $roles): void {
    require_login();
    if (!in_array(current_user()['role_name'], $roles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function audit(string $action, string $entityType = '', ?int $entityId = null, string $details = ''): void {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO audit_logs(user_id, action, entity_type, entity_id, details, ip_address) VALUES(?,?,?,?,?,?)");
    $stmt->execute([
        current_user()['id'] ?? null, $action, $entityType, $entityId, $details,
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
}
