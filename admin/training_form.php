<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['System Administrator','Regional Training Administrator','Training Coordinator']);
$id=(int)($_GET['id']??0);
if($_SERVER['REQUEST_METHOD']==='POST'){
  $previousTraining = null;
  if ($id) {
    $previous = $pdo->prepare('SELECT * FROM trainings WHERE id=?');
    $previous->execute([$id]);
    $previousTraining = $previous->fetch() ?: null;
  }
  $data=[trim($_POST['title']),trim($_POST['description']), (int)$_POST['category_id']?:null,trim($_POST['instructor']),
  $_POST['start_at'],$_POST['end_at'],trim($_POST['venue']),trim($_POST['meeting_link']),(int)$_POST['max_participants'],
  trim($_POST['completion_requirements']),$_POST['status']];
  if($id){$s=$pdo->prepare("UPDATE trainings SET title=?,description=?,category_id=?,instructor=?,start_at=?,end_at=?,venue=?,meeting_link=?,max_participants=?,completion_requirements=?,status=? WHERE id=?");$s->execute(array_merge($data, [$id]));audit('UPDATE','training',$id,'Training updated');}
  else{$s=$pdo->prepare("INSERT INTO trainings(title,description,category_id,instructor,start_at,end_at,venue,meeting_link,max_participants,completion_requirements,status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");$s->execute(array_merge($data, [current_user()['id']]));$id=(int)$pdo->lastInsertId();audit('CREATE','training',$id,'Training created');}
  $previousStatus = $previousTraining['status'] ?? null;
  if ($data[10] === 'Published' && $previousStatus !== 'Published' && strtotime($data[4]) > time()) {
    $recipients = $pdo->query("SELECT u.id user_id,u.employee_id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND r.name='Employee' AND u.employee_id IS NOT NULL")->fetchAll();
    $notify = $pdo->prepare("INSERT INTO notifications (employee_id,user_id,title,message,type) VALUES (?,?,?,?,'training_available')");
    $startLabel = date('M j, Y g:i A', strtotime($data[4]));
    foreach ($recipients as $recipient) {
      $notify->execute([(int)$recipient['employee_id'], (int)$recipient['user_id'], 'New training available', 'TRAINING_ID:' . $id . ' | ' . $data[0] . ' is now open for registration. Training starts ' . $startLabel . '.']);
    }
  } elseif ($previousStatus === 'Published' && $previousTraining) {
    $fields = [
      ['title', 0, 'training title'], ['description', 1, 'description'], ['category_id', 2, 'category'],
      ['instructor', 3, 'instructor'], ['start_at', 4, 'start date or time'], ['end_at', 5, 'end date or time'],
      ['venue', 6, 'venue'], ['meeting_link', 7, 'online meeting link'], ['max_participants', 8, 'participant limit'],
      ['completion_requirements', 9, 'completion requirements'], ['status', 10, 'status'],
    ];
    $changedFields = [];
    foreach ($fields as [$column, $index, $label]) {
      $oldValue = $previousTraining[$column] ?? null;
      $newValue = $data[$index] ?? null;
      if (in_array($column, ['start_at', 'end_at'], true)) {
        $oldValue = $oldValue === null ? null : substr(str_replace('T', ' ', (string)$oldValue), 0, 16);
        $newValue = $newValue === null ? null : substr(str_replace('T', ' ', (string)$newValue), 0, 16);
      } elseif ($oldValue !== null || $newValue !== null) {
        $oldValue = trim((string)$oldValue);
        $newValue = trim((string)$newValue);
      }
      if ($oldValue !== $newValue) $changedFields[] = $label;
    }
    if ($changedFields) {
      $recipients = $pdo->query("SELECT u.id user_id,u.employee_id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND r.name='Employee' AND u.employee_id IS NOT NULL")->fetchAll();
      $notify = $pdo->prepare("INSERT INTO notifications (employee_id,user_id,title,message,type) VALUES (?,?,?,?,'training_updated')");
      if ($data[10] === 'Cancelled') {
        $title = 'Training cancelled';
        $description = $data[0] . ' has been cancelled by the training administrator.';
      } elseif ($data[10] !== 'Published') {
        $title = 'Training status updated';
        $description = $data[0] . ' is now marked ' . $data[10] . '. Check My Training for the latest details.';
      } else {
        $title = 'Published training updated';
        $description = $data[0] . ' was updated (' . implode(', ', $changedFields) . '). Check My Training for the latest details.';
      }
      foreach ($recipients as $recipient) {
        $notify->execute([(int)$recipient['employee_id'], (int)$recipient['user_id'], $title, 'TRAINING_ID:' . $id . ' | ' . $description]);
      }
    }
  }
  header('Location: trainings.php');exit;
}
$t=null;if($id){$s=$pdo->prepare("SELECT * FROM trainings WHERE id=?");$s->execute([$id]);$t=$s->fetch();}
$cats=$pdo->query("SELECT * FROM training_categories ORDER BY name")->fetchAll();
$page_title='Training Form';require __DIR__.'/../includes/header.php';
?>
<h1><?= $id?'Edit':'Create' ?> Training</h1><form method="post" class="panel"><div class="form-grid">
<div class="field"><label>Training Title</label><input name="title" required value="<?=htmlspecialchars($t['title']??'')?>"></div>
<div class="field"><label>Category</label><select name="category_id"><?php foreach($cats as $c):?><option value="<?=$c['id']?>" <?=($t['category_id']??0)==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach;?></select></div>
<div class="field"><label>Instructor/Resource Person</label><input name="instructor" value="<?=htmlspecialchars($t['instructor']??'')?>"></div>
<div class="field"><label>Start</label><input type="datetime-local" name="start_at" required value="<?=isset($t['start_at'])?date('Y-m-d\TH:i',strtotime($t['start_at'])):''?>"></div>
<div class="field"><label>End</label><input type="datetime-local" name="end_at" required value="<?=isset($t['end_at'])?date('Y-m-d\TH:i',strtotime($t['end_at'])):''?>"></div>
<div class="field"><label>Maximum Participants</label><input type="number" name="max_participants" min="0" value="<?=htmlspecialchars($t['max_participants']??0)?>"></div>
<div class="field"><label>Venue</label><input name="venue" value="<?=htmlspecialchars($t['venue']??'')?>"></div>
<div class="field"><label>Online Meeting Link</label><input name="meeting_link" value="<?=htmlspecialchars($t['meeting_link']??'')?>"></div>
<div class="field"><label>Status</label><select name="status"><?php foreach(['Draft','Published','Completed','Cancelled'] as $s):?><option <?=$s===($t['status']??'Draft')?'selected':''?>><?=$s?></option><?php endforeach;?></select></div>
</div><br><div class="field"><label>Description</label><textarea name="description"><?=htmlspecialchars($t['description']??'')?></textarea></div><div class="field"><label>Completion Requirements</label><textarea name="completion_requirements"><?=htmlspecialchars($t['completion_requirements']??'')?></textarea></div><br><button class="btn">Save Training</button></form>
<?php require __DIR__.'/../includes/footer.php'; ?>
