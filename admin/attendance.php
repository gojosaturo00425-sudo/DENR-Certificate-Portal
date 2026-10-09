<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['System Administrator','Regional Training Administrator','Training Coordinator']);
$training_id=(int)($_GET['training_id']??0);
$trainingStmt=$pdo->prepare('SELECT title,completion_requirements FROM trainings WHERE id=?');
$trainingStmt->execute([$training_id]);
$training=$trainingStmt->fetch();
if(!$training){http_response_code(404);exit('Training not found.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
  $reg=(int)$_POST['registration_id'];$status=$_POST['attendance_status'];
  $registrationStatus=$_POST['registration_status']??null;
  $s=$pdo->prepare("INSERT INTO attendance(registration_id,attendance_status,checked_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE attendance_status=VALUES(attendance_status),checked_by=VALUES(checked_by),checked_at=NOW()");
  $s->execute([$reg,$status,current_user()['id']]);
  if(!in_array($registrationStatus,['Registered','Attended','Completed','Cancelled'],true)){
    $registrationStatus=$status==='Present'?'Attended':'Registered';
  }
  $pdo->prepare("UPDATE training_registrations SET status=? WHERE id=?")->execute([$registrationStatus,$reg]);
  audit('ATTENDANCE','registration',$reg,$status.'; registration '.$registrationStatus);
  header("Location: attendance.php?training_id=$training_id");exit;
}
$rows=[];$databaseError=false;
$loadRows=function() use (&$pdo,$training_id): array {
  $statement=$pdo->prepare("SELECT r.id,r.status,e.employee_no,CONCAT(e.first_name,' ',e.last_name) employee_name,a.attendance_status FROM training_registrations r JOIN employees e ON e.id=r.employee_id LEFT JOIN attendance a ON a.registration_id=r.id WHERE r.training_id=? AND r.status<>'Cancelled' ORDER BY e.last_name");
  $statement->execute([$training_id]);
  return $statement->fetchAll();
};
try {
  $rows=$loadRows();
} catch(PDOException $exception) {
  $driverCode=(int)($exception->errorInfo[1]??0);
  if(in_array($driverCode,[2006,2013],true)){
    try {
      $pdo=open_database_connection();
      $rows=$loadRows();
    } catch(PDOException $retryException) {
      $databaseError=true;
      http_response_code(503);
    }
  } else {
    throw $exception;
  }
}
$page_title='Attendance';require __DIR__.'/../includes/header.php';
?>
<h1>Attendance and Completion</h1><div class="panel"><h2><?=htmlspecialchars($training['title'])?></h2><?php if($training['completion_requirements']):?><p><strong>Completion requirements:</strong> <?=nl2br(htmlspecialchars($training['completion_requirements']))?></p><?php endif;?><p>Record attendance, then set the registration to <strong>Completed</strong> after confirming the requirements. Employees can request a certificate once their training is marked Completed.</p></div><br>
<?php if($databaseError): ?><div class="notice error" role="alert">The database connection was interrupted. Your records were not changed. Confirm MySQL is running in XAMPP, then reload this page.</div><?php endif; ?>
<div class="table-wrap"><table class="table"><tr><th>Employee</th><th>Registration Status</th><th>Attendance</th><th>Save</th></tr>
<?php foreach($rows as $r):?><tr><td><?=htmlspecialchars($r['employee_no'].' - '.$r['employee_name'])?></td><td><form method="post"><input type="hidden" name="registration_id" value="<?=$r['id']?>"><select name="registration_status"><?php foreach(['Registered','Attended','Completed','Cancelled'] as $registrationOption):?><option value="<?=$registrationOption?>" <?=$r['status']===$registrationOption?'selected':''?>><?=$registrationOption?></option><?php endforeach;?></select></td><td><select name="attendance_status"><option <?=($r['attendance_status']??'')==='Present'?'selected':''?>>Present</option><option <?=($r['attendance_status']??'')==='Partial'?'selected':''?>>Partial</option><option <?=($r['attendance_status']??'')==='Absent'?'selected':''?>>Absent</option></select></td><td><button class="btn">Save</button></form></td></tr><?php endforeach;?>
</table></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
