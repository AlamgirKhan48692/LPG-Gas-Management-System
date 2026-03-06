<?php
mysqli_report(MYSQLI_REPORT_OFF);
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit;
}
include 'db_connect.php';

function respond_json($data){
  header('Content-Type: application/json');
  echo json_encode($data);
  exit;
}

function stmt_bind_params_by_array($stmt, $types, $params = []) {
    $bind_names = [];
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

$api = $_REQUEST['api'] ?? null;

if($api){

switch($api){

// ---------------- SAVE ----------------
case 'save_guaranty':

    $q = $conn->prepare("
      INSERT INTO guaranty_records
      (date, customer_name, cylinder_type, quantity, guaranty_type, cash_amount, guarantor_name, return_status, created_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");

    stmt_bind_params_by_array($q,"sssisss",[
      $_POST['date'],
      $_POST['name'],
      $_POST['ctype'],
      $_POST['qty'],
      $_POST['gtype'],
      $_POST['cash'],
      $_POST['guarantor']
    ]);

    if(!$q->execute()){
      respond_json(['error'=>true,'message'=>$q->error]);
    }

    respond_json(['success'=>true]);
break;


case 'load_guaranty':

    $q = $conn->query("
        SELECT * 
        FROM guaranty_records 
        ORDER BY id DESC
    ");

    $rows = [];
    while($r = $q->fetch_assoc()){
        $rows[] = $r;
    }

    respond_json($rows);
break;




// ---------------- MARK RETURNED ----------------
case 'mark_returned':

    if(($_SESSION['role'] ?? '') !== 'md'){
        respond_json(['error'=>true,'message'=>'No permission']);
    }

    $q = $conn->prepare("
      UPDATE guaranty_records
      SET return_status='returned', return_date=NOW()
      WHERE id=?
    ");
    stmt_bind_params_by_array($q,"i",[$_POST['id']]);
    $q->execute();

    respond_json(['success'=>true]);
break;


// ---------------- SUMMARY ----------------
case 'summary_cards':

    $q = $conn->query("
      SELECT 
        COALESCE(SUM(quantity),0) q,
        COALESCE(SUM(cash_amount),0) c
      FROM guaranty_records
      WHERE return_status = 'pending'
    ");

    $r = $q->fetch_assoc();

    respond_json([
      'pending_qty' => floatval($r['q']),
      'pending_cash' => floatval($r['c'])
    ]);
break;



default:
  respond_json(['error'=>true,'message'=>'Unknown API']);
}

exit;
}
?>


<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Daily Guaranty</title>

<!-- 🔥 USE SAME CSS AS PARCHOON -->
<style>
<?php include 'parchoon_style.css'; ?>

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
</style>
<link rel="stylesheet" href="app.css">

</head>
<body>

<div class="nav">
  <a href="insaf_home.php">Dashboard</a>
  <a href="quick_entry.php">Addition</a>
  <a href="daily_parchoon.php">Parchoon</a>
  <a href="sale_record.php">Sale Record</a>
  <a href="stock_records.php">Stock Record</a>
  <a href="expenditure.php">Expenditure</a>
  
  <a href="daily_closing.php">Daily Sumary</a>
  <?php if($_SESSION['role']==='md'): ?><a href="cash_in_hand.php">Cash in Hand</a><?php endif; ?>
  <?php if($_SESSION['role']==='md'): ?><a href="profit.php">Profit</a><?php endif; ?>
  <?php if($_SESSION['role']==='md'): ?><a href="assets.php">Assets</a><?php endif; ?>
  
  <a href="image_notes.php">Image Notes</a>
  <a href="checklist.php">Check List</a>
  
</div>

<div class="container">

<!-- HEADER -->
<div class="header-bar">
  <div class="page-title">📦 Daily Guaranty</div>

  <div class="date-load-box">
    <div class="date-input-wrap">
      <input type="date" id="theDate" value="<?=date('Y-m-d')?>">
    </div>
    <button class="load-btn" id="btnLoad">Load</button>
  </div>
</div>

<!-- CARDS -->
<div class="grid">
  <div class="card blue">
    <div class="label">Pending Qty</div>
    <div class="value" id="cardPendingQty">0</div>
  </div>

  <div class="card green">
    <div class="label">Pending Cash</div>
    <div class="value" id="cardPendingCash">Rs 0</div>
  </div>
</div>

<!-- FORM -->
<div class="section">

<div class="form-row">
  <input id="g_name" placeholder="Customer name">
  <select id="g_ctype">
    <option value="15KG">15KG</option>
    <option value="45KG">45KG</option>
  </select>
  <input id="g_qty" type="number" min="1" value="1" placeholder="Qty">
  <select id="g_gtype">
    <option value="cash">Cash</option>
    <option value="person">Person</option>
  </select>
  <input id="g_cash" type="number" placeholder="Cash">
  <input id="g_guaran" placeholder="Guarantor">
  <button class="button" id="g_save">Save</button>
</div>
<button class="button" id="toggleTable">Hide Records</button>

<div class="table-wrap" id="tableWrap">

<table id="guarantyTable">
<thead>
<tr>
  <th>Date</th>
  <th>Name</th>
  <th>Type</th>
  <th>Qty</th>
  <th>G-Type</th>
  <th>Cash</th>
  <th>Guarantor</th>
  <th>Action</th>
</tr>
</thead>
<tbody></tbody>
</table>
</div>

</div>

</div>

<script>
const IS_MD = <?= (($_SESSION['role'] ?? '') === 'md') ? 'true' : 'false' ?>;
const $=id=>document.getElementById(id);
function fmt(v){return 'Rs '+Number(v||0).toLocaleString();}
function fetchJson(u,o={}){return fetch(u,o).then(r=>r.json());}

g_save.onclick=async()=>{
  if(!g_name.value.trim()) return alert('Enter name');
  const fd=new FormData();
  fd.append('api','save_guaranty');
  fd.append('date',theDate.value);
  fd.append('name',g_name.value);
  fd.append('ctype',g_ctype.value);
  fd.append('qty',g_qty.value);
  fd.append('gtype',g_gtype.value);
  fd.append('cash',g_cash.value);
  fd.append('guarantor',g_guaran.value);
  const r=await fetchJson('daily_guaranty.php',{method:'POST',body:fd});
  if(r.success){
    g_name.value=''; g_cash.value=''; g_guaran.value=''; g_qty.value=1;
    load();
  }
};

async function load(){
  const date = document.getElementById('theDate').value;

  const rows = await fetchJson(`daily_guaranty.php?api=load_guaranty&date=${date}`);

  const tb = document.querySelector('#guarantyTable tbody');
  tb.innerHTML = '';

  rows.forEach(r=>{
    const pending = (r.return_status === 'pending');

    const act = (IS_MD && pending)
      ? `<button onclick="markReturned(${r.id})">Returned</button>`
      : '';

    const tr = document.createElement('tr');
    tr.innerHTML = `
  <td>${r.date}</td>
  <td>${r.customer_name}</td>
  <td>${r.cylinder_type}</td>
  <td>${r.quantity}</td>
  <td>${r.guaranty_type}</td>
  <td>${Number(r.cash_amount).toLocaleString()}</td>
  <td>${r.guarantor_name || ''}</td>
  <td>${act}</td>
`;

    tb.appendChild(tr);
  });

  // Load summary
  const s = await fetchJson(`daily_guaranty.php?api=summary_cards&date=${date}`);

  document.getElementById('cardPendingQty').innerText = s.pending_qty;
  document.getElementById('cardPendingCash').innerText = 'Rs ' + Number(s.pending_cash).toLocaleString();
}


async function markReturned(id){
  if(!confirm('Mark returned?')) return;
  const fd=new FormData();
  fd.append('api','mark_returned');
  fd.append('id',id);
  await fetchJson('daily_guaranty.php',{method:'POST',body:fd});
  load();
}

btnLoad.onclick=load;
load();

let tableVisible = true;

toggleTable.onclick = function(){
  tableVisible = !tableVisible;

  const wrap = document.getElementById('tableWrap');

  if(tableVisible){
    wrap.style.display = '';
    toggleTable.innerText = 'Hide Records';
  } else {
    wrap.style.display = 'none';
    toggleTable.innerText = 'Show Records';
  }
};

</script>

</body>
</html>
