<?php
mysqli_report(MYSQLI_REPORT_OFF);
session_start();
if (!isset($_SESSION['username'])) { header("Location: login.html"); exit(); }

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['md', 'operator'])) {
    header("Location: login.html");
    exit();
}

include 'db_connect.php';

// -------------------------
// Helpers
// -------------------------
function respond_json($data){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function fetch_rows($conn, $sql, $types = "", $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    if ($types !== "") $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    return $rows;
}

function day_params($date){ return [$date, $date]; }

// -------------------------
// API
// -------------------------
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if ($action) {

    // ===== SUMMARY CARDS (🔥 FROM daily_pg.php LOGIC) =====
    if ($action === 'summary_cards') {

    $date = $_GET['date'] ?? date('Y-m-d');

    // ✅ Today Sale (FIXED COLUMN NAME)
    $q = $conn->prepare("SELECT COALESCE(SUM(total),0) s FROM sale_details WHERE DATE(date)=?");
    $q->bind_param("s", $date);
    $q->execute();
    $today_sale = floatval($q->get_result()->fetch_assoc()['s']);

    // Today Received
    $q = $conn->prepare("SELECT COALESCE(SUM(receivedAmount),0) r FROM sale_details WHERE DATE(date)=?");
    $q->bind_param("s", $date);
    $q->execute();
    $today_received = floatval($q->get_result()->fetch_assoc()['r']);

    // Today Expense
    $q = $conn->prepare("SELECT COALESCE(SUM(amount),0) e FROM expenditure_data WHERE DATE(date)=?");
    $q->bind_param("s", $date);
    $q->execute();
    $today_exp = floatval($q->get_result()->fetch_assoc()['e']);

    $today_remaining = $today_sale - $today_received;

    respond_json([
        'today_sale' => $today_sale,
        'today_exp' => $today_exp,
        'today_received' => $today_received,
        'today_remaining' => $today_remaining
    ]);
}


    // ===== SALES FOR DATE =====
    if ($action === 'sales_for_date') {
        $date = $_GET['date'] ?? date('Y-m-d');

        $sql = "SELECT
            DATE(date) AS date,
            name,
            kg12, kg15, kg45,
            total, receivedAmount, amountRemaining,
            kg12Received, kg15Received, kg45Received,
            margin
        FROM sale_details
        WHERE date >= ? AND date < DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY id DESC";

        respond_json(fetch_rows($conn, $sql, "ss", day_params($date)));
    }

    // ===== PREVIOUS CLOSING =====
    if ($action === 'previous_closing') {
        $month = $_GET['month'] ?? date('Y-m');
        [$y,$m] = explode('-', $month);
        $start = "$y-$m-01";
        $end = date('Y-m-d', strtotime("$start +1 month"));

        $stmt = $conn->prepare("
            SELECT closing_date, sale_amount, received_amount, remaining_amount, expenditure_amount, parchoon_amount
            FROM daily_closing
            WHERE closing_date >= ? AND closing_date < ?
            ORDER BY closing_date DESC
        ");
        $stmt->bind_param("ss", $start, $end);
        $stmt->execute();

        $rows=[];
        $res=$stmt->get_result();
        while($r=$res->fetch_assoc()) $rows[]=$r;

        respond_json($rows);
    }

    respond_json(['error'=>true,'message'=>'Unknown action']);
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Daily Closing</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="app.css">

<style>
.container{max-width:1200px;margin:20px auto;background:#fff;padding:20px;border-radius:12px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-bottom:20px}
.card{padding:22px;border-radius:14px;color:#fff;font-weight:bold}
.card .label{font-size:16px}
.card .value{font-size:26px;margin-top:10px}
.blue{background:#0072ff;}
.green{background:#00b09b;}
.orange{background:#fb8c00;}
.pink{background:#ff512f;}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #ccc;padding:6px;text-align:center}
thead{background:#1e88e5;color:#fff}
.button{padding:8px 14px;background:#1e88e5;color:#fff;border:none;border-radius:6px;font-weight:bold}

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
</head>
<body>
<div class="nav">
  <a href="insaf_home.php">Dashboard</a>
  <a href="quick_entry.php">Addition</a>
  <a href="daily_parchoon.php">Parchoon</a>
  <a href="sale_record.php">Sale Record</a>
  <a href="stock_records.php">Stock Record</a>
  <a href="expenditure.php">Expenditure</a>
  <a href="daily_guaranty.php">Guaranty</a>
  
  
  <?php if($_SESSION['role']==='md'): ?><a href="cash_in_hand.php">Cash in Hand</a><?php endif; ?>
  <?php if($_SESSION['role']==='md'): ?><a href="profit.php">Profit</a><?php endif; ?>
  <?php if($_SESSION['role']==='md'): ?><a href="assets.php">Assets</a><?php endif; ?>
  
  <a href="image_notes.php">Image Notes</a>
  <a href="checklist.php">Check List</a>
  
</div>

<div class="container">

<h1>Daily Summary</h1>

<div style="display:flex;gap:10px;align-items:center;margin-bottom:15px">
  <input type="date" id="theDate" value="<?=date('Y-m-d')?>">
  <button class="button" id="btnLoad">Load</button>
</div>

<!-- ===== 4 SUMMARY CARDS ===== -->
<div class="grid">
  <div class="card blue"><div class="label">Today Sale</div><div class="value" id="cSale">Rs 0</div></div>
  <div class="card green"><div class="label">Today Expenditure</div><div class="value" id="cExpense">Rs 0</div></div>
  <div class="card orange"><div class="label">Today Received</div><div class="value" id="cReceived">Rs 0</div></div>
  <div class="card pink"><div class="label">Today Remaining</div><div class="value" id="cRemaining">Rs 0</div></div>
</div>

<!-- ===== SALES TABLE ===== -->
<h3>Sales Detail</h3>
<table>
<thead>
<tr>
<th>Date</th><th>Name</th><th>12</th><th>15</th><th>45</th>
<th>Total</th><th>Received</th><th>Remaining</th>
<th>12R</th><th>15R</th><th>45R</th><th>Margin</th>
</tr>
</thead>
<tbody id="salesBody"></tbody>
</table>

<hr>

<!-- ===== PREVIOUS ===== -->
<h3>Previous Closings</h3>
<input type="month" id="monthPicker" value="<?=date('Y-m')?>">
<button class="button" id="btnLoadPrev">Load</button>

<table>
<thead>
<tr><th>Date</th><th>Sale</th><th>Received</th><th>Remaining</th><th>Expense</th><th>Parchoon</th></tr>
</thead>
<tbody id="prevBody"></tbody>
</table>

</div>

<script>
const $ = id => document.getElementById(id);
function fmt(v){ return 'Rs ' + Number(v||0).toLocaleString(); }

// ===== LOAD ALL =====
async function loadAll(){
  const d = $('theDate').value;

  // cards
  const c = await fetch(`daily_closing.php?action=summary_cards&date=${d}`).then(r=>r.json());
  $('cSale').innerText = fmt(c.today_sale);
  $('cExpense').innerText = fmt(c.today_exp);
  $('cReceived').innerText = fmt(c.today_received);
  $('cRemaining').innerText = fmt(c.today_remaining);

  // table
  const rows = await fetch(`daily_closing.php?action=sales_for_date&date=${d}`).then(r=>r.json());
  const tb = $('salesBody'); tb.innerHTML='';
  rows.forEach(r=>{
    const tr=document.createElement('tr');
    tr.innerHTML = `
      <td>${r.date}</td><td>${r.name}</td>
      <td>${r.kg12}</td><td>${r.kg15}</td><td>${r.kg45}</td>
      <td>${Number(r.total).toLocaleString()}</td>
      <td>${Number(r.receivedAmount).toLocaleString()}</td>
      <td>${Number(r.amountRemaining).toLocaleString()}</td>
      <td>${r.kg12Received}</td><td>${r.kg15Received}</td><td>${r.kg45Received}</td>
      <td>${Number(r.margin||0).toLocaleString()}</td>
    `;
    tb.appendChild(tr);
  });
}

// ===== PREVIOUS =====
async function loadPrevious(){
  const m = $('monthPicker').value;
  const rows = await fetch(`daily_closing.php?action=previous_closing&month=${m}`).then(r=>r.json());
  const tb = $('prevBody'); tb.innerHTML='';
  rows.forEach(r=>{
    const tr=document.createElement('tr');
    tr.innerHTML = `
      <td>${r.closing_date}</td>
      <td>${fmt(r.sale_amount)}</td>
      <td>${fmt(r.received_amount)}</td>
      <td>${fmt(r.remaining_amount)}</td>
      <td>${fmt(r.expenditure_amount)}</td>
      <td>${fmt(r.parchoon_amount)}</td>
    `;
    tb.appendChild(tr);
  });
}

$('btnLoad').onclick = loadAll;
$('btnLoadPrev').onclick = loadPrevious;
window.onload = loadAll;
</script>

</body>
</html>
