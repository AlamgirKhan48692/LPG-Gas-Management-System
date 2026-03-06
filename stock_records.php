<?php

session_start();
if(!isset($_SESSION['username'])){header("Location: login.html");exit();}


include 'db_connect.php';

$filterMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$filterYear  = isset($_GET['year'])  ? (int)$_GET['year']  : date('Y');


$role=$_SESSION['role']??'';

/* ================= POST HANDLERS ================= */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])){
 header('Content-Type:application/json');

 /* DELETE */
 if($_POST['action']==='delete_stock' && $role==='md'){
   $id=intval($_POST['id']);
   $stmt=$conn->prepare("DELETE FROM stock_data WHERE id=?");
   $stmt->bind_param("i",$id);
   echo json_encode($stmt->execute()?['message'=>'Record deleted']:['error'=>'Delete failed']);
   $stmt->close();exit;
 }

 /* UPDATE */
 if($_POST['action']==='update_stock' && $role==='md'){
   $id=intval($_POST['id']);
   $date=$_POST['date']??'';
   $plantName=$_POST['plantName']??'';

   $kg12=intval($_POST['kg12']??0);
   $kg15=intval($_POST['kg15']??0);
   $kg45=intval($_POST['kg45']??0);
   $rate=floatval($_POST['rate']??0);

   $cost12=($rate/11.8)*12;
   $cost15=($rate/11.8)*15;
   $cost45=($rate/11.8)*45.4;

   $totalAmount=($cost12*$kg12)+($cost15*$kg15)+($cost45*$kg45);

   $stmt=$conn->prepare("
     UPDATE stock_data 
     SET date=?,plantName=?,kg12=?,kg15=?,kg45=?,rate=?,
         cost12=?,cost15=?,cost45=?,totalAmount=?
     WHERE id=?
   ");
   $stmt->bind_param(
     "ssiiiddddii",
     $date,$plantName,$kg12,$kg15,$kg45,$rate,
     $cost12,$cost15,$cost45,$totalAmount,$id
   );
   echo json_encode($stmt->execute()?['message'=>'Record updated']:['error'=>'Update failed']);
   $stmt->close();exit;
 }

 echo json_encode(['error'=>'unauthorized']);exit;
}

/* ================= FETCH DATA ================= */
$stmt = $conn->prepare("
 SELECT * FROM stock_data 
 WHERE MONTH(date)=? AND YEAR(date)=?
 ORDER BY date DESC, id DESC
");
$stmt->bind_param("ii", $filterMonth, $filterYear);
$stmt->execute();
$q = $stmt->get_result();


/* ================= CALCULATIONS ================= */
$sum_auto_12=$sum_auto_15=$sum_auto_45=0;
$sum_plant_12=$sum_plant_15=$sum_plant_45=0;

$stockQ=$conn->query("
 SELECT 
   SUM(kg12) s12,
   SUM(kg15) s15,
   SUM(kg45) s45 
 FROM stock_data
");

$s=$stockQ->fetch_assoc();
$remaining_12=$s['s12']; $remaining_15=$s['s15']; $remaining_45=$s['s45'];

$avgQ=$conn->query("
 SELECT
  SUM(CASE WHEN kg12>0 THEN kg12*cost12 ELSE 0 END) / NULLIF(SUM(CASE WHEN kg12>0 THEN kg12 ELSE 0 END),0) a12,
  SUM(CASE WHEN kg15>0 THEN kg15*cost15 ELSE 0 END) / NULLIF(SUM(CASE WHEN kg15>0 THEN kg15 ELSE 0 END),0) a15,
  SUM(CASE WHEN kg45>0 THEN kg45*cost45 ELSE 0 END) / NULLIF(SUM(CASE WHEN kg45>0 THEN kg45 ELSE 0 END),0) a45
 FROM stock_data
 WHERE plantName NOT LIKE '%AUTO_DEDUCT%'
");

$a=$avgQ->fetch_assoc();

$remaining_value =
($remaining_12 * $a['a12']) +
($remaining_15 * $a['a15']) +
($remaining_45 * $a['a45']);



$rq=$conn->query("SELECT * FROM stock_data");
while($r=$rq->fetch_assoc()){
 if(strpos($r['plantName'],'AUTO_DEDUCT')!==false){
  $sum_auto_12+=$r['kg12'];
  $sum_auto_15+=$r['kg15'];
  $sum_auto_45+=$r['kg45'];
 }else{
  $sum_plant_12+=$r['kg12'];
  $sum_plant_15+=$r['kg15'];
  $sum_plant_45+=$r['kg45'];
 }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Stock Records</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<style>
/* ---------- FULL CSS ---------- */
body{font-family:Poppins,system-ui;background:#eef2f7;margin:0}
.container{max-width:1200px;margin:20px auto;padding:20px;background:#fff;border-radius:12px}
h1{color:#1e88e5}
.rem-box{background:#1e88e5;color:#fff;padding:14px;border-radius:12px;display:flex;gap:16px;font-weight:800}
.stock-cards{display:flex;gap:14px;margin:16px 0}
.stock-card{flex:1;background:#f5f9ff;padding:16px;border-radius:12px;text-align:center;font-weight:700;background:lightgreen}
.stock-card-title{font-size:18px;color:#1e88e5;font-weight:900}
table{width:100%;border-collapse:collapse}
th{background:#1e88e5;color:#fff;padding:10px}
td{padding:8px;border-bottom:1px solid #ddd}
tr:hover{background:#f1f5ff}
button{padding:4px 8px;border:none;border-radius:6px;cursor:pointer}
.edit{background:#2979ff;color:#fff}
.del{background:#d32f2f;color:#fff}

.nav{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  background:#1e88e5;
  padding:10px 14px;
  justify-content:center;
}

.nav a{
  color:#fff;
  padding:5px 10px;
  text-decoration:none;
  background:#0f63a3;
  border-radius:6px;
  font-weight:600;
  font-size:13px;
  font-family: 'Merriweather', serif;
  letter-spacing: 0.3px;
}

.nav a:hover{
  background:#0b4f85;
  transform:translateY(-1px);
}

/* Logout button */
.nav a[style]{
  background:#d32f2f !important;
}
.nav a[style]:hover{
  background:#b71c1c !important;
}

.stock-bar{
background:linear-gradient(90deg,#0072ff,#00c6ff);
color:#fff;padding:16px;border-radius:12px;
font-weight:800;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px
}

.filter-bar{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  flex-wrap:wrap;
  margin:14px 0 18px 0;
}

.filter-left input{
  padding:10px 14px;
  width:240px;
  border-radius:8px;
  border:1px solid #cfd8dc;
  font-size:14px;
  outline:none;
  transition:.2s;
}

.filter-left input:focus{
  border-color:#1e88e5;
  box-shadow:0 0 0 2px rgba(30,136,229,.15);
}

.filter-right{
  display:flex;
  gap:10px;
  align-items:center;
}

.filter-right select{
  padding:10px 14px;
  border-radius:8px;
  border:1px solid #cfd8dc;
  font-weight:600;
  font-size:14px;
  background:#fff;
  cursor:pointer;
}

.filter-right button{
  padding:10px 18px;
  border-radius:8px;
  border:none;
  background:linear-gradient(135deg,#1e88e5,#1565c0);
  color:#fff;
  font-weight:700;
  cursor:pointer;
  transition:.2s;
}

.filter-right button:hover{
  transform:translateY(-1px);
  box-shadow:0 6px 16px rgba(30,136,229,.35);
}

@media(max-width:600px){
  .filter-bar{
    flex-direction:column;
    align-items:stretch;
  }
  .filter-left input{
    width:100%;
  }
  .filter-right{
    width:100%;
  }
  .filter-right select,
  .filter-right button{
    flex:1;
  }
}

</style>
</head>

<body>
<div class="nav">
  <a href="insaf_home.php">Dashboard</a>
  <a href="quick_entry.php">Addition</a>
 
  <a href="daily_parchoon.php">Parchoon</a>
  <a href="sale_record.php">Sale Record</a>
  
  <a href="expenditure.php">Expenditure</a>
  <a href="daily_guaranty.php">Guaranty</a>
  <a href="daily_closing.php">Daily Sumary</a>
  <?php if($_SESSION['role']==='md'): ?><a href="cash_in_hand.php">Cash in Hand</a><?php endif; ?>
  <?php if($_SESSION['role']==='md'): ?><a href="profit.php">Profit</a><?php endif; ?>
  <?php if($_SESSION['role']==='md'): ?><a href="assets.php">Assets</a><?php endif; ?>
  
  <a href="image_notes.php">Image Notes</a>
  <a href="checklist.php">Check List</a>
  
</div>

<div class="container">
<h1>Stock Management</h1>

<div class="stock-bar">
  <div>✔ Available Stock</div>

  <div class="mid">
    12KG ➤ <?= number_format($remaining_12) ?> |
    15KG ➤ <?= number_format($remaining_15) ?> |
    45KG ➤ <?= number_format($remaining_45) ?>
 </div>

  <div class="right">
    💰 Rs <?= number_format($remaining_value,2) ?>
  </div>
</div>

<div class="stock-cards">
 <div class="stock-card"><div class="stock-card-title">Plant</div>
 12KG <?= $sum_plant_12 ?> | 15KG <?= $sum_plant_15 ?> | 45KG <?= $sum_plant_45 ?>
 </div>
 <div class="stock-card"><div class="stock-card-title">Deduct</div>
 12KG <?= $sum_auto_12 ?> | 15KG <?= $sum_auto_15 ?> | 45KG <?= $sum_auto_45 ?>
 </div>
</div>

<div class="filter-bar">

  <!-- Search -->
  <div class="filter-left">
    <input id="search" placeholder="Search...">
  </div>

  <!-- Month / Year Filter -->
  <form method="GET" class="filter-right">

    <!-- Month -->
    <select name="month">
      <?php for($m=1;$m<=12;$m++): ?>
        <option value="<?=$m?>" <?=$m==$filterMonth?'selected':''?>>
          <?=date("F", mktime(0,0,0,$m,1))?>
        </option>
      <?php endfor; ?>
    </select>

    <!-- Year -->
    <select name="year">
      <?php for($y=date('Y')-5; $y<=date('Y')+1; $y++): ?>
        <option value="<?=$y?>" <?=$y==$filterYear?'selected':''?>>
          <?=$y?>
        </option>
      <?php endfor; ?>
    </select>

    <!-- Button -->
    <button type="submit">Load</button>

  </form>

</div>



<table id="stockTable">
<thead>
<tr>
<th>Date</th><th>Plant</th>
<th>12</th><th>15</th><th>45</th>
<th>Rate</th>
<th>12 Cost</th><th>15 Cost</th><th>45 Cost</th>
<th>Total</th><th>Action</th>
</tr>
</thead>
<tbody>
<?php while($r=$q->fetch_assoc()): ?>
<tr>
<td><?= $r['date'] ?></td>
<td><?= htmlspecialchars($r['plantName']) ?></td>
<td><?= $r['kg12'] ?></td>
<td><?= $r['kg15'] ?></td>
<td><?= $r['kg45'] ?></td>
<td><?= $r['rate'] ?></td>
<td><?= number_format($r['cost12'],2) ?></td>
<td><?= number_format($r['cost15'],2) ?></td>
<td><?= number_format($r['cost45'],2) ?></td>
<td><?= number_format($r['totalAmount'],0) ?></td>
<td>
<?php if($role==='md'): ?>
<?php $json=htmlspecialchars(json_encode($r),ENT_QUOTES); ?>
<button class="edit" onclick='openEditModal(<?= $json ?>)'>✏</button>
<button class="del" onclick='deleteStock(<?= $r['id'] ?>)'>🗑</button>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<!-- ================= EDIT MODAL ================= -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center">
<div style="background:#fff;padding:20px;border-radius:12px;width:360px">
<h3>Edit Stock</h3>
<input type="hidden" id="e_id">
<input type="date" id="e_date"><br><br>
<input placeholder="Plant" id="e_plant"><br><br>
<input type="number" id="e_kg12" placeholder="12KG"><br>
<input type="number" id="e_kg15" placeholder="15KG"><br>
<input type="number" id="e_kg45" placeholder="45KG"><br>
<input type="number" id="e_rate" placeholder="Rate"><br><br>
<input id="e_total" readonly><br><br>
<button onclick="saveEdit()">Save</button>
<button onclick="closeEdit()">Cancel</button>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
/* ================= FULL JS ================= */
const m=document.getElementById('editModal');
const e_id=e_date=e_plant=e_kg12=e_kg15=e_kg45=e_rate=e_total=null;

function openEditModal(r){
 document.getElementById('e_id').value=r.id;
 document.getElementById('e_date').value=r.date;
 document.getElementById('e_plant').value=r.plantName;
 document.getElementById('e_kg12').value=r.kg12;
 document.getElementById('e_kg15').value=r.kg15;
 document.getElementById('e_kg45').value=r.kg45;
 document.getElementById('e_rate').value=r.rate;
 calc();
 m.style.display='flex';
}

function closeEdit(){m.style.display='none';}

function calc(){
 let r=+document.getElementById('e_rate').value||0;
 let t=(r/11.8)*(
  12*+document.getElementById('e_kg12').value+
  15*+document.getElementById('e_kg15').value+
  45.4*+document.getElementById('e_kg45').value
 );
 document.getElementById('e_total').value=t.toFixed(2);
}

['e_rate','e_kg12','e_kg15','e_kg45'].forEach(id=>{
 document.getElementById(id).addEventListener('input',calc);
});

function saveEdit(){
 let f=new FormData();
 f.append('action','update_stock');
 f.append('id',e_id.value);
 f.append('date',e_date.value);
 f.append('plantName',e_plant.value);
 f.append('kg12',e_kg12.value);
 f.append('kg15',e_kg15.value);
 f.append('kg45',e_kg45.value);
 f.append('rate',e_rate.value);

 fetch('',{method:'POST',body:f})
 .then(r=>r.json())
 .then(j=>{alert(j.message||j.error);location.reload();});
}

function deleteStock(id){
 if(!confirm('Delete?'))return;
 let f=new FormData();
 f.append('action','delete_stock');
 f.append('id',id);
 fetch('',{method:'POST',body:f})
 .then(r=>r.json())
 .then(j=>{alert(j.message||j.error);location.reload();});
}

/* search */
document.getElementById('search').addEventListener('input',e=>{
 let v=e.target.value.toLowerCase();
 document.querySelectorAll('#stockTable tbody tr')
 .forEach(r=>r.style.display=r.innerText.toLowerCase().includes(v)?'':'none');
});
</script>

</body>
</html>
