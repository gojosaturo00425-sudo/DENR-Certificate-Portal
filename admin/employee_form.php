<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['System Administrator','Regional Training Administrator','Office/PENRO/CENRO Administrator']);
$id=(int)($_GET['id']??0);
$employee=$id ? $pdo->prepare("SELECT * FROM employees WHERE id=?") : null;
if($employee){$employee->execute([$id]);$employee=$employee->fetch();}
if($_SERVER['REQUEST_METHOD']==='POST'){
    $data=[
      trim($_POST['employee_no']),trim($_POST['first_name']),trim($_POST['middle_name']??''),
      trim($_POST['last_name']),(int)$_POST['position_id'] ?: null,(int)$_POST['office_id'] ?: null,
      trim($_POST['penro_cenro']),trim($_POST['division_section']),$_POST['employment_status'],
      trim($_POST['email']),trim($_POST['phone'])
    ];
    if($id){
      $s=$pdo->prepare("UPDATE employees SET employee_no=?,first_name=?,middle_name=?,last_name=?,position_id=?,office_id=?,penro_cenro=?,division_section=?,employment_status=?,email=?,phone=? WHERE id=?");
      $s->execute(array_merge($data, [$id])); audit('UPDATE','employee',$id,'Employee profile updated');
    }else{
      $s=$pdo->prepare("INSERT INTO employees(employee_no,first_name,middle_name,last_name,position_id,office_id,penro_cenro,division_section,employment_status,email,phone) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
      $s->execute($data); $id=(int)$pdo->lastInsertId(); audit('CREATE','employee',$id,'Employee profile created');
    }
    header('Location: employees.php');exit;
}
$positions=$pdo->query("SELECT * FROM positions ORDER BY name")->fetchAll();
$offices=$pdo->query("SELECT * FROM offices ORDER BY name")->fetchAll();
$page_title='Employee Form'; require __DIR__ . '/../includes/header.php';
?>
<h1><?= $id?'Edit':'Add' ?> Employee</h1>
<form method="post" class="panel"><div class="form-grid">
<?php
$fields=[
['employee_no','Employee ID'],['first_name','First Name'],['middle_name','Middle Name'],['last_name','Last Name'],
['penro_cenro','PENRO/CENRO'],['division_section','Division/Section'],['email','Email'],['phone','Phone']
];
foreach($fields as [$name,$label]): ?><div class="field"><label><?=$label?></label><input name="<?=$name?>" value="<?=htmlspecialchars($employee[$name]??'')?>"></div><?php endforeach;?>
<div class="field"><label>Position</label><select name="position_id"><option value="">-- Select --</option><?php foreach($positions as $p):?><option value="<?=$p['id']?>" <?=($employee['position_id']??0)==$p['id']?'selected':''?>><?=htmlspecialchars($p['name'])?></option><?php endforeach;?></select></div>
<div class="field"><label>Office</label><select name="office_id"><option value="">-- Select --</option><?php foreach($offices as $o):?><option value="<?=$o['id']?>" <?=($employee['office_id']??0)==$o['id']?'selected':''?>><?=htmlspecialchars($o['name'])?></option><?php endforeach;?></select></div>
<div class="field"><label>Employment Status</label><select name="employment_status"><?php foreach(['Permanent','Contractual','Job Order','Casual','Other'] as $s):?><option <?=$s===($employee['employment_status']??'Permanent')?'selected':''?>><?=$s?></option><?php endforeach;?></select></div>
</div><br><button class="btn">Save Employee</button> <a class="btn secondary" href="employees.php">Cancel</a></form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
