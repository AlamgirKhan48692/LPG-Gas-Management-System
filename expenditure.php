<?php
session_start();
include 'db_connect.php';

$role = $_SESSION['role'] ?? '';

// If this is an AJAX request for actions, handle and return JSON
if ((isset($_GET['action']) && $_GET['action']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']))) {
    header('Content-Type: application/json; charset=utf-8');

    // === LOAD data (GET) ===
    if (isset($_GET['action']) && $_GET['action'] === 'load') {
        $type = $_GET['type'] ?? 'ShowAll';
        $type = strtolower(trim($type));

        $where = "";
        if ($type === 'vehicle') $where = "WHERE category = 'Vehicle'";
        elseif ($type === 'shop') $where = "WHERE category = 'Shop'";
        elseif ($type === 'salaries') $where = "WHERE category = 'Salaries'";
        elseif ($type === 'other') $where = "WHERE category = 'Other'";
        else $where = "";

        $data = [];
        $sql = "SELECT id, category, date, details, byhand, amount FROM expenditure_data $where ORDER BY date DESC";
        if ($res = $conn->query($sql)) {
            while ($r = $res->fetch_assoc()) $data[] = $r;
        }

        // totals
        $totals = [
            'vehicle' => 0,
            'shop' => 0,
            'salaries' => 0,
            'other' => 0,
            'grand' => 0
        ];
        $cats = ['Vehicle','Shop','Salaries','Other'];
        foreach ($cats as $cat) {
            $safe = $conn->real_escape_string($cat);
            $r = $conn->query("SELECT IFNULL(SUM(amount),0) AS sum FROM expenditure_data WHERE category='{$safe}'");
            if ($r) $totals[strtolower($cat)] = (float)$r->fetch_assoc()['sum'];
        }
        $totals['grand'] = $totals['vehicle'] + $totals['shop'] + $totals['salaries'] + $totals['other'];

        echo json_encode(['status'=>'success','data'=>$data,'totals'=>$totals]);
        exit;
    }

    // === SAVE new record (POST) ===
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
        $category = $_POST['category'] ?? '';
        $date = $_POST['date'] ?? '';
        $details = $_POST['details'] ?? '';
        $byhand = $_POST['byhand'] ?? '';
        $amount = $_POST['amount'] ?? '';

        // basic validation
        if (empty($category) || empty($date) || empty($details) || $amount === '') {
            echo json_encode(['status'=>'error','message'=>'Missing required fields']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO expenditure_data (category, date, details, byhand, amount, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssd", $category, $date, $details, $byhand, $amount);
        if ($stmt->execute()) {
            echo json_encode(['status'=>'success']);
        } else {
            echo json_encode(['status'=>'error','message'=>'Insert failed']);
        }
        $stmt->close();
        exit;
    }

    // === UPDATE record (POST) — MD only ===
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
        if ($role !== 'md') {
            echo json_encode(['status'=>'error','message'=>'Unauthorized']);
            exit;
        }

        $id = intval($_POST['id'] ?? 0);
        $date = $_POST['date'] ?? '';
        $details = $_POST['details'] ?? '';
        $byhand = $_POST['byhand'] ?? '';
        $amount = $_POST['amount'] ?? '';
        $category = $_POST['category'] ?? '';

        if ($id <= 0 || empty($date) || empty($details) || $amount === '') {
            echo json_encode(['status'=>'error','message'=>'Missing required fields']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE expenditure_data SET category = ?, date = ?, details = ?, byhand = ?, amount = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ssssdi", $category, $date, $details, $byhand, $amount, $id);

            $ok = $stmt->execute();
            $stmt->close();
            if ($ok) echo json_encode(['status'=>'success','message'=>'Updated']);
            else echo json_encode(['status'=>'error','message'=>'Update failed']);
        } else {
            echo json_encode(['status'=>'error','message'=>'Prepare failed']);
        }
        exit;
    }

    // === DELETE record (POST) — MD only ===
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
        if ($role !== 'md') {
            echo json_encode(['status'=>'error','message'=>'Unauthorized']);
            exit;
        }
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status'=>'error','message'=>'Invalid id']);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM expenditure_data WHERE id = ?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) echo json_encode(['status'=>'success','message'=>'Deleted']);
        else echo json_encode(['status'=>'error','message'=>'Delete failed']);
        exit;
    }


    // === LOAD MONTHLY TOTALS ===
if (isset($_GET['action']) && $_GET['action'] === 'load_monthly') {
    $month = $_GET['month'] ?? date('Y-m');

    $totals = [
        'vehicle' => 0,
        'shop' => 0,
        'salaries' => 0,
        'other' => 0,
        'grand' => 0
    ];

    $cats = ['Vehicle','Shop','Salaries','Other'];
    foreach ($cats as $cat) {
        $stmt = $conn->prepare(
          "SELECT IFNULL(SUM(amount),0) 
           FROM expenditure_data 
           WHERE category=? AND DATE_FORMAT(date,'%Y-%m')=?"
        );
        $stmt->bind_param("ss", $cat, $month);
        $stmt->execute();
        $stmt->bind_result($sum);
        $stmt->fetch();
        $stmt->close();

        $totals[strtolower($cat)] = (float)$sum;
        $totals['grand'] += (float)$sum;
    }

    echo json_encode(['status'=>'success','totals'=>$totals]);
    exit;
}

    // fallback
    echo json_encode(['status'=>'error','message'=>'Unknown action']);
    exit;
}

// If not AJAX, render the page HTML below
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>💰 Expenditure</title>

  <!-- Use the same dashboard CSS file -->
  <link rel="stylesheet" href="sale_style.css" />

 <style>
/* ======================
   BASE
====================== */
body{
  font-family:Poppins,system-ui;
  background:#eef2f7;
  margin:0;
}

/* ======================
   NAV
====================== */
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
  font-family:'Merriweather',serif;
}

.nav a:hover{
  background:#0b4f85;
  transform:translateY(-1px);
}

/* logout */
.nav a[style]{
  background:#d32f2f !important;
}
.nav a[style]:hover{
  background:#b71c1c !important;
}

/* ======================
   CONTAINER
====================== */
.container{
  max-width:1100px;
  margin:20px auto;
  padding:20px;
  background:#fff;
  border-radius:12px;
}

/* ======================
   HEADINGS
====================== */
h1,h3{
  color:#1e88e5;
  font-weight:900;
}

/* ======================
   CARD GRID
====================== */
.cards,
.summary-bar{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
  gap:14px;
  margin:20px 0;
}

/* ======================
   CARD BASE
====================== */
/* ===== FORCE CARDS IN ONE ROW ===== */
.summary-bar,
.cards{
  display:grid;
  grid-template-columns: repeat(5, 1fr); /* 👈 always 5 in a row */
  gap:16px;
  margin:20px 0;
}

/* Card base */
.summary-bar div,
.card{
  padding:20px;
  border-radius:14px;
  color:#fff;
  font-weight:800;
  text-align:center;
  box-shadow:0 12px 30px rgba(0,0,0,.12);
}

/* ======================
   CARD COLORS
====================== */
.c-blue{background:#0072ff}
.c-green{background:#00b09b}
.c-orange{background:#fb8c00}
.c-purple{background:#7b1fa2}
.c-red{background:#ff512f}

/* auto colors for expenditure cards */
.summary-bar div:nth-child(1){background:#0072ff}
.summary-bar div:nth-child(2){background:#00b09b}
.summary-bar div:nth-child(3){background:#fb8c00}
.summary-bar div:nth-child(4){background:#7b1fa2}
.summary-bar div:nth-child(5){background:#ff512f}

/* ======================
   MONTH / STOCK BAR
====================== */
.stock-bar,
.month-bar{
  background:linear-gradient(90deg,#0072ff,#00c6ff);
  color:#fff;
  padding:16px;
  border-radius:12px;
  font-weight:800;
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:20px;
  gap:10px;
  flex-wrap:wrap;
}

/* ======================
   FORM
====================== */
.form-section{
  background:linear-gradient(90deg,#0072ff,#00c6ff);
  padding:16px;
  border-radius:12px;
  display:flex;
  gap:12px;
  flex-wrap:wrap;
  justify-content:center;
}

.form-section input,
.form-section select{
  padding:8px 12px;
  border-radius:6px;
  border:none;
  font-weight:bold;
}

/* ======================
   BUTTONS
====================== */
.button-group{
  display:flex;
  gap:12px;
  justify-content:center;
  flex-wrap:wrap;
}

.button-group button,
form button{
  padding:8px 14px;
  border-radius:6px;
  border:none;
  background:#1e88e5;
  color:#fff;
  font-weight:800;
  cursor:pointer;
}

.button-group button:hover,
form button:hover{
  opacity:.9;
}

/* ======================
   TABLE
====================== */
table{
  width:100%;
  border-collapse:collapse;
  background:#fff;
  border-radius:12px;
  overflow:hidden;
}

th{
  background:#1e88e5;
  color:#fff;
  padding:12px;
}

td{
  padding:10px;
  border-bottom:1px solid #e5e7eb;
}

tr:hover{
  background:#f1f5ff;
}

/* ======================
   ACTION BUTTONS
====================== */
.act-btn{
  padding:6px 10px;
  border-radius:6px;
  border:none;
  font-weight:800;
  cursor:pointer;
  color:#fff;
}

.act-edit{background:#0072ff}
.act-delete{background:#ff512f}

/* ======================
   COLLAPSIBLE PANEL
====================== */
#recordPanel{
  overflow:hidden;
  max-height:0;
  opacity:0;
  transition:.4s ease;
}
#recordPanel.show{
  max-height:3000px;
  opacity:1;
}

/* Mobile fallback */
@media(max-width:900px){
  .summary-bar,
  .cards{
    grid-template-columns: repeat(2, 1fr);
  }
}
@media(max-width:500px){
  .summary-bar,
  .cards{
    grid-template-columns: 1fr;
  }
}

/* ===== EXACT SIMPLE FILTER ===== */
.month-filter{
  display:flex;
  align-items:center;
  gap:14px;
  margin:10px 0 20px;
}

.month-filter label{
  font-size:20px;
  font-weight:900;
  color:#000;
  display:flex;
  align-items:center;
  gap:8px;
}

.month-filter input[type="month"]{
  padding:6px 10px;
  font-size:16px;
  border:2px solid #000;
  border-radius:4px;
  background:#fff;
  color:#000;
  font-weight:600;
}

.month-filter button{
  padding:10px 18px;
  border:none;
  border-radius:10px;
  background:#1e88e5;
  color:#fff;
  font-size:16px;
  font-weight:800;
  cursor:pointer;
}

.month-filter button:hover{
  opacity:.9;
}

/* mobile safe */
@media(max-width:600px){
  .month-filter{
    flex-wrap:wrap;
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
  <a href="stock_records.php">Stock Record</a>
  
  <a href="daily_guaranty.php">Guaranty</a>
  <a href="daily_closing.php">Daily Sumary</a>
  <?php if($_SESSION['role']==='md'): ?><a href="cash_in_hand.php">Cash in Hand</a><?php endif; ?>
  <?php if($_SESSION['role']==='md'): ?><a href="profit.php">Profit</a><?php endif; ?>
  <?php if($_SESSION['role']==='md'): ?><a href="assets.php">Assets</a><?php endif; ?>
  
  <a href="image_notes.php">Image Notes</a>
  <a href="checklist.php">Check List</a>
  
</div>

<main class="container">

  <!-- HEADER -->
  <header class="table-header">
    <div class="header-row">
      <div class="logo" style="font-size:32px;"></div>
      <h1>Expenditure</h1>
    </div>
  </header>

<div class="month-filter">
  <label>📅 Select Month:</label>
  <input type="month" id="monthPicker" value="<?php echo date('Y-m'); ?>">
  <button id="loadMonth">Load Month</button>
</div>


<!-- 🔹 MONTHLY SUMMARY -->
<section class="summary-bar" id="monthlySummary">
  <div>Month Total <br><span id="mGrand">Rs. 0</span></div>
  <div>Vehicle <br><span id="mVehicle">Rs. 0</span></div>
  <div>Shop <br> <span id="mShop">Rs. 0</span></div>
  <div>Salaries <br> <span id="mSalaries">Rs. 0</span></div>
  <div>Other <br> <span id="mOther">Rs. 0</span></div>
</section>

<hr style="margin:25px 0;">

<br>
<!-- SUMMARY -->
    <section class="summary-bar">
      <div>Total Expenditure <br> <span id="grandTotal">Rs. 0.00</span></div>
      <div>Vehicle <br> <span id="totalVehicle">Rs. 0.00</span></div>
      <div>Shop <br> <span id="totalShop">Rs. 0.00</span></div>
      <div>Salaries <br> <span id="totalSalaries">Rs. 0.00</span></div>
      <div>Other <br> <span id="totalOther">Rs. 0.00</span></div>
    </section><br><br>
  <!-- FORM -->
  <section class="form-section" style="background: linear-gradient(90deg, #1e88e5, #00c6ff);">
    <select id="filterType">
      <option value="ShowAll">All Record</option>
      <option value="Vehicle">Vehicle</option>
      <option value="Shop">Shop</option>
      <option value="Salaries">Salaries</option>
      <option value="Other">Other</option>
    </select><br><br>

    <input id="date" type="date" value="<?php echo date('Y-m-d'); ?>" />
    <input id="details" placeholder="Details" />
    <input id="byhand" placeholder="By Hand" />
    <input id="amount" type="number" placeholder="0.00" />
  </section>

<br>

  <!-- BUTTONS -->
  <section class="button-group">
    <button id="saveData">💾 Save</button>
    <button id="loadData">📂 Load</button>
    <button id="excelBtn">📊 Excel</button>
    <button id="pdfBtn">📑 PDF</button>

    <!-- ✅ COLLAPSE / EXPAND BUTTON -->
    <button id="toggleRecords">📂 Show Records</button>
  </section>

<br>

  <!-- ✅ COLLAPSIBLE RECORDS -->
  <div id="recordPanel">

    <!-- TABLE -->
    <section>
      <table id="dataTable">
        <thead>
          <tr>
            <th>Category</th>
            <th>Date</th>
            <th>Details</th>
            <th>By Hand</th>
            <th>Amount</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="tableBody"></tbody>
      </table>
    </section>

  </div>
  <!-- ✅ END COLLAPSIBLE RECORDS -->

</main>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
/* ========== EXCEL EXPORT ========== */
document.getElementById("excelBtn").addEventListener("click", function () {
  const table = document.getElementById("dataTable");
  const workbook = XLSX.utils.book_new();
  const worksheet = XLSX.utils.table_to_sheet(table);
  XLSX.utils.book_append_sheet(workbook, worksheet, "Expenditure");
  XLSX.writeFile(workbook, "expenditure_records.xlsx");
});

/* ========== PDF EXPORT ========== */
document.getElementById("pdfBtn").addEventListener("click", function () {
  window.print();
});

document.addEventListener("DOMContentLoaded", () => {

  const saveBtn = document.getElementById("saveData");
  const loadBtn = document.getElementById("loadData");
  const tbody   = document.getElementById("tableBody");
  const filter  = document.getElementById("filterType");

  console.log("✅ Expenditure script loaded");

  /* ---------- CLEAR INPUTS ---------- */
  function clearInputs() {
    document.getElementById("details").value = "";
    document.getElementById("byhand").value = "";
    document.getElementById("amount").value = "";
  }

  /* ---------- SAVE DATA ---------- */
  saveBtn.onclick = async (e) => {
    e.preventDefault();

    const fd = new FormData();
    fd.append("action", "save");
    fd.append("category", filter.value);
    fd.append("date", document.getElementById("date").value);
    fd.append("details", document.getElementById("details").value);
    fd.append("byhand", document.getElementById("byhand").value);
    fd.append("amount", document.getElementById("amount").value);

    if (!fd.get("date") || !fd.get("details") || !fd.get("amount")) {
      alert("⚠️ Please fill all required fields");
      return;
    }

    const res  = await fetch(window.location.pathname, { method:"POST", body: fd });
    const json = await res.json();

    if (json.status === "success") {
      alert("✅ Record saved");
      clearInputs();
      loadData();
      loadMonthly();
    } else {
      alert("❌ " + (json.message || "Save failed"));
    }
  };

  /* ---------- LOAD TABLE ---------- */
  loadBtn.onclick = () => loadData();

  async function loadData() {
    const res  = await fetch(
      window.location.pathname + "?action=load&type=" + encodeURIComponent(filter.value)
    );
    const data = await res.json();

    tbody.innerHTML = "";

    data.data.forEach(row => {
      const isMd = <?= json_encode($role === 'md') ?>;
      const actionHtml = isMd
        ? `<button class="act-btn act-edit"
             onclick="openEditFromRow(${row.id}, '${row.date}', '${escapeJs(row.details)}',
             '${escapeJs(row.byhand)}', ${Number(row.amount)}, '${row.category.replace("'", "\\'")}')">
             ✏ Edit</button>
           <button class="act-btn act-delete" onclick="deleteRecord(${row.id})">🗑 Delete</button>`
        : '<span style="color:#777;font-size:12px">—</span>';

      tbody.innerHTML += `
        <tr>
          <td>${escapeHtml(row.category)}</td>
          <td>${row.date}</td>
          <td>${escapeHtml(row.details)}</td>
          <td>${escapeHtml(row.byhand)}</td>
          <td>${Number(row.amount).toLocaleString()}</td>
          <td>${actionHtml}</td>
        </tr>`;
    });

    if (data.totals) {
      document.getElementById("grandTotal").innerText =
        "Rs. " + data.totals.grand.toLocaleString();
      document.getElementById("totalVehicle").innerText =
        "Rs. " + data.totals.vehicle.toLocaleString();
      document.getElementById("totalShop").innerText =
        "Rs. " + data.totals.shop.toLocaleString();
      document.getElementById("totalSalaries").innerText =
        "Rs. " + data.totals.salaries.toLocaleString();
      document.getElementById("totalOther").innerText =
        "Rs. " + data.totals.other.toLocaleString();
    }
  }

  /* ---------- MONTHLY TOTALS ---------- */
  document.getElementById("loadMonth").onclick = loadMonthly;

  async function loadMonthly() {
    const month = document.getElementById("monthPicker").value;
    const res   = await fetch(
      window.location.pathname + "?action=load_monthly&month=" + month
    );
    const json  = await res.json();

    if (json.status === "success") {
      document.getElementById("mGrand").innerText =
        "Rs. " + json.totals.grand.toLocaleString();
      document.getElementById("mVehicle").innerText =
        "Rs. " + json.totals.vehicle.toLocaleString();
      document.getElementById("mShop").innerText =
        "Rs. " + json.totals.shop.toLocaleString();
      document.getElementById("mSalaries").innerText =
        "Rs. " + json.totals.salaries.toLocaleString();
      document.getElementById("mOther").innerText =
        "Rs. " + json.totals.other.toLocaleString();
    }
  }

  /* ---------- HELPERS ---------- */
  function escapeHtml(s) {
    return String(s || "")
      .replace(/&/g,"&amp;").replace(/</g,"&lt;")
      .replace(/>/g,"&gt;").replace(/"/g,"&quot;");
  }

  function escapeJs(s) {
    return String(s || "")
      .replace(/\\/g,"\\\\").replace(/'/g,"\\'")
      .replace(/\n/g,"\\n").replace(/\r/g,"");
  }

  window.escapeHtml = escapeHtml;
  window.escapeJs   = escapeJs;

  /* ---------- INITIAL LOAD ---------- */
  loadData();
  loadMonthly();

});

/* ---------- TOGGLE RECORD PANEL ---------- */
document.getElementById("toggleRecords").onclick = function () {
  const panel = document.getElementById("recordPanel");
  panel.classList.toggle("show");
  this.innerText = panel.classList.contains("show")
    ? "📁 Hide Records"
    : "📂 Show Records";
};
</script>

</body>
</html>


