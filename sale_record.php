<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

include 'db_connect.php';

// 🔒 HARD STOP: API MODE
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
}

$role = $_SESSION['role'] ?? '';

/* ============================
   MD UPDATE & DELETE HANDLERS
   ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    /* DELETE — MD ONLY */
    if ($_POST['action'] === 'delete' && $role === 'md') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM sale_details WHERE id=$id");
        echo json_encode(["message" => "Record deleted"]);
        exit;
    }

    /* UPDATE — MD ONLY */
    if ($_POST['action'] === 'update' && $role === 'md') {

    $stmt = $conn->prepare("
        UPDATE sale_details 
        SET 
            name=?,
            kg12=?, kg15=?, kg45=?,
            total=?,
            receivedAmount=?,
            amountRemaining=?,
            kg12Received=?, kg15Received=?, kg45Received=?,
            totalCylinderRemaining=?
        WHERE id=?
    ");

    $stmt->bind_param(
        "siiidddiiiii",
        $_POST['name'],
        $_POST['kg12'],
        $_POST['kg15'],
        $_POST['kg45'],
        $_POST['total'],
        $_POST['received'],
        $_POST['remaining'],
        $_POST['kg12Received'],
        $_POST['kg15Received'],
        $_POST['kg45Received'],
        $_POST['totalCylinderRemaining'],
        $_POST['id']
    );

    $stmt->execute();
    echo json_encode(["message" => "Record updated"]);
    exit;
}


}

/* ============================
   AJAX GET HANDLERS
   ============================ */
if (isset($_GET['action'])) {
  header('Content-Type: application/json; charset=utf-8');
  $action = $_GET['action'];

  // CUSTOMER AGGREGATE (by month/year)
  if ($_GET['action'] === 'customer_agg_all') {

    $res = $conn->query("
    SELECT
  name,

  SUM(kg12) AS kg12,
  SUM(kg15) AS kg15,
  SUM(kg45) AS kg45,

  SUM(total) AS total,
  SUM(receivedAmount) AS received,
  SUM(amountRemaining) AS remaining,

  SUM(kg12Received) AS kg12Received,
  SUM(kg15Received) AS kg15Received,
  SUM(kg45Received) AS kg45Received,

  -- 🔥 FIX: calculate cylinder remaining
  (SUM(kg12) + SUM(kg15) + SUM(kg45))
  -
  (SUM(kg12Received) + SUM(kg15Received) + SUM(kg45Received))
  AS totalCylinderRemaining

FROM sale_details
GROUP BY name
ORDER BY name

");

echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
  }
  
// LIST — optional month/year filter
  if ($action === 'list') {
    $m = isset($_GET['month']) && is_numeric($_GET['month']) ? intval($_GET['month']) : null;
    $y = isset($_GET['year']) && is_numeric($_GET['year']) ? intval($_GET['year']) : null;

    if ($m && $y) {
        $stmt = $conn->prepare("
            SELECT 
  id,
  name,
  DATE_FORMAT(date,'%Y-%m-%d') AS date,
  kg12, kg15, kg45,
  total,
  receivedAmount,
  amountRemaining,
  IFNULL(kg12Received,0) AS kg12Received,
  IFNULL(kg15Received,0) AS kg15Received,
  IFNULL(kg45Received,0) AS kg45Received,
  IFNULL(totalCylinderRemaining,0) AS totalCylinderRemaining
FROM sale_details

            WHERE MONTH(date)=? AND YEAR(date)=?
            ORDER BY date DESC, id DESC
        ");
        $stmt->bind_param("ii", $m, $y);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query("
            SELECT 
  id,
  name,
  DATE_FORMAT(date,'%Y-%m-%d') AS date,
  kg12, kg15, kg45,
  total,
  receivedAmount,
  amountRemaining,
  IFNULL(kg12Received,0) AS kg12Received,
  IFNULL(kg15Received,0) AS kg15Received,
  IFNULL(kg45Received,0) AS kg45Received,
  IFNULL(totalCylinderRemaining,0) AS totalCylinderRemaining
FROM sale_details

            ORDER BY date DESC, id DESC
        ");
    }

    $out = [];
    while ($row = $res->fetch_assoc()) $out[] = $row;
    echo json_encode($out);
    exit;
  }

  // month_totals (used for the top summary when month filter applied if needed)
  if ($action === 'month_totals') {
    $m = intval($_GET['month'] ?? date('n'));
    $y = intval($_GET['year'] ?? date('Y'));
    $stmt = $conn->prepare("
      SELECT 
  id,
  name,
  DATE_FORMAT(date,'%Y-%m-%d') AS date,
  kg12, kg15, kg45,
  total,
  receivedAmount,
  amountRemaining,
  IFNULL(kg12Received,0) AS kg12Received,
  IFNULL(kg15Received,0) AS kg15Received,
  IFNULL(kg45Received,0) AS kg45Received,
  IFNULL(totalCylinderRemaining,0) AS totalCylinderRemaining
FROM sale_details

      WHERE MONTH(date)=? AND YEAR(date)=?
    ");
    $stmt->bind_param("ii", $m, $y);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    echo json_encode($res);
    exit;
  }

  // ALL_TOTALS — returns totals across all records, or for a specific name if provided
  if ($action === 'all_totals') {
    $name = isset($_GET['name']) ? trim($_GET['name']) : '';

    if ($name !== '') {
        $stmt = $conn->prepare("
            SELECT 
                SUM(kg12) AS kg12,
                SUM(kg15) AS kg15,
                SUM(kg45) AS kg45,

                SUM(total) AS total,
                SUM(receivedAmount) AS received,
                SUM(amountRemaining) AS remaining,

                SUM(kg12Received) AS kg12Received,
                SUM(kg15Received) AS kg15Received,
                SUM(kg45Received) AS kg45Received
            FROM sale_details
            WHERE name = ?
        ");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_assoc());
        exit;
    }

    // 🔹 ALL RECORDS TOTAL
    $res = $conn->query("
        SELECT 
            SUM(kg12) AS kg12,
            SUM(kg15) AS kg15,
            SUM(kg45) AS kg45,

            SUM(total) AS total,
            SUM(receivedAmount) AS received,
            SUM(amountRemaining) AS remaining,

            SUM(kg12Received) AS kg12Received,
            SUM(kg15Received) AS kg15Received,
            SUM(kg45Received) AS kg45Received
        FROM sale_details
    ");

    echo json_encode($res->fetch_assoc());
    exit;
}


  // customer_history by name
    // customer_history (FINAL – KEEP ONLY THIS)
  if ($action === 'customer_history' && !empty($_GET['name'])) {

      $name = trim($_GET['name']);

      $stmt = $conn->prepare("
          SELECT 
  id,
  name,
  DATE_FORMAT(date,'%Y-%m-%d') AS date,
  kg12, kg15, kg45,
  total,
  receivedAmount,
  amountRemaining,
  IFNULL(kg12Received,0) AS kg12Received,
  IFNULL(kg15Received,0) AS kg15Received,
  IFNULL(kg45Received,0) AS kg45Received,
  IFNULL(totalCylinderRemaining,0) AS totalCylinderRemaining
FROM sale_details

          WHERE TRIM(LOWER(name)) = TRIM(LOWER(?))
          ORDER BY date ASC
      ");

      $stmt->bind_param("s", $name);
      $stmt->execute();

      $res = $stmt->get_result();
      $out = [];

      while ($r = $res->fetch_assoc()) {
          $out[] = $r;
      }

      echo json_encode($out);
      exit;
  }

  // 🔴 IMPORTANT: stop PHP here for ANY action
  exit;
}

// 🔒 STOP ANY HTML OUTPUT FOR AJAX
if (isset($_GET['action'])) {
    exit;
}

/* ============================
   CUSTOMER AGGREGATE (ALL)
   ============================ */
if (isset($_GET['action']) && $_GET['action'] === 'customer_agg_all') {

    header('Content-Type: application/json; charset=utf-8');

    $res = $conn->query("
        SELECT
            name,
            SUM(kg12) AS kg12,
            SUM(kg15) AS kg15,
            SUM(kg45) AS kg45,

            SUM(total) AS total,
            SUM(receivedAmount) AS received,
            SUM(amountRemaining) AS remaining,

            SUM(kg12Received) AS kg12Received,
            SUM(kg15Received) AS kg15Received,
            SUM(kg45Received) AS kg45Received,

            (
              SUM(kg12)+SUM(kg15)+SUM(kg45)
              -
              (SUM(kg12Received)+SUM(kg15Received)+SUM(kg45Received))
            ) AS totalCylinderRemaining
        FROM sale_details
        GROUP BY name
        ORDER BY name
    ");

    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
}

/* ============================
   CUSTOMER HISTORY (BY NAME)
   ============================ */
if (isset($_GET['action']) && $_GET['action'] === 'customer_history' && !empty($_GET['name'])) {

    header('Content-Type: application/json; charset=utf-8');

    $stmt = $conn->prepare("
        SELECT
            DATE_FORMAT(date,'%Y-%m-%d') AS date,
            kg12, kg15, kg45,
            total, receivedAmount, amountRemaining,
            kg12Received, kg15Received, kg45Received,
            totalCylinderRemaining
        FROM sale_details
        WHERE TRIM(LOWER(name)) = TRIM(LOWER(?))
        ORDER BY date ASC
    ");

    $stmt->bind_param("s", $_GET['name']);
    $stmt->execute();

    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Sale Records — Insaf AK LPG</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<style>

body{font-family:Poppins,system-ui;background:#eef2f7;margin:0}
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
.container{max-width:1200px;margin:20px auto;padding:20px;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.08)}
.header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap}
.title h1{margin:0;font-size:28px;font-weight:900;color:#1e88e5}
.btn{padding:8px 14px;border:none;border-radius:8px;background:#1e88e5;color:#fff;font-weight:700;cursor:pointer}
.btn.small{padding:6px 10px}
.btn.ghost{background:#fff;color:#333;border:1px solid #d0d7e2}
.card-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:12px}
.card{padding:18px;border-radius:12px;text-align:center;font-weight:700;color:#fff}
.card .label{font-size:13px;opacity:.95}
.card .value{margin-top:6px;font-size:18px;font-weight:900}
.table-wrap{margin-top:20px;overflow:auto;background:#fff;border-radius:12px}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #e5e7eb}
th{background:#1e88e5;color:#fff;font-weight:800}
tr:hover{background:#f1f5ff}
.total-row{background:#e3f2fd;font-weight:900}
.actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.modal-root{position:fixed;inset:0;display:none;justify-content:center;align-items:center;background:rgba(0,0,0,.5);z-index:9999}
.modal{width:95%;max-width:700px;padding:20px;background:#fff;border-radius:12px}
.history-scroll {
  max-height: 65vh;
  overflow: auto;
  position: relative;
}

/* Sticky header */
.history-scroll thead th {
  position: sticky;
  top: 0;
  z-index: 3;
  background: #1e88e5;
}

/* Sticky TOTAL row */
.history-scroll .total-row {
  position: sticky;
  top: 42px; /* height of header row */
  z-index: 2;
  background: #4CAF50 !important;
  color: #fff;
}

.history-scroll table{width:100%;border-collapse:collapse}
.history-scroll th,.history-scroll td{padding:10px;border-bottom:1px solid #ddd;text-align:right}
.history-scroll th:first-child,.history-scroll td:first-child{text-align:left}
.history-scroll thead tr:first-child{background:#1e88e5;color:#fff;font-weight:900}
.history-scroll .total-row{background:#4CAF50;color:#fff;font-weight:900}
body.dark{background:#0f172a;color:#e5e7eb}
body.dark .container{background:#020617}
body.dark th{background:#020617}
body.dark td{border-bottom:1px solid #1e293b}
@media(max-width:600px){.header{flex-direction:column;align-items:flex-start}}

</style>


<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<body><br>
<div class="nav">
  <a href="insaf_home.php">Dashboard</a>
  <a href="quick_entry.php">Addition</a>
 
  <a href="daily_parchoon.php">Parchoon</a>
  
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

  <div class="header">
    <div class="title"><div style="font-size:30px"></div><h1>Sale Records</h1></div>
    <div class="controls">
      <button id="darkToggleTop" class="btn small ghost">🌙</button>
    </div>
  </div>

 

  <br>

  <!-- Top cards: NOTE - these show ALL records by default, but if user searches a name they will show totals for that name -->
  <!-- Two rows (5 + 5) -->
  
  <div class="card-row" id="cardRowTop">
    <div class="card" style="background:linear-gradient(135deg,#8e44ad,#c678ff);">
  <div class="label">🛢️ 12KG Sale</div>
  <div class="value" id="card_kg12">0</div>
</div>

    <div class="card" style="background:linear-gradient(135deg,#0072ff,#00c6ff);">
      <div class="label">🛢️ 15KG Sale</div>
      <div class="value" id="card_kg15">0</div>
    </div>
    <div class="card" style="background:linear-gradient(135deg,#00b09b,#96c93d);">
      <div class="label">🛢️ 45KG Sale</div>
      <div class="value" id="card_kg45">0</div>
    </div>
    <div class="card" style="background:linear-gradient(135deg,#ff512f,#f09819);">
      <div class="label">💵 Total Amount</div>
      <div class="value" id="card_totalAmount">0</div>
    </div>
    <div class="card" style="background:linear-gradient(135deg,#ff416c,#ff4b2b);">
      <div class="label">💰 Received Amount</div>
      <div class="value" id="card_received">0</div>
    </div>
    <div class="card" style="background:linear-gradient(135deg,#455a64,#607d8b);">
      <div class="label">📌 Remaining Amount</div>
      <div class="value" id="card_remaining">0</div>
    </div>
  </div>

  <div class="card-row" id="cardRowBottom">
    <div class="card" style="background:linear-gradient(135deg,#6d28d9,#a78bfa);">
  <div class="label">12KG Rec</div>
  <div class="value" id="card_12kgRec">0</div>
</div>

    <div class="card" style="background:linear-gradient(135deg,#8e44ad,#c678ff);">
      <div class="label">15KG Rec</div>
      <div class="value" id="card_15kgRec">0</div>
    </div>
    <div class="card" style="background:linear-gradient(135deg,#6a1b9a,#9c27b0);">
      <div class="label">45KG Rec</div>
      <div class="value" id="card_45kgRec">0</div>
    </div>
    <div class="card" style="background:linear-gradient(135deg,#2e7d32,#60ad5e);">
      <div class="label">15KG Rem</div>
      <div class="value" id="card_15kgRem">0</div>
    </div>
    <div class="card" style="background:linear-gradient(135deg,#2b6cb0,#4fb3f8);">
      <div class="label">45KG Rem</div>
      <div class="value" id="card_45kgRem">0</div>
    </div>
    <div class="card" style="background:linear-gradient(135deg,#ff8a65,#ffb085);">
      <div class="label">Total Cylinder Remaining</div>
      <div class="value" id="card_totalCylRem">0</div>
    </div>
  </div>

  <br>

  <div class="actions">
    <!-- moved PDF/Excel/Search to LEFT as requested -->
    <div style="display:flex;gap:8px;align-items:center;">
	
	  <select id="monthSelect"></select>
      <select id="yearSelect"></select>
      <button id="filterBtn">📅 Filter</button>
	  
      <div style="margin-left:auto;display:flex;gap:8px;align-items:center;">

      <!-- Expand / Collapse button (toggling only records under total row) -->
      <button id="toggleBodyBtn" class="btn small" title="Expand / Collapse records under total row">▼ Expand</button>

      <button id="pdfBtn" class="btn">📑 PDF</button>
      <button id="csvBtnTop" class="btn small">📊 Excel</button>
      <input id="searchBox" placeholder="🔍 Search by name..." style="padding:8px;border-radius:8px;border:1px solid #ffd4d4;min-width:220px" />
    </div>

  </div>
  </div>

  <br>

  <div class="table-wrap print-area" id="printMainTable">

    <table id="recordsTable">
      <thead>
        <tr>
          <th style="width:110px">Date</th>
          <th>Name</th>
          <th style="text-align:right">12 KG</th>
  <th style="text-align:right">15 KG</th>
  <th style="text-align:right">45 KG</th>
  <th style="text-align:right">Total</th>
  <th style="text-align:right">Received</th>
  <th style="text-align:right">Remaining</th>
  <th style="text-align:right">12KG Rec</th>
  <th style="text-align:right">15KG Rec</th>
  <th style="text-align:right">45KG Rec</th>
  <th style="text-align:right">T C Rem</th>
  <th style="text-align:right">T A Rem</th>
          
        </tr>
      </thead>

      <!-- Total row (permanent, directly under header) -->
      <tbody id="totalRowBody">
        <tr id="totalRow" class="total-row">
          <td>Total</td>
          <td>Name</td>
		  <td style="text-align:right" id="t_kg12">0</td>
          <td style="text-align:right" id="t_kg15">0</td>
          <td style="text-align:right" id="t_kg45">0</td>
          <td style="text-align:right" id="t_total">0</td>
          <td style="text-align:right" id="t_received">0</td>
          <td style="text-align:right" id="t_remaining">0</td>
		  <td style="text-align:right" id="t_kg12Rec">0</td>
          <td style="text-align:right" id="t_kg15Rec">0</td>
          <td style="text-align:right" id="t_kg45Rec">0</td>
          <td style="text-align:right" id="t_cylRem">0</td>
		  <td style="text-align:right" id="t_amtRem">0</td>
       <td></td>
        </tr>
      </tbody>

      <tbody id="recordsBody"></tbody>

    </table>
  </div><br><br>
<tr>
  <td colspan="12" style="padding:0">
    <div style="height:2px;background:#4CAF50"></div>
  </td>
</tr>
<br><br>
  <!-- ================= CUSTOMER PANEL ================= -->
<div class="customer-panel">

  <!-- SEARCH + REFRESH -->
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
    <input id="custListSearch" placeholder="🔍 Search customer...">
    <button id="custRefresh" class="btn" style="background:#1e88e5">Refresh</button>
    <span style="font-weight:800;color:#333">Click customer for full history</span>
  </div>

  <!-- COLLAPSE + EXPORT BUTTONS -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:8px;flex-wrap:wrap">
    <button id="toggleCustomerTable" class="btn">🔽 Collapse</button>

    <div style="display:flex;gap:6px">
      <button id="exportExcelCustomers" class="btn" style="background:#1e88e5">📊 Excel</button>
      <button id="exportPdfCustomers" class="btn" style="background:#4CAF50">📄 PDF</button>
    </div>
  </div>

  <!-- TABLE WRAPPER -->
  <div id="customerTableBox">
    <table class="customer-table" id="customerTableAgg">
      <thead>
  <tr>
    <th>Customer</th>

    <th>12KG</th>
    <th>15KG</th>
    <th>45KG</th>

    <th>Total</th>
    <th>Received</th>
    <th>Remaining</th>

    <th>12KG Rec</th>
    <th>15KG Rec</th>
    <th>45KG Rec</th>

    <th>T C Rem</th>
    <th>T A Rem</th>
  </tr>
</thead>

      <tbody id="customerListBody"></tbody>
    </table>
  </div>

</div>
<!-- ================= END CUSTOMER PANEL ================= -->


</div> <!-- container end -->

<!-- Modal -->
<div class="modal-root" id="historyModal">
  <div class="modal" style="max-width:1100px">

    <!-- HEADER -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <h2 id="modalTitle" style="margin:0;color:#1e88e5;font-weight:900">
        History
      </h2>

      <div style="display:flex;gap:8px">
  <button id="customerReportPdfBtn" class="btn" style="background:#4CAF50">📄 PDF Report</button>
  <button id="customerReportExcelBtn" class="btn" style="background:#1e88e5">📊 Excel Report</button>
  <button id="closeHistoryBtn" class="btn">Close</button>
</div>

    </div>

    <!-- TABLE -->
    <div id="modalBody" class="history-scroll print-area"></div>


 
</div>
</div>
<script>

/* ---------------------------
   Client JS + Expand/Collapse Rows
   --------------------------- */

const API = '<?= basename(__FILE__) ?>';
let currentMonth = new Date().getMonth() + 1;
let currentYear = new Date().getFullYear();

/* ============================
   Populate Month & Year Selects
   ============================ */
(function () {
    const ms = document.getElementById('monthSelect');
    const ys = document.getElementById('yearSelect');
    const months = [
        'January','February','March','April','May','June',
        'July','August','September','October','November','December'
    ];

    months.forEach((m,i) =>
        ms.innerHTML += `<option value="${i+1}">${m}</option>`
    );

    const current = new Date().getFullYear();
    for (let y = current; y >= 2010; y--)
        ys.innerHTML += `<option value="${y}">${y}</option>`;

    ms.value = currentMonth;
    ys.value = currentYear;
})();

/* Escape HTML */
function escapeHtml(s){
    return String(s)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
}

function money(n) {
    return Math.round(Number(n || 0)).toLocaleString();
}



async function fetchJson(url){
    const res = await fetch(url);
    return res.json();
}

/* =====================================
   Load CARDS TOTALS
   ===================================== */
async function loadCardsTotals(name = '') {
    try {
        let url = `${API}?action=all_totals`;
        if (name.trim()) url += `&name=${encodeURIComponent(name.trim())}`;

        const data = await fetchJson(url); // ✅ THIS WAS MISSING

        const kg12 = Number(data.kg12 || 0);
        const kg15 = Number(data.kg15 || 0);
        const kg45 = Number(data.kg45 || 0);

        const total = Number(data.total || 0);
        const received = Number(data.received || 0);
        const remaining = Number(data.remaining || 0);

        const kg12Rec = Number(data.kg12Received || 0);
        const kg15Rec = Number(data.kg15Received || 0);
        const kg45Rec = Number(data.kg45Received || 0);

        const kg12Rem = kg12 - kg12Rec;
        const kg15Rem = kg15 - kg15Rec;
        const kg45Rem = kg45 - kg45Rec;

        const totalCylRem = kg12Rem + kg15Rem + kg45Rem;

        document.getElementById('card_kg12').textContent = kg12;
        document.getElementById('card_kg15').textContent = kg15;
        document.getElementById('card_kg45').textContent = kg45;

        document.getElementById('card_totalAmount').textContent = "" + money(total);
        document.getElementById('card_received').textContent = "" + money(received);
        document.getElementById('card_remaining').textContent = "" + money(remaining);


        document.getElementById('card_12kgRec').textContent = kg12Rec;
        document.getElementById('card_15kgRec').textContent = kg15Rec;
        document.getElementById('card_45kgRec').textContent = kg45Rec;

        document.getElementById('card_15kgRem').textContent = kg15Rem;
        document.getElementById('card_45kgRem').textContent = kg45Rem;
        document.getElementById('card_totalCylRem').textContent = totalCylRem;

    } catch (e) {
        console.error("Cards load failed:", e);
    }
}


/* =====================================
   LOAD TABLE (MONTH/YEAR FILTER)
   ===================================== */
async function loadRows(month = currentMonth, year = currentYear) {
    try {
        const rows = await fetchJson(`${API}?action=list&month=${month}&year=${year}`);
        const tbody = document.getElementById('recordsBody');
        tbody.innerHTML = "";

        rows.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
  <td>${r.date}</td>
  <td>${escapeHtml(r.name)}</td>
  <td style="text-align:right">${r.kg12}</td>
  <td style="text-align:right">${r.kg15}</td>
  <td style="text-align:right">${r.kg45}</td>
  <td style="text-align:right">${money(r.total)}</td>
  <td style="text-align:right">${money(r.receivedAmount)}</td>
  <td style="text-align:right">${money(r.amountRemaining)}</td>

  <td style="text-align:right">${r.kg12Received}</td>
  <td style="text-align:right">${r.kg15Received}</td>
  <td style="text-align:right">${r.kg45Received}</td>
  <td style="text-align:right">${r.totalCylinderRemaining}</td>
  <td style="text-align:right">${r.amountRemaining}</td>
  
`;

            tr.addEventListener("click", () => showHistory(r.name));
            tbody.appendChild(tr);
        });

        updateTotalRow();

    } catch (e) {
        console.error("Load rows failed:", e);
    }
}

/* =====================================
   UPDATE TOTAL ROW
   ===================================== */
function num(x) {
    return Number(String(x).replace(/,/g, '')) || 0;
}

function updateTotalRow() {
    const rows = document.querySelectorAll("#recordsBody tr");

    let sum12=0,sum15=0,sum45=0,sumTotal=0,sumReceived=0,sumRemaining=0,
        sum12Rec=0,sum15Rec=0,sum45Rec=0,sumCylRem=0;

    rows.forEach(tr => {
        const t = tr.children;

        sum12 += num(t[2].innerText);
        sum15 += num(t[3].innerText);
        sum45 += num(t[4].innerText);

        sumTotal += num(t[5].innerText);
        sumReceived += num(t[6].innerText);
        sumRemaining += num(t[7].innerText);

        sum12Rec += num(t[8].innerText);
        sum15Rec += num(t[9].innerText);
        sum45Rec += num(t[10].innerText);

        sumCylRem += num(t[11].innerText);
    });

    document.getElementById('t_kg12').textContent = sum12;
    document.getElementById('t_kg15').textContent = sum15;
    document.getElementById('t_kg45').textContent = sum45;

    document.getElementById('t_total').textContent = "" + money(sumTotal);
    document.getElementById('t_received').textContent = "" + money(sumReceived);
    document.getElementById('t_remaining').textContent = "" + money(sumRemaining);

    document.getElementById('t_kg12Rec').textContent = sum12Rec;
    document.getElementById('t_kg15Rec').textContent = sum15Rec;
    document.getElementById('t_kg45Rec').textContent = sum45Rec;

    document.getElementById('t_cylRem').textContent = sumCylRem;
    document.getElementById('t_amtRem').textContent = "" + money(sumRemaining);
}


/* =====================================
   EXPAND / COLLAPSE ROWS UNDER TOTAL
   ===================================== */
(function () {
    const btn = document.getElementById("toggleBodyBtn");
    const body = document.getElementById("recordsBody");

    if (!btn || !body) return;

    let expanded = false; // ❗ Default = collapsed

    function updateState() {
        if (expanded) {
            body.style.display = "";
            btn.textContent = "▲ Collapse";
        } else {
            body.style.display = "none";
            btn.textContent = "▼ Expand";
        }
    }

    updateState(); // Set initial collapsed state

    btn.addEventListener("click", () => {
        expanded = !expanded;
        updateState();
    });
})();

/* ============================
   SEARCH + FILTER + EXPORT + PDF
   ============================ */

document.getElementById('filterBtn').addEventListener('click', () => {
    currentMonth = Number(monthSelect.value);
    currentYear = Number(yearSelect.value);
    loadRows(currentMonth, currentYear);
});

/* SEARCH */
document.getElementById('searchBox').addEventListener('input', e => {
    const q = e.target.value.trim().toLowerCase();

    if (!q) {
        loadRows(currentMonth, currentYear);
        loadCardsTotals('');
        return;
    }

    const rows = document.querySelectorAll("#recordsBody tr");
    let matchedName = "";

    rows.forEach(tr => {
        const text = tr.innerText.toLowerCase();
        const visible = text.includes(q);
        tr.style.display = visible ? "" : "none";
        if (visible && !matchedName) matchedName = tr.children[1].innerText.trim();
    });

    updateTotalRow();
    loadCardsTotals(matchedName || '');
});

/* EXCEL */
document.getElementById('csvBtnTop').addEventListener('click', async () => {
    const data = await fetchJson(`${API}?action=list&month=${currentMonth}&year=${currentYear}`);
    const sheet = XLSX.utils.json_to_sheet(data);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, sheet, "Sale Records");
    XLSX.writeFile(wb, "sale_records.xlsx");
});

/* PDF */
// MAIN TABLE PDF
document.getElementById('pdfBtn').addEventListener('click', () => {
  document.querySelectorAll('.print-area').forEach(e => e.classList.remove('print-area'));
  document.getElementById('printMainTable').classList.add('print-area');
  window.print();
});

/* DARK MODE */
document.getElementById('darkToggleTop').addEventListener('click', () =>
    document.body.classList.toggle('dark')
);

/* INIT */
window.addEventListener("DOMContentLoaded", () => {
    loadRows();          // load ALL data
    loadCardsTotals('');
});

document.getElementById("customerReportPdfBtn").addEventListener("click", () => {
    // Print only modalBody
    document.querySelectorAll(".print-area").forEach(e => e.classList.remove("print-area"));
    modalBody.classList.add("print-area");
    window.print();
});

document.getElementById("customerReportExcelBtn").addEventListener("click", async () => {

    const name = document.getElementById("modalTitle").innerText.replace("History — ", "").trim();

    const rows = await fetchJson(`${API}?action=customer_history&name=` + encodeURIComponent(name));

    if (!rows.length) {
        alert("No data to export");
        return;
    }

    // Sort A → Z by date
    rows.sort((a,b) => new Date(a.date) - new Date(b.date));

    const sheet = XLSX.utils.json_to_sheet(rows);

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, sheet, "Customer Report");

    XLSX.writeFile(wb, name + "_FULL_REPORT.xlsx");
});
</script>

<script>
const customerListBody = document.getElementById("customerListBody");
const custListSearch = document.getElementById("custListSearch");
const custRefresh = document.getElementById("custRefresh");

/* ============================
   LOAD CUSTOMER AGGREGATES
   ============================ */
async function loadCustomerAggregates() {

    customerListBody.innerHTML =
        '<tr><td colspan="12">Loading...</td></tr>';

    try {
        const res = await fetch('sale_record.php?action=customer_agg_all');
        const rows = await res.json();

        customerListBody.innerHTML = "";

        if (!rows.length) {
            customerListBody.innerHTML =
                '<tr><td colspan="12">No customers</td></tr>';
            return;
        }

        rows.forEach(v => {
            const tr = document.createElement("tr");
            tr.innerHTML = `
                <td><span class="cust-name" style="cursor:pointer;color:#1e88e5;font-weight:700">${v.name}</span></td>

                <td>${v.kg12}</td>
                <td>${v.kg15}</td>
                <td>${v.kg45}</td>

                <td>${v.total}</td>
                <td>${v.received}</td>
                <td>${v.remaining}</td>

                <td>${v.kg12Received}</td>
                <td>${v.kg15Received}</td>
                <td>${v.kg45Received}</td>

                <td>${v.totalCylinderRemaining}</td>
                <td>${v.remaining}</td>
            `;

            tr.querySelector(".cust-name")
  .addEventListener("click", () => openHistoryModal(v.name));


            customerListBody.appendChild(tr);
        });

    } catch (e) {
        console.error(e);
        customerListBody.innerHTML =
            '<tr><td colspan="12">Error loading customers</td></tr>';
    }
}

/* ============================
   CUSTOMER SEARCH
   ============================ */
custListSearch.addEventListener("input", () => {
    const q = custListSearch.value.toLowerCase();
    document.querySelectorAll("#customerListBody tr").forEach(tr => {
        tr.style.display = tr.innerText.toLowerCase().includes(q) ? "" : "none";
    });
});

/* ============================
   REFRESH BUTTON
   ============================ */
custRefresh.addEventListener("click", () => {
    custListSearch.value = "";
    loadCustomerAggregates();
});
</script>
<script>
const modal = document.getElementById("historyModal");
const modalBody = document.getElementById("modalBody");
const modalTitle = document.getElementById("modalTitle");
const closeHistoryBtn = document.getElementById("closeHistoryBtn");

closeHistoryBtn.addEventListener("click", () => {
    modal.style.display = "none";
});

/* ============================
   OPEN CUSTOMER HISTORY
   ============================ */
async function openHistoryModal(name){
    modal.style.display = "flex";
    modalTitle.innerText = "History — " + name;
    modalBody.innerHTML = "Loading...";

    const res = await fetch(
        API + '?action=customer_history&name=' + encodeURIComponent(name)
    );
    const rows = await res.json();

    // ================== CALCULATE TOTALS ==================
    let t12=0, t15=0, t45=0;
    let tTotal=0, tReceived=0, tRemaining=0;
    let t12Rec=0, t15Rec=0, t45Rec=0;
    let tCyl=0;

    let runningBalance = 0;

    rows.forEach(r=>{
        t12 += Number(r.kg12)||0;
        t15 += Number(r.kg15)||0;
        t45 += Number(r.kg45)||0;

        tTotal += Number(r.total)||0;
        tReceived += Number(r.receivedAmount)||0;
        tRemaining += Number(r.amountRemaining)||0;

        t12Rec += Number(r.kg12Received)||0;
        t15Rec += Number(r.kg15Received)||0;
        t45Rec += Number(r.kg45Received)||0;

        tCyl += Number(r.totalCylinderRemaining)||0;

        runningBalance += Number(r.amountRemaining) || 0;
    });

    // ================== BUILD TABLE ==================
    let html = `
    <table>
      <thead>
        <tr>
          <th>Date</th><th>12KG</th><th>15KG</th><th>45KG</th>
          <th>Total</th><th>Received</th><th>Remaining</th>
          <th>12KG Rec</th><th>15KG Rec</th><th>45KG Rec</th>
          <th>T C Rem</th><th>T A Rem</th>
        </tr>
      </thead>
      <tbody>

      <tr class="total-row">
        <td><b>TOTAL</b></td>
        <td>${t12}</td>
        <td>${t15}</td>
        <td>${t45}</td>
        <td>${money(tTotal)}</td>
        <td>${money(tReceived)}</td>
        <td>${money(tRemaining)}</td>

        <td>${t12Rec}</td>
        <td>${t15Rec}</td>
        <td>${t45Rec}</td>
        <td>${tCyl}</td>
        <td>${money(runningBalance)}</td>
      </tr>
    `;

    // ================== ROWS ==================
    let rb = 0;

    rows.forEach(r=>{
        const rem = Number(r.amountRemaining) || 0;
        rb += rem;

        html += `
        <tr>
          <td>${r.date}</td>
          <td>${r.kg12}</td>
          <td>${r.kg15}</td>
          <td>${r.kg45}</td>
          <td>${money(r.total)}</td>
          <td>${money(r.receivedAmount)}</td>
          <td>${money(rem)}</td>
          <td>${r.kg12Received}</td>
          <td>${r.kg15Received}</td>
          <td>${r.kg45Received}</td>
          <td>${r.totalCylinderRemaining}</td>
          <td>${money(rb)}</td>
        </tr>
        `;
    });

    html += "</tbody></table>";

    modalBody.innerHTML = html;
}

</script>


<script>
const toggleCustomerBtn = document.getElementById("toggleCustomerTable");
const customerTableBox = document.getElementById("customerTableBox");

toggleCustomerBtn.addEventListener("click", () => {
    const hidden = customerTableBox.style.display === "none";
    customerTableBox.style.display = hidden ? "" : "none";
    toggleCustomerBtn.innerText = hidden ? "🔽 Collapse" : "▶ Expand";
});
</script>
<script>
window.addEventListener("DOMContentLoaded", () => {
    loadCustomerAggregates();
});
</script>

<script>
document.getElementById("historyExcelBtn").addEventListener("click", () => {
    const table = document.querySelector("#modalBody table");
    if (!table) {
        alert("No data to export");
        return;
    }

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(table);

    XLSX.utils.book_append_sheet(wb, ws, "Customer History");
    XLSX.writeFile(wb, "customer_history.xlsx");
});
</script>

</body>
</html>
