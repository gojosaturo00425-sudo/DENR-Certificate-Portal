<?php
require_once __DIR__ . '/../includes/auth.php';
if (current_user()) { header('Location: ' . BASE_URL . '/index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT u.*,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.username=? AND u.is_active=1 LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        login_user($user);
        audit('LOGIN', 'user', (int)$user['id'], 'Successful login');
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
    $error = 'We could not sign you in. Check your username and password, then try again.';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#123b2a">
<title>Welcome · DENR XII Employee Certification Portal</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
</head>
<body class="welcome-page">
<main class="welcome-shell">
  <section class="welcome-story" aria-label="DENR XII portal introduction">
    <a class="welcome-brand" href="<?= BASE_URL ?>/auth/login.php"><img src="<?= BASE_URL ?>/assets/denr-logo.webp" alt="DENR seal"><span><strong>DENR XII</strong><small>Department of Environment and Natural Resources</small></span></a>
    <div class="welcome-copy"><span class="welcome-tag"><i></i> Welcome to your employee workspace </span><h1>EMPLOYEE CERTIFICATION<br><em>PORTAL</em></h1><p>Manage your training journey, keep track of certificate requests, and access your issued credentials in one secure place.</p><div class="welcome-benefits"><div><span class="welcome-check">✓</span><span><strong>Training updates</strong><small>Stay informed about available programs.</small></span></div><div><span class="welcome-check">✓</span><span><strong>Certificate tracking</strong><small>Follow every request from review to issue.</small></span></div><div><span class="welcome-check">✓</span><span><strong>Trusted verification</strong><small>Access certificates prepared for your account.</small></span></div></div></div>
    <div class="welcome-foot">PENRO Sarangani<span>·</span> Environment · Service · Integrity</div>
  </section>
  <section class="welcome-form-side"><div class="login-card"><div class="login-mobile-brand"><img src="<?= BASE_URL ?>/assets/denr-logo.webp" alt="DENR seal"><span><strong>DENR XII</strong><small>Employee Certification Portal</small></span></div><span class="login-eyebrow">SECURE PORTAL ACCESS</span><h2>Sign in to continue</h2><p class="login-intro">Use your employee portal account to access your dashboard.</p>
    <?php if ($error): ?><div class="notice error login-error" role="alert"><strong>Sign-in unsuccessful</strong><span><?= htmlspecialchars($error) ?></span></div><?php endif; ?>
    <form method="post" class="welcome-login-form"><div class="field"><label for="username">Username</label><input id="username" name="username" autocomplete="username" required autofocus placeholder="Enter your username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"></div><div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password"></div><button class="btn welcome-submit" type="submit">Sign in <span aria-hidden="true">→</span></button></form>
    <div class="login-register">New to the portal? <a href="<?= BASE_URL ?>/auth/register.php">Create an employee account</a></div><div class="login-security"><span aria-hidden="true">⌑</span> Your account information is protected.</div>
  </div><footer class="welcome-legal">© <?= date('Y') ?> DENR XII <span>·</span> Employee Certification Portal</footer></section>
</main>
</body>
</html>
