<?php
declare(strict_types=1);

// Run from a terminal with: php scripts/reset_admin.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/config.php';

function ask(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    return trim((string) fgets(STDIN));
}

$username = ask('New admin username: ');
if ($username === '' || strlen($username) > 100) {
    fwrite(STDERR, "Username must be between 1 and 100 characters.\n");
    exit(1);
}

$password = ask('New admin password (12+ characters): ');
if (strlen($password) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}

$role = $pdo->prepare("SELECT id FROM roles WHERE name = 'System Administrator' LIMIT 1");
$role->execute();
$roleId = $role->fetchColumn();
if (!$roleId) {
    fwrite(STDERR, "System Administrator role is missing. Import sql/database.sql first.\n");
    exit(1);
}

$admin = $pdo->prepare("SELECT id FROM users WHERE role_id = ? ORDER BY id LIMIT 1");
$admin->execute([$roleId]);
$adminId = $admin->fetchColumn();
$hash = password_hash($password, PASSWORD_DEFAULT);

if ($adminId) {
    $save = $pdo->prepare('UPDATE users SET username = ?, password_hash = ?, is_active = 1 WHERE id = ?');
    $save->execute([$username, $hash, $adminId]);
} else {
    $save = $pdo->prepare('INSERT INTO users (username, password_hash, role_id, is_active) VALUES (?, ?, ?, 1)');
    $save->execute([$username, $hash, $roleId]);
}

fwrite(STDOUT, "Admin credentials updated. Sign in with the new username and password.\n");
