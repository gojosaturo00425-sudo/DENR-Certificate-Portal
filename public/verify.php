<?php
require_once __DIR__ . '/../includes/config.php';
$token = trim((string)($_GET['token'] ?? ''));
$number = trim((string)($_GET['certificate_no'] ?? ''));
$certificate = null;
if ($token !== '' || $number !== '') {
    $lookup = $pdo->prepare("SELECT c.*,CONCAT(e.first_name,' ',e.last_name) employee_name,COALESCE(c.certificate_type,t.title,'Training Certificate') training_title FROM certificates c JOIN employees e ON e.id=c.employee_id LEFT JOIN trainings t ON t.id=c.training_id WHERE " . ($token !== '' ? 'c.qr_token=?' : 'c.certificate_no=?') . ' LIMIT 1');
    $lookup->execute([$token !== '' ? $token : $number]);
    $certificate = $lookup->fetch();
}
$valid = $certificate && in_array($certificate['status'], ['ACTIVE','APPROVED','EXPIRING'], true) && (!$certificate['valid_until'] || $certificate['valid_until'] >= date('Y-m-d'));
$searched = $token !== '' || $number !== '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#123b2a">
<title>Verify Certificate - DENR XII</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
</head>
<body class="welcome-page">
<main class="welcome-shell verify-shell">
  <section class="welcome-story" aria-label="DENR XII certificate verification">
    <a class="welcome-brand" href="<?= BASE_URL ?>/auth/login.php"><img src="<?= BASE_URL ?>/assets/denr-logo.webp" alt="DENR seal"><span><strong>DENR XII</strong><small>Department of Environment and Natural Resources</small></span></a>
    <div class="welcome-copy"><span class="welcome-tag"><i></i> CERTIFICATE AUTHENTICITY CHECK</span><h1>TRUSTED<br><em>VERIFICATION</em></h1><p>Confirm the details and current status of a certificate issued through the DENR XII Employee Certification Portal.</p><div class="welcome-benefits"><div><span class="welcome-check">✓</span><span><strong>Official records</strong><small>Check against the portal's certificate records.</small></span></div><div><span class="welcome-check">✓</span><span><strong>Clear certificate details</strong><small>Review the holder, certificate type, and validity.</small></span></div><div><span class="welcome-check">✓</span><span><strong>Public verification</strong><small>No employee account is required.</small></span></div></div></div>
    <div class="welcome-foot">PENRO Sarangani <span>·</span> Environment · Service · Integrity</div>
  </section>
  <section class="welcome-form-side verify-form-side">
    <div class="login-card verify-card">
      <div class="login-mobile-brand"><img src="<?= BASE_URL ?>/assets/denr-logo.webp" alt="DENR seal"><span><strong>DENR XII</strong><small>Certificate Verification</small></span></div>
      <span class="login-eyebrow">PUBLIC VERIFICATION</span>
      <h2>Verify a certificate</h2>
      <p class="login-intro">Enter the certificate number shown on the issued document.</p>
      <form method="get" action="<?= BASE_URL ?>/public/verify.php" class="welcome-login-form verify-search-form">
        <div class="field"><label for="certificate_no">Certificate number</label><input id="certificate_no" name="certificate_no" value="<?= htmlspecialchars($number) ?>" placeholder="DENR12-2026-000184" autocomplete="off" required></div>
        <button class="btn welcome-submit" type="submit">Verify certificate <span aria-hidden="true">→</span></button>
      </form>
      <?php if ($certificate): ?>
      <div class="verify-result <?= $valid ? 'is-valid' : 'is-invalid' ?>" role="status">
        <div class="verify-result-heading"><span class="verify-result-icon"><?= $valid ? '✓' : '!' ?></span><div><strong><?= $valid ? 'VALID CERTIFICATE' : 'NOT VALID' ?></strong><small><?= $valid ? 'This certificate is recognized by the portal.' : 'This certificate is not currently valid.' ?></small></div></div>
        <dl class="verify-details">
          <div><dt>Certificate number</dt><dd><?= htmlspecialchars($certificate['certificate_no']) ?></dd></div>
          <div><dt>Employee</dt><dd><?= htmlspecialchars($certificate['employee_name']) ?></dd></div>
          <div><dt>Certificate type</dt><dd><?= htmlspecialchars($certificate['training_title']) ?></dd></div>
          <div><dt>Issued</dt><dd><?= htmlspecialchars($certificate['issued_date'] ?? '-') ?></dd></div>
          <div><dt>Valid until</dt><dd><?= htmlspecialchars($certificate['valid_until'] ?? '-') ?></dd></div>
          <div><dt>Record status</dt><dd><?= htmlspecialchars($certificate['status']) ?></dd></div>
        </dl>
      </div>
      <?php elseif ($searched): ?>
      <div class="notice error verify-not-found" role="alert"><strong>Certificate not found</strong><span>Check the certificate number and try again.</span></div>
      <?php endif; ?>
      <br><div class="verify-back"><a href="<?= BASE_URL ?>/auth/login.php">Back</a></div>
    </div>
    <footer class="welcome-legal">© <?= date('Y') ?> DENR XII <span>·</span> Employee Certification Portal</footer>
  </section>
</main>
</body>
</html>
