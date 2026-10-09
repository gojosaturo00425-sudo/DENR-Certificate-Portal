<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Employee']);
if(!current_user()['employee_id']) exit('Account not linked to employee profile.');
$s=$pdo->prepare("SELECT c.*,COALESCE(c.certificate_type,t.title,'Training Certificate') training_title FROM certificates c LEFT JOIN trainings t ON t.id=c.training_id WHERE c.employee_id=? ORDER BY c.created_at DESC");
$s->execute([current_user()['employee_id']]);$certs=$s->fetchAll();
$page_title='My Certificates';require __DIR__.'/../includes/header.php';
?>
<h1>My Certificates</h1><div class="table-wrap"><table class="table"><tr><th>Certificate No.</th><th>Training / Certificate Type</th><th>Status</th><th>Valid Until</th><th>Certificate File</th><th>Verification</th></tr>
<?php foreach($certs as $c):?><tr><td><?=htmlspecialchars($c['certificate_no'])?></td><td><?=htmlspecialchars($c['training_title'])?></td><td><?=htmlspecialchars($c['status'])?></td><td><?=htmlspecialchars($c['valid_until']??'-')?></td><td><?php if($c['pdf_path']&&in_array($c['status'],['ACTIVE','APPROVED','EXPIRING','EXPIRED'],true)):?><div class="actions"><a class="btn secondary" target="_blank" rel="noopener" href="<?=BASE_URL?>/employee/certificate_file.php?id=<?=(int)$c['id']?>">View</a><a class="btn" href="<?=BASE_URL?>/employee/certificate_file.php?id=<?=(int)$c['id']?>&amp;download=1">Download</a></div><?php else:?>—<?php endif;?></td><td><a class="btn secondary" target="_blank" rel="noopener" href="<?=BASE_URL?>/public/verify.php?token=<?=urlencode($c['qr_token'])?>">Verify</a></td></tr><?php endforeach;?>
<?php if(!$certs):?><tr><td colspan="6">No certificates yet. Approved certificate requests will appear here.</td></tr><?php endif;?>
</table></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
