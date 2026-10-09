<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['System Administrator','Regional Training Administrator','Office/PENRO/CENRO Administrator','Training Coordinator']);
$trainings=$pdo->query("SELECT t.*,c.name category_name,(SELECT COUNT(*) FROM training_registrations r WHERE r.training_id=t.id AND r.status<>'Cancelled') participants FROM trainings t LEFT JOIN training_categories c ON c.id=t.category_id ORDER BY t.start_at DESC")->fetchAll();
$pendingCancellationCount=(int)$pdo->query("SELECT COUNT(*) FROM training_cancellation_requests WHERE status='Pending'")->fetchColumn();
$canManageCompletion=in_array(current_user()['role_name'],['System Administrator','Regional Training Administrator','Training Coordinator'],true);
$page_title='Training Programs'; require __DIR__ . '/../includes/header.php';
?>
<h1>Training Programs</h1>
<p><a class="btn" href="training_form.php">+ Create Training</a> <a class="btn secondary" href="training_cancellations.php">Review Cancellation Requests <span class="request-count"><?=$pendingCancellationCount?></span></a></p>
<div class="table-wrap"><table class="table"><thead><tr><th>Training</th><th>Category</th><th>Date</th><th>Participants</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach($trainings as $t): ?><tr><td><?=htmlspecialchars($t['title'])?></td><td><?=htmlspecialchars($t['category_name']??'-')?></td><td><?=htmlspecialchars($t['start_at'])?></td><td><?=$t['participants']?> / <?=$t['max_participants']?></td><td><?=htmlspecialchars($t['status'])?></td><td><div class="actions"><a class="btn secondary" href="training_form.php?id=<?=$t['id']?>">Manage</a><?php if($canManageCompletion):?><a class="btn" href="attendance.php?training_id=<?=$t['id']?>">Attendance / Completion</a><?php endif;?></div></td></tr><?php endforeach;?>
</tbody></table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
