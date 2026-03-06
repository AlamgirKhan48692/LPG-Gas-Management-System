<?php
mysqli_report(MYSQLI_REPORT_OFF);
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit;
}
include 'db_connect.php';

// ---------------- HELPERS ----------------
function respond_json($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
function respond_error($msg = 'error') {
    respond_json(['error' => true, 'message' => $msg]);
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

if ($api) {

switch($api){

// ---------------- SAVE PARCHOON ----------------
case 'save_parchoon':

    $date = $_POST['date'] ?? '';
    $rate = floatval($_POST['rate'] ?? 0);
    $kg = floatval($_POST['kg'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);

    if(!$date || $rate <= 0 || $kg <= 0){
        respond_error("Invalid data");
    }

    $q = $conn->prepare("INSERT INTO daily_parchoon (date, rate, kg, amount) VALUES (?, ?, ?, ?)");
    stmt_bind_params_by_array($q, "sddd", [$date, $rate, $kg, $amount]);

    if(!$q->execute()){
        respond_error("DB Error: " . $q->error);
    }

    respond_json(['success' => true]);
break;

// ---------------- LOAD PARCHOON ----------------
case 'load_parchoon':

    $date = $_GET['date'] ?? date('Y-m-d');

    $rows = [];
    $q = $conn->prepare("SELECT * FROM daily_parchoon WHERE date=? ORDER BY id DESC");
    stmt_bind_params_by_array($q, "s", [$date]);
    $q->execute();
    $res = $q->get_result();

    while($r = $res->fetch_assoc()){
        $rows[] = $r;
    }

    respond_json($rows);
break;

// ---------------- SUMMARY CARDS ----------------
case 'summary_cards':

    $date = $_GET['date'] ?? date('Y-m-d');

    // Parchoon
    $q = $conn->prepare("SELECT 
        COALESCE(SUM(amount),0) a,
        COALESCE(SUM(kg),0) k
      FROM daily_parchoon 
      WHERE date=?");
    stmt_bind_params_by_array($q,"s",[$date]);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();

    respond_json([
        'par_total' => floatval($r['a']),
        'par_kg' => floatval($r['k'])
    ]);
break;

default:
    respond_error('Unknown API');
}
exit;
}
?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Daily Parchoon</title>

<style>
:root{
  --primary:#1e88e5;
  --secondary:#0f63a3;
  --bg:#eef2f7;
  --card:#ffffff;
  --success:#00b09b;
  --info:#0072ff;
}

*{box-sizing:border-box}

body{
  font-family: Poppins, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  background:var(--bg);
  margin:0;
  padding:0;
}

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

/* CONTAINER */
.container{
  max-width:1200px;
  margin:20px auto;
  padding:20px;
  background:var(--card);
  border-radius:16px;
  box-shadow:0 10px 25px rgba(0,0,0,.08);
}

/* HEADER */
.page-title{
  font-size:24px;
  font-weight:800;
  color:#222;
  margin-bottom:10px;
}
.controls{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  margin-bottom:15px;
}

/* BUTTON */
.button{
  padding:9px 16px;
  border:none;
  border-radius:10px;
  background:linear-gradient(135deg,#1e88e5,#0d47a1);
  color:white;
  font-weight:700;
  cursor:pointer;
  box-shadow:0 4px 10px rgba(30,136,229,.3);
  transition:.2s;
}
.button:hover{transform:translateY(-1px);opacity:.95}

/* INPUT */
input,select{
  padding:9px 12px;
  border-radius:10px;
  border:1px solid #d0d7de;
  font-size:14px;
}
input:focus{
  outline:none;
  border-color:var(--primary);
  box-shadow:0 0 0 2px rgba(30,136,229,.15);
}

/* CARDS */
.grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
  gap:16px;
  margin-top:15px;
}
.card{
  padding:22px;
  border-radius:18px;
  color:white;
  font-weight:800;
  box-shadow:0 10px 25px rgba(0,0,0,.15);
}
.card .label{
  font-size:14px;
  opacity:.9;
}
.card .value{
  font-size:26px;
  margin-top:8px;
}
.blue{background:linear-gradient(135deg,#0072ff,#00c6ff);}
.green{background:linear-gradient(135deg,#00b09b,#96c93d);}

/* SECTION */
.section{
  margin-top:25px;
  padding:20px;
  border-radius:16px;
  background:#f8fbff;
  box-shadow:0 5px 15px rgba(0,0,0,.05);
}

/* FORM ROW */
.form-row{
  display:flex;
  gap:12px;
  flex-wrap:wrap;
  align-items:flex-end;
  margin-bottom:15px;
}

/* TABLE */
.table-wrap{margin-top:15px}
table{
  width:100%;
  border-collapse:collapse;
  background:white;
  border-radius:12px;
  overflow:hidden;
  box-shadow:0 5px 15px rgba(0,0,0,.08);
}
th{
  background:linear-gradient(135deg,#1e88e5,#0d47a1);
  color:white;
  text-align:left;
}
th,td{
  padding:12px;
  border-bottom:1px solid #e0e6ed;
}
tr:hover td{background:#f1f7ff}

/* TOTAL ROW */
tfoot td{
  font-weight:800;
  background:#f0f7ff;
}

/* MOBILE */
@media(max-width:600px){
  .page-title{font-size:20px}
  .card .value{font-size:22px}
}

.date-load-box{
  display:flex;
  align-items:center;
  gap:10px;
}

/* Date input wrapper */
.date-input-wrap{
  position:relative;
}

/* Date input */
.date-input-wrap input{
  padding:12px 44px 12px 16px;
  border-radius:16px;
  border:1px solid #d0d7de;
  font-size:16px;
  font-weight:600;
  background:white;
  min-width:200px;
  box-shadow:0 4px 12px rgba(0,0,0,.06);
  cursor:pointer;
}

/* Make calendar icon nice */
.date-input-wrap input::-webkit-calendar-picker-indicator{
  position:absolute;
  right:14px;
  top:50%;
  transform:translateY(-50%);
  cursor:pointer;
  opacity:.7;
  filter: invert(0.3);
}

/* Load button */
.load-btn{
  padding:12px 22px;
  border:none;
  border-radius:16px;
  font-size:16px;
  font-weight:700;
  background:linear-gradient(135deg,#1976d2,#0d47a1);
  color:white;
  cursor:pointer;
  box-shadow:0 6px 15px rgba(25,118,210,.35);
  transition:.2s;
}

.load-btn:hover{
  transform:translateY(-1px);
  opacity:.95;
}

/* HEADER BAR */
.header-bar{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:15px;
  flex-wrap:wrap;
  margin-bottom:15px;
}

/* On mobile stack nicely */
@media(max-width:600px){
  .header-bar{
    flex-direction:column;
    align-items:stretch;
  }
  .date-load-box{
    justify-content:flex-end;
  }
}

</style>
</head>

<body>

<div class="nav">
  <a href="insaf_home.php">Dashboard</a>
  <a href="quick_entry.php">Addition</a>
 
  <a href="sale_record.php">Sale Record</a>
  <a href="stock_records.php">Stock Record</a>
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

<div class="header-bar">
  <div class="page-title">🧺 Daily Parchoon</div>

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
    <div>Parchoon Today (Amount)</div>
    <div id="cardParchoon">Rs 0</div>
  </div>
  <div class="card green">
    <div>Parchoon Today (KG)</div>
    <div id="cardParchoonKg">0 KG</div>
  </div>
</div>

<!-- FORM -->
<div class="section">
  <input id="p_rate" placeholder="Rate" type="number">
  <input id="p_kg" placeholder="KG" type="number">
  <input id="p_total" placeholder="Total" type="number">
  <button class="button" id="p_save">Save</button>
  
<div class="table-wrap">
  <table id="parchoonTable">
    <thead><tr><th>Rate</th><th>KG</th><th>Amount</th></tr></thead>
    <tbody></tbody>
    <tfoot>
      <tr><td>Total</td><td id="parchoonKgTotal"></td><td id="parchoonTotal"></td></tr>
    </tfoot>
  </table>
</div>
</div>
</div>

<script>
const $=id=>document.getElementById(id);
function fmtMoney(v){return 'Rs '+Number(v||0).toLocaleString();}
function fetchJson(u,o={}){return fetch(u,o).then(r=>r.json());}

let lastEditedP=null;

['p_rate','p_kg','p_total'].forEach(id=>{
  $(id).addEventListener('input',()=>{lastEditedP=id; recalc();});
});

function recalc(){
  const r=+p_rate.value||0, k=+p_kg.value||0, t=+p_total.value||0;
  if(r<=0) return;
  if(lastEditedP==='p_rate'||lastEditedP==='p_kg') p_total.value=(r*k).toFixed(2);
  if(lastEditedP==='p_total') p_kg.value=(t/r).toFixed(3);
}

p_save.onclick=async()=>{
  const fd=new FormData();
  fd.append('api','save_parchoon');
  fd.append('date',theDate.value);
  fd.append('rate',p_rate.value);
  fd.append('kg',p_kg.value);
  fd.append('amount',p_total.value);
  const r=await fetchJson('daily_parchoon.php',{method:'POST',body:fd});
  if(r.success){ p_rate.value=''; p_kg.value=''; p_total.value=''; load(); }
  else alert(r.message);
};

async function load(){
  const rows=await fetchJson(`daily_parchoon.php?api=load_parchoon&date=${theDate.value}`);
  const tb=document.querySelector('#parchoonTable tbody');
  tb.innerHTML='';
  let ta=0, tk=0;
  rows.forEach(r=>{
    tb.innerHTML+=`<tr><td>${r.rate}</td><td>${r.kg}</td><td>${r.amount}</td></tr>`;
    ta+=+r.amount; tk+=+r.kg;
  });
  parchoonTotal.innerText=fmtMoney(ta);
  parchoonKgTotal.innerText=tk;

  const s=await fetchJson(`daily_parchoon.php?api=summary_cards&date=${theDate.value}`);
  cardParchoon.innerText=fmtMoney(s.par_total);
  cardParchoonKg.innerText=s.par_kg+' KG';
}

btnLoad.onclick=load;
load();
</script>

</body>
</html>
