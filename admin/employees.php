<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['System Administrator','Regional Training Administrator','Office/PENRO/CENRO Administrator']);
$page_title='Employees';
$employees=$pdo->query("SELECT e.*,o.name office_name,p.name position_name,u.username account_username,u.is_active account_active FROM employees e LEFT JOIN offices o ON o.id=e.office_id LEFT JOIN positions p ON p.id=e.position_id LEFT JOIN users u ON u.employee_id=e.id ORDER BY e.last_name,e.first_name")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h1>Employee Management</h1>
<p><a class="btn" href="employee_form.php">+ Add Employee</a></p>
<div class="panel"><input id="tableSearch" placeholder="Search employee..." style="padding:9px;width:300px"></div><br>
<div class="table-wrap"><table class="table"><thead><tr><th>Employee ID</th><th>Name</th><th>Position</th><th>Office</th><th>Employment</th><th>Portal Account</th><th>Action</th></tr></thead><tbody>
<?php foreach($employees as $e): ?><tr>
<td><?=htmlspecialchars($e['employee_no'])?></td><td><?=htmlspecialchars($e['last_name'].', '.$e['first_name'])?></td><td><?=htmlspecialchars($e['position_name']??'-')?></td><td><?=htmlspecialchars($e['office_name']??'-')?></td><td><?=htmlspecialchars($e['employment_status'])?></td><td><?=htmlspecialchars($e['account_username']??'No account')?><?=isset($e['account_active'])?' · '.($e['account_active']?'Active':'Inactive'):''?></td>
<td><div class="actions"><a class="btn secondary" href="employee_form.php?id=<?=$e['id']?>">View/Edit</a><?php if($e['account_username']):?><a class="btn warning" href="employee_account.php?employee_id=<?=$e['id']?>">Reset Login</a><?php endif;?></div></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
