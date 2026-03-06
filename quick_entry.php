<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit;
}
include 'db_connect.php';


$action = $_GET['action'] ?? null;

if ($action !== null) {
    header('Content-Type: application/json; charset=utf-8');
}

/* ================= LOW STOCK LOGIC ================= */

$lowLimit12 = 10;   // 🔧 set your own limit
$lowLimit15 = 10;
$lowLimit45 = 5;

$q = $conn->query("
    SELECT 
        IFNULL(SUM(kg12),0) AS s12,
        IFNULL(SUM(kg15),0) AS s15,
        IFNULL(SUM(kg45),0) AS s45
    FROM stock_data
");

$r = $q->fetch_assoc();

$stock12 = (int)$r['s12'];
$stock15 = (int)$r['s15'];
$stock45 = (int)$r['s45'];

$showLowStock = (
    $stock12 <= $lowLimit12 ||
    $stock15 <= $lowLimit15 ||
    $stock45 <= $lowLimit45
);

/* ================= CUSTOMER AGGREGATE ================= */
if ($action === 'customer_agg_all') {
    $res = $conn->query("
SELECT
    name,
    CASE
WHEN COUNT(
        CASE 
        WHEN TRIM(LOWER(details)) <> 'no'
        AND TRIM(details) <> ''
        THEN 1 END
     ) > 0

THEN GROUP_CONCAT(
        CASE
        WHEN TRIM(LOWER(details)) <> 'no'
        AND TRIM(details) <> ''
        THEN details
        END
        SEPARATOR ' | '
     )

ELSE 'No'

END AS details,



    SUM(kg12) kg12,
    SUM(kg15) kg15,
    SUM(kg45) kg45,

    SUM(total) total,
    SUM(receivedAmount) received,
    SUM(amountRemaining) remaining,

    SUM(kg12Received) kg12Received,
    SUM(kg15Received) kg15Received,
    SUM(kg45Received) kg45Received,

    (
      SUM(kg12)+SUM(kg15)+SUM(kg45)
      -
      (SUM(kg12Received)+SUM(kg15Received)+SUM(kg45Received))
    ) totalCylinderRemaining

FROM sale_details
GROUP BY name
ORDER BY name
");

    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
}

/* ================= CUSTOMER HISTORY ================= */
if ($action === 'customer_history' && !empty($_GET['name'])) {
    $stmt = $conn->prepare("
        SELECT
    DATE_FORMAT(date,'%Y-%m-%d') date,
    details,

    kg12, kg15, kg45,
    total, receivedAmount, amountRemaining,

    kg12Received,
    kg15Received,
    kg45Received,

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

/* ================= STOCK SUMMARY ================= */

$qStock = $conn->query("
    SELECT
        IFNULL(SUM(kg12),0) AS r12,
        IFNULL(SUM(kg15),0) AS r15,
        IFNULL(SUM(kg45),0) AS r45,
        IFNULL(SUM(totalAmount),0) AS stockValue
    FROM stock_data
");

$stockRow = $qStock->fetch_assoc();

$remaining12 = (float)$stockRow['r12'];
$remaining15 = (float)$stockRow['r15'];
$remaining45 = (float)$stockRow['r45'];

$stockValue  = (float)$stockRow['stockValue'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Daily Entry — Sale</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- LIBS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

<style>
body{font-family:Poppins,system-ui;background:#eef2f7;margin:0}
.container{max-width:1300px;margin:20px auto;background:#fff;border-radius:14px;padding:20px;box-shadow:0 20px 50px rgba(0,0,0,.15)}

.section-header{background:#1e88e5;color:#fff;padding:14px 18px;border-radius:12px;display:flex;justify-content:space-between;align-items:center;cursor:pointer}
.section-header h2{margin:0;font-size:20px}
.section-body{padding:18px}

.grid{display:grid;grid-template-columns:repeat(5,minmax(140px,1fr));gap:12px;display: flex;
  gap: 15px;flex-wrap: wrap;}
input{padding:10px;border-radius:10px;border:1px solid #cbd5e1;width:100%;flex: 1;min-width: 200px;}

.btn{padding:8px 14px;border:none;border-radius:6px;font-weight:700;cursor:pointer;background:#1e88e5;color:#fff}
.save{background:#4CAF50}
.excel{background:#2196f3}
.pdf{background:#ff9800}
.btn.blue{background:#1e88e5}
.btn.green{background:#4CAF50}

.calc-box{margin-top:20px;padding:20px;border-radius:16px;background:linear-gradient(135deg,#00b09b,#96c93d);color:#fff}
.calc-grid{display:grid;grid-template-columns:repeat(5,minmax(160px,1fr));gap:12px;display: flex;gap: 15px;flex-wrap: wrap;}
.calc-grid input{background:#fff;flex: 1;min-width: 200px;}

.rate-card{margin-top:14px;padding:20px;border-radius:16px;background:linear-gradient(135deg,#11998e,#38ef7d);font-size:22px;font-weight:900}
.hidden{display:none}

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

@media(max-width:768px){.nav{padding:6px;gap:6px}.nav a{padding:6px 8px;font-size:13px}}

.stock-bar{background:#f59e0b;color:#fff;padding:14px 20px;border-radius:12px;font-weight:900;font-size:16px;margin-bottom:15px;box-shadow:0 6px 18px rgba(0,0,0,.2);display:flex;align-items:center;gap:10px}

.customer-panel{background:#fff;padding:20px;border-radius:12px;margin-top:40px}
.cust-top{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px}
.cust-top input{padding:8px 10px;border-radius:6px;border:1px solid #ccc}
.hint{font-weight:800;color:#333}
.cust-actions{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:10px}
.export-btns{display:flex;gap:6px}

.customer-table{width:100%;border-collapse:collapse}
.customer-table th{background:#1e88e5;color:#fff;padding:10px}
.customer-table td{padding:10px;border-bottom:1px solid #ddd}
.customer-table tr:hover{background:#e3f2fd}

table{width:100%;border-collapse:collapse}
th,td{padding:8px;border-bottom:1px solid #ddd;text-align:right}
th:first-child,td:first-child{text-align:left}
th{background:#1e88e5;color:#fff}

.modal{
  background:#fff;
  padding:20px;
  border-radius:10px;
  width:95%;
  max-width:1100px;

  position:relative;
  z-index:10000;
}


.modal-root{
  position:fixed;
  inset:0;
  display:none;
  background:rgba(0,0,0,.5);
  justify-content:center;
  align-items:center;

  z-index:9999;
}

.modal{background:#fff;padding:20px;border-radius:10px;width:95%;max-width:1100px}
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

.total-row{background:#4CAF50;color:#fff;font-weight:900}

.stock-bar{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:20px;

  background:linear-gradient(90deg,#0f74e6,#11b0e8);
  color:#fff;

  padding:14px 22px;
  border-radius:14px;

  font-weight:600;
  font-size:18px;

  box-shadow:0 6px 15px rgba(0,0,0,.12);
}

.stock-left{
  display:flex;
  align-items:center;
  gap:6px;
  font-size:18px;
}

.stock-center{
  flex:1;
  text-align:center;
  font-size:20px;
  font-weight:700;
}

.stock-right{
  text-align:right;
  font-size:20px;
  font-weight:800;
}

/* Mobile */
@media(max-width:768px){
  .stock-bar{
    flex-direction:column;
    text-align:center;
    gap:6px;
  }

  .stock-center,
  .stock-right{
    font-size:16px;
  }
}

#customerListBody tr:first-child{
   position: sticky;
   top: 42px;
   background:#4CAF50;
   z-index:2;
}

#customerTableBox{
    max-height: 500px;
    overflow: auto;
}

#customerTableAgg thead th{
    position: sticky;
    top: 0;
    z-index: 5;
    background: #1e88e5;
    color: #fff;
}

#customerListBody tr:first-child{
    position: sticky;
    top: 42px;   /* height of header */
    background: #4CAF50;
    color:#fff;
    z-index: 4;
}

.suggestion-box{
    position:absolute;
    background:#fff;
    border:1px solid #ccc;
    width:100%;
    max-height:200px;
    overflow:auto;
    z-index:9999;
}

.suggestion-item{
    padding:8px;
    cursor:pointer;
}

.suggestion-item:hover{
    background:#e3f2fd;
}

</style>
</head>

<body>

  <div class="nav">
  <a href="insaf_home.php">Dashboard</a>
 
  <a href="daily_parchoon.php">Parchoon</a>
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

<!-- ================= SALE SECTION ================= -->

<div class="stock-bar">

  <div class="stock-left">
    ✔ Available Stock
  </div>

  <div class="stock-center">
    12KG ➤ <?=number_format((float)($remaining12 ?? 0))?>
    |
    15KG ➤ <?=number_format((float)($remaining15 ?? 0))?>
    |
    45KG ➤ <?=number_format((float)($remaining45 ?? 0))?>
  </div>

  <div class="stock-right">
    💰 Rs <?=number_format((float)($stockValue ?? 0),2)?>
  </div>

</div>



<div class="section-header" onclick="toggleSale()">
    <h2>🛒 SALE</h2>
    <span id="saleArrow">▼</span>
</div>

<div id="saleBody" class="section-body">

<button id="toggleDailyCalc" class="btn excel">▼ Daily Calculator</button>

<!-- ===== DAILY CALCULATOR ===== -->
<div id="dailyCalcBox" class="calc-box">
    <div><h2 style="color:yellow">First Enter Today Rate</h2></div>
    <div class="calc-grid">
        <input id="pc_date" type="date" value="<?=date('Y-m-d')?>">
        <input id="pc_baseRate" placeholder="Base Rate (Auto)" readonly>
        <input id="pc_base12" placeholder="Base 12KG (Auto)" readonly>
        <input id="pc_base15" placeholder="Base 15KG (Auto)" readonly>
        <input id="pc_base45" placeholder="Base 45KG (Auto)" readonly>
        <input id="pc_todayRate" placeholder="Today Sale Rate">
    </div>

    <button id="saveRateBtn" class="btn save" style="margin-top:14px">
        🔒 Save Rate
    </button>

    <div class="rate-card">
        Today Sale Rate<br>
        <span id="todayRateText">Not Set</span>
    </div>
</div>

<br>

<!-- ===== SALE INPUTS ===== -->
<div class="grid">

    <input id="sale_name" placeholder="Customer Name">
	<div id="nameSuggestions" 
     style="position:relative;">
    </div>
    <input id="sale_date" type="date" value="<?=date('Y-m-d')?>">

    <input id="kg12" type="number" placeholder="12 KG Qty">
    <input id="kg15" type="number" placeholder="15 KG Qty">
    <input id="kg45" type="number" placeholder="45 KG Qty">

    <input id="total" type="number" placeholder="Total Amount">
    <input id="receivedAmount" type="number" placeholder="Received Amount">

    <input id="amountRemaining" placeholder="Amount Remaining" readonly>

    <input id="kg12Received" type="number" placeholder="12KG Rec or Guaranty">
    <input id="kg15Received" type="number" placeholder="15KG Rec or Guaranty">
    <input id="kg45Received" type="number" placeholder="45KG Rec or Guaranty">

    <input id="totalCylinderRemaining" placeholder="Cylinder Remaining" readonly>
    <input id="pc_margin" placeholder="Margin (Auto)" readonly>
	<input type="text" id="sale_details" placeholder="Details / Guaranty / Notes">

</div>

<br>

<button id="saveBtn" class="btn save">💾 Save</button>

<!-- ===== SUCCESS MESSAGE ===== -->
<div id="saleMsg"
     style="display:none;
            margin-top:12px;
            padding:12px;
            border-radius:8px;
            background:#e8f5e9;
            color:#2e7d32;
            font-weight:700;">
</div>

<!-- ===== TEMPORARY ROW ===== -->
<table style="width:100%;margin-top:16px;border-collapse:collapse">
  <thead>
    <tr style="background:#1e88e5;color:#fff">
      
      <th>Name</th>
	  <th>Details</th>
      <th>Date</th>

      <th>12KG</th>
      <th>15KG</th>
      <th>45KG</th>

      <th>12KG Rec</th>
      <th>15KG Rec</th>
      <th>45KG Rec</th>

      <th>Total</th>
      <th>Received</th>
      <th>Remaining</th>

      <th>Cyl Rem</th>
    </tr>
  </thead>
  <tbody id="tempRows"></tbody>
</table>

<!-- ================= CUSTOMER PANEL ================= -->
<div class="customer-panel">

  <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
    <input id="custListSearch" placeholder="🔍 Search customer...">
    <button id="custRefresh" class="btn">Refresh</button>
    
  </div>

  <div style="display:flex;justify-content:space-between;margin-bottom:10px">
    <button id="toggleCustomerTable" class="btn">🔽 Collapse</button>
	<span style="font-size:30px">Click on Customer Name for Full History</span>
    <div>
      <button id="exportExcelCustomers" class="btn">📊 Excel</button>
      <button id="exportPdfCustomers" class="btn">📄 PDF</button>
    </div>
  </div>

  <div id="customerTableBox">
    <table id="customerTableAgg">
      <thead>
        <tr>
          <th>Customer</th>
		  <th>Details</th>
          <th>12KG</th><th>15KG</th><th>45KG</th>
          <th>Total</th><th>Received</th><th>Remaining</th>
          <th>12KG Rec</th><th>15KG Rec</th><th>45KG Rec</th>
          <th>Total Cyl</th><th>Total Amt</th>
        </tr>
      </thead>
      <tbody id="customerListBody"></tbody>
    </table>
  </div>

</div>

<!-- ================= MODAL ================= -->
<div class="modal-root" id="historyModal">
  <div class="modal">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:10px">
  
  <h2 id="modalTitle" style="margin:0">History</h2>

  <div style="display:flex;gap:8px">
    <button id="historyPdfBtn" class="btn">PDF</button>
    <button id="historyExcelBtn" class="btn">Excel</button>
    <button id="closeHistoryBtn" class="btn">Close</button>
  </div>

</div>

    <div id="modalBody" class="history-scroll"></div>

  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
const API = 'quick_entry.php';

const customerListBody = document.getElementById("customerListBody");
const custListSearch = document.getElementById("custListSearch");
const custRefresh = document.getElementById("custRefresh");

/* LOAD CUSTOMER LIST */
async function loadCustomerAggregates(){

    customerListBody.innerHTML='<tr><td colspan="12">Loading...</td></tr>';

    const res = await fetch(API+'?action=customer_agg_all');
    const rows = await res.json();

    customerListBody.innerHTML = '';

/* ===== TOTAL VARIABLES ===== */
let t12=0, t15=0, t45=0;
let tTotal=0, tReceived=0, tRemaining=0;
let t12Rec=0, t15Rec=0, t45Rec=0;
let tCyl=0;

/* FIRST LOOP → calculate totals only */
rows.forEach(v=>{
    t12 += Number(v.kg12)||0;
    t15 += Number(v.kg15)||0;
    t45 += Number(v.kg45)||0;

    tTotal += Number(v.total)||0;
    tReceived += Number(v.received)||0;
    tRemaining += Number(v.remaining)||0;

    t12Rec += Number(v.kg12Received)||0;
    t15Rec += Number(v.kg15Received)||0;
    t45Rec += Number(v.kg45Received)||0;

    tCyl += Number(v.totalCylinderRemaining)||0;
});

/* ===== TOTAL ROW FIRST ===== */
const totalRow = document.createElement('tr');
totalRow.style.background = "#4CAF50";
totalRow.style.color = "#fff";
totalRow.style.fontWeight = "900";

totalRow.innerHTML = `
    <td><b>TOTAL</b></td>
	<td>Details</td>
    <td>${t12}</td>
    <td>${t15}</td>
    <td>${t45}</td>
    <td>${tTotal.toLocaleString()}</td>
    <td>${tReceived.toLocaleString()}</td>
    <td>${tRemaining.toLocaleString()}</td>
    <td>${t12Rec}</td>
    <td>${t15Rec}</td>
    <td>${t45Rec}</td>
    <td>${tCyl}</td>
    <td>${tRemaining.toLocaleString()}</td>
`;

customerListBody.appendChild(totalRow);

/* SECOND LOOP → normal rows */
rows.forEach(v=>{
    const tr=document.createElement('tr');
    tr.innerHTML=`
      <td style="color:#1e88e5;cursor:pointer">${v.name}</td>
      <td>${v.details ?? ''}</td>

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

    tr.querySelector("td").onclick =
        () => openHistoryModal(v.name);

    customerListBody.appendChild(tr);
});

}

/* SEARCH */
custListSearch.oninput=()=>{
    const q=custListSearch.value.toLowerCase();
    document.querySelectorAll("#customerListBody tr").forEach(tr=>{
        tr.style.display=tr.innerText.toLowerCase().includes(q)?'':'none';
    });
};

custRefresh.onclick=()=>{custListSearch.value='';loadCustomerAggregates();};

/* MODAL */
const modal = document.getElementById("historyModal");
const modalTitle = document.getElementById("modalTitle");
const modalBody = document.getElementById("modalBody");

const historyExcelBtn = document.getElementById("historyExcelBtn");
const historyPdfBtn = document.getElementById("historyPdfBtn");

const exportExcelCustomers = document.getElementById("exportExcelCustomers");
const exportPdfCustomers = document.getElementById("exportPdfCustomers");


document.getElementById("closeHistoryBtn").onclick = () => {
    modal.style.display = "none";
};

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
	let totalDetails = [];


    rows.forEach(r=>{
		const det = (r.details || '').trim().toLowerCase();

        if(det && det !== 'no'){
            totalDetails.push(r.details.trim());
        }

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
	
	const totalDetailsText =
    totalDetails.length
    ? totalDetails.join(' | ')
    : '';


    // ================== BUILD TABLE ==================
    let html = `
    <table>
      <thead>
<tr>
<th>Date</th>
<th>Details</th>   <!-- ✅ ADD THIS -->
<th>12KG</th>
<th>15KG</th>
<th>45KG</th>
<th>Total</th>
<th>Received</th>
<th>Remaining</th>
<th>12KG Rec</th>
<th>15KG Rec</th>
<th>45KG Rec</th>
<th>Total Cyl</th>
<th>Total Amt</th>
</tr>
</thead>

      <tbody>

      <tr class="total-row">
        <td><b>TOTAL</b></td>
        <td>${totalDetailsText}</td>


        <td>${t12}</td>
        <td>${t15}</td>
        <td>${t45}</td>
        <td>Rs ${tTotal.toLocaleString()}</td>
        <td>Rs ${tReceived.toLocaleString()}</td>
        <td>Rs ${tRemaining.toLocaleString()}</td>
        <td>${t12Rec}</td>
        <td>${t15Rec}</td>
        <td>${t45Rec}</td>
        <td>${tCyl}</td>
        <td>Rs ${runningBalance.toLocaleString()}</td>
      </tr>
    `;

    // ================== ROWS ==================
    let rb = 0;        // running amount balance
let rcyl = 0;     // ✅ running cylinder balance

rows.forEach(r=>{
    const rem = Number(r.amountRemaining) || 0;
    const cyl = Number(r.totalCylinderRemaining) || 0;

    rb += rem;
    rcyl += cyl;   // ✅ accumulate cylinders

    html += `
    <tr>
      <td>${r.date}</td>
	  <td>${r.details ?? ''}</td>
      <td>${r.kg12}</td>
      <td>${r.kg15}</td>
      <td>${r.kg45}</td>
      <td>Rs ${Number(r.total).toLocaleString()}</td>
      <td>Rs ${Number(r.receivedAmount).toLocaleString()}</td>
      <td>Rs ${rem.toLocaleString()}</td>
      <td>${r.kg12Received}</td>
      <td>${r.kg15Received}</td>
      <td>${r.kg45Received}</td>
      <td>${rcyl}</td>   <!-- ✅ CUMULATIVE -->
      <td>Rs ${rb.toLocaleString()}</td>
    </tr>
    `;
});


    html += "</tbody></table>";

    modalBody.innerHTML = html;
}


/* Excel */
historyExcelBtn.onclick = () => {
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(
        wb,
        XLSX.utils.table_to_sheet(modalBody.querySelector("table")),
        "History"
    );
    XLSX.writeFile(wb, "customer_history.xlsx");
};


/* COLLAPSE */
toggleCustomerTable.onclick=()=>{
    customerTableBox.style.display=
        customerTableBox.style.display==='none'?'':'none';
};

/* INIT */
loadCustomerAggregates();

/* ================= EXCEL EXPORT ================= */
historyExcelBtn.onclick = () => {
    const table = modalBody.querySelector("table");
    if (!table) {
        alert("No data to export");
        return;
    }

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(table);
    XLSX.utils.book_append_sheet(wb, ws, "Customer History");

    XLSX.writeFile(wb, "customer_history.xlsx");
};


/* ================= PDF EXPORT ================= */
historyPdfBtn.onclick = async () => {
    const table = modalBody.querySelector("table");
    if (!table) {
        alert("No data to export");
        return;
    }

    const canvas = await html2canvas(table, {
        scale: 2,
        useCORS: true
    });

    const imgData = canvas.toDataURL("image/png");

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF("l", "mm", "a4");

    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();

    const imgWidth = pageWidth - 10;
    const imgHeight = (canvas.height * imgWidth) / canvas.width;

    let y = 5;

    pdf.addImage(imgData, "PNG", 5, y, imgWidth, imgHeight);

    pdf.save("customer_history.pdf");
};

exportExcelCustomers.onclick = () => {
    const table = document.getElementById("customerTableAgg");

    if (!table) {
        alert("No table found");
        return;
    }

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(table);

    XLSX.utils.book_append_sheet(wb, ws, "Customer Summary");
    XLSX.writeFile(wb, "customer_summary.xlsx");
};

exportPdfCustomers.onclick = async () => {
    const table = document.getElementById("customerTableAgg");

    if (!table) {
        alert("No table found");
        return;
    }

    const canvas = await html2canvas(table, {
        scale: 2,
        useCORS: true
    });

    const imgData = canvas.toDataURL("image/png");

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF("l", "mm", "a4");

    const pageWidth = pdf.internal.pageSize.getWidth();
    const imgWidth = pageWidth - 10;
    const imgHeight = (canvas.height * imgWidth) / canvas.width;

    pdf.addImage(imgData, "PNG", 5, 10, imgWidth, imgHeight);
    pdf.save("customer_summary.pdf");
};


/* ================= TOGGLE SALE ================= */
function toggleSale(){
    const body = document.getElementById("saleBody");
    const arrow = document.getElementById("saleArrow");

    body.classList.toggle("hidden");
    arrow.textContent =
        body.classList.contains("hidden") ? "▶" : "▼";
}

/* ================= TOGGLE STOCK ================= */
function toggleStock(){
    const body = document.getElementById("stockBody");
    const arrow = document.getElementById("stockArrow");

    body.classList.toggle("hidden");
    arrow.textContent =
        body.classList.contains("hidden") ? "▶" : "▼";
}
</script>

<script>
document.addEventListener("DOMContentLoaded", function(){

/* ================= HELPERS ================= */
function n(v){ return Number(v) || 0; }

/* ================= ELEMENTS ================= */
const nameEl = document.getElementById("sale_name");
const detailsEl = document.getElementById("sale_details");

const dateEl = document.getElementById("sale_date");

const kg12 = document.getElementById("kg12");
const kg15 = document.getElementById("kg15");
const kg45 = document.getElementById("kg45");

const total = document.getElementById("total");
const receivedAmount = document.getElementById("receivedAmount");
const amountRemaining = document.getElementById("amountRemaining");

const kg12Received = document.getElementById("kg12Received");
const kg15Received = document.getElementById("kg15Received");
const kg45Received = document.getElementById("kg45Received");

const totalCylinderRemaining = document.getElementById("totalCylinderRemaining");
const pc_margin = document.getElementById("pc_margin");

/* ===== DAILY CALCULATOR ELEMENTS ===== */
const pc_date = document.getElementById("pc_date");
const pc_todayRate = document.getElementById("pc_todayRate");

const pc_baseRate = document.getElementById("pc_baseRate");
const pc_base12 = document.getElementById("pc_base12");
const pc_base15 = document.getElementById("pc_base15");
const pc_base45 = document.getElementById("pc_base45");

const todayRateText = document.getElementById("todayRateText");
const saveRateBtn = document.getElementById("saveRateBtn");

/* ===== UI ===== */
const saveBtn = document.getElementById("saveBtn");
const msgBox = document.getElementById("saleMsg");
const tempRows = document.getElementById("tempRows");

const toggleDailyCalc = document.getElementById("toggleDailyCalc");
const dailyCalcBox = document.getElementById("dailyCalcBox");

toggleDailyCalc.onclick = function(){
    dailyCalcBox.classList.toggle("hidden");
    toggleDailyCalc.textContent =
        dailyCalcBox.classList.contains("hidden")
        ? "▶ Daily Calculator"
        : "▼ Daily Calculator";
};


/* ================= INPUT RESTRICTIONS ================= */

function allowDigits(el){
    el.addEventListener("input", function(){
        this.value = this.value
            .replace(/[^0-9.]/g,'')
            .replace(/(\..*)\./g,'$1');
    });
}


function allowAlphabet(el){
    el.addEventListener("input", function(){
        this.value = this.value.replace(/[^a-zA-Z\s]/g,'');
    });
}

function allowAlphaNumeric(el){
    el.addEventListener("input", function(){
        this.value = this.value.replace(/[^a-zA-Z0-9\s.,-]/g,'');
    });
}


/* ================= EVENTS ================= */

[
 kg12,kg15,kg45,
 total,receivedAmount,
 kg12Received,kg15Received,kg45Received,
 pc_todayRate
].forEach(allowDigits);

allowAlphabet(nameEl);

allowAlphaNumeric(detailsEl);


pc_todayRate.addEventListener("input", profitCalc);
pc_date.addEventListener("change", loadTodayRate);

[
 kg12,kg15,kg45,
 total,receivedAmount,
 kg12Received,kg15Received,kg45Received
].forEach(el=>{
    el.addEventListener("input",()=>{ calc(); profitCalc(); });
});

/* ================= CALCULATIONS ================= */
function calc(){
    amountRemaining.value = (n(total.value) - n(receivedAmount.value)).toFixed(2);
    totalCylinderRemaining.value =
        (n(kg12.value)+n(kg15.value)+n(kg45.value)) -
        (n(kg12Received.value)+n(kg15Received.value)+n(kg45Received.value));
}

function profitCalc(){

    const rate = n(pc_todayRate.value);
    if(rate <= 0){
        pc_baseRate.value = "";
        pc_base12.value = "";
        pc_base15.value = "";
        pc_base45.value = "";
        pc_margin.value = "";
        todayRateText.innerText = "Not Set";
        return;
    }

    /* ===== BASE CALC ===== */
    const baseRate = rate / 11.8;

    const base12 = baseRate * 12;
    const base15 = baseRate * 15;
    const base45 = baseRate * 45.4;

    pc_baseRate.value = baseRate.toFixed(2);
    pc_base12.value = base12.toFixed(2);
    pc_base15.value = base15.toFixed(2);
    pc_base45.value = base45.toFixed(2);

    /* ===== MARGIN CALC (THIS WAS MISSING) ===== */
    const margin =
        n(total.value)
        - (n(kg12.value) * base12)
        - (n(kg15.value) * base15)
        - (n(kg45.value) * base45);

    pc_margin.value = margin.toFixed(2);

    todayRateText.innerText = "Rs " + rate;
}


/* ================= LOAD RATE ================= */
async function loadTodayRate(){
    try{
        const res = await fetch("get_daily_rate.php?date=" + pc_date.value);
        const j = await res.json();

        if(j.status === "ok" && n(j.rate) > 0){
            pc_todayRate.value = j.rate;
            pc_todayRate.readOnly = true;
            todayRateText.innerText = "Rs " + j.rate;
            profitCalc();
        }else{
            pc_todayRate.value = "";
            pc_todayRate.readOnly = false;
            todayRateText.innerText = "Not Set";
        }
    }catch(e){
        console.error("Rate load error", e);
    }
}

/* ================= SAVE RATE ================= */
saveRateBtn.onclick = async function(){

    const rate = n(pc_todayRate.value);
    if(rate <= 0){
        alert("Enter valid sale rate");
        return;
    }

    const fd = new FormData();
    fd.append("date", pc_date.value);
    fd.append("rate", rate);

    try{
        const res = await fetch("save_daily_rate.php",{
            method:"POST",
            body: fd
        });

        const j = await res.json();
        console.log("RATE SAVE RESPONSE:", j);

        /* ✅ MATCH BACKEND STATUS */
        if (j.status === "saved") {
            todayRateText.innerText = "Rs " + rate;
            pc_todayRate.readOnly = true;
            profitCalc();
            alert("✅ Rate saved successfully");
        }
        else if (j.status === "locked") {
            todayRateText.innerText = "Rs " + rate;
            pc_todayRate.readOnly = true;
            alert("⚠ Rate already saved for this date");
        }
        else {
            alert(j.msg || "Rate save failed");
        }

    }catch(e){
        console.error(e);
        alert("Cannot save rate");
    }
};


/* ================= SAVE SALE ================= */
saveBtn.onclick = async function(){
	
	// 🚫 DAILY RATE RESTRICTION
    if (Number(pc_todayRate.value) <= 0) {
        alert("⚠ Enter Today Rate first");
        pc_todayRate.focus();
        return;
    }
   
    if(!nameEl.value.trim()){
        alert("Customer name required");
        return;
    }

    profitCalc();

    const payload = {
        name: nameEl.value,
		details: detailsEl.value.trim() || "No",
        date: dateEl.value,
        kg12: n(kg12.value),
        kg15: n(kg15.value),
        kg45: n(kg45.value),
        total: n(total.value),
        receivedAmount: n(receivedAmount.value),
        amountRemaining: n(amountRemaining.value),
        kg12Received: n(kg12Received.value),
        kg15Received: n(kg15Received.value),
        kg45Received: n(kg45Received.value),
        totalCylinderRemaining: n(totalCylinderRemaining.value),
        margin: n(pc_margin.value)
    };

    try{
        await fetch("sale_save.php",{
            method:"POST",
            headers:{ "Content-Type":"application/json" },
            body: JSON.stringify(payload)
        });
    }catch(e){
        alert("Cannot reach server");
        return;
    }

    msgBox.innerText = "✅ Sale saved successfully";
    msgBox.style.display = "block";
    setTimeout(()=>msgBox.style.display="none",3000);

    const tr = document.createElement("tr");
    tr.style.background="#e8f5e9";
    tr.style.fontWeight="600";
    tr.innerHTML = `
        <td>${payload.name}</td>
		<td>${payload.details}</td>
        <td>${payload.date}</td>
        <td>${payload.kg12}</td>
        <td>${payload.kg15}</td>
        <td>${payload.kg45}</td>
        <td>${payload.kg12Received}</td>
        <td>${payload.kg15Received}</td>
        <td>${payload.kg45Received}</td>
        <td>Rs ${payload.total}</td>
        <td>Rs ${payload.receivedAmount}</td>
        <td>Rs ${payload.amountRemaining}</td>
        <td>${payload.totalCylinderRemaining}</td>
    `;
    tempRows.prepend(tr);
    setTimeout(()=>tr.remove(),10000);

    nameEl.value="";
	detailsEl.value="";
    kg12.value=kg15.value=kg45.value="";
    total.value=receivedAmount.value="";
    amountRemaining.value="";
    kg12Received.value=kg15Received.value=kg45Received.value="";
    totalCylinderRemaining.value="";
    pc_margin.value="";
};


/* ================= CUSTOMER NAME AUTOCOMPLETE ================= */

const suggestionBox = document.createElement("div");
suggestionBox.className = "suggestion-box";
suggestionBox.style.display = "none";

nameEl.parentNode.style.position = "relative";
nameEl.parentNode.appendChild(suggestionBox);

let customerNames = [];

async function loadCustomerNames(){

    const res = await fetch(API+'?action=customer_agg_all');

    const rows = await res.json();

    customerNames = rows.map(r => r.name);
}

loadCustomerNames();

nameEl.addEventListener("input", function(){

    const value = this.value.toLowerCase();

    suggestionBox.innerHTML = "";

    if(!value){
        suggestionBox.style.display = "none";
        return;
    }

    const filtered = customerNames.filter(n =>
        n.toLowerCase().includes(value)
    );

    if(filtered.length === 0){
        suggestionBox.style.display = "none";
        return;
    }

    filtered.forEach(name=>{

        const div = document.createElement("div");

        div.className = "suggestion-item";

        div.innerText = name;

        div.onclick = function(){

            nameEl.value = name;

            suggestionBox.style.display = "none";
        };

        suggestionBox.appendChild(div);
    });

    suggestionBox.style.display = "block";
});


document.addEventListener("click", function(e){

    if(e.target !== nameEl){

        suggestionBox.style.display = "none";
    }

});


/* ================= INIT ================= */
loadTodayRate();

});

</script>



<!-- ================= STOCK SECTION ================= -->
<div class="container" style="margin-top:20px">

<!-- ===== STOCK HEADER ===== -->
<div class="section-header" onclick="toggleStock()">
    <h2>🏭 STOCK</h2>
    <span id="stockArrow">▼</span>
</div>

<div id="stockBody" class="section-body">

<!-- ===== STOCK INPUT GRID ===== -->
<div class="grid" style="grid-template-columns:repeat(7,minmax(140px,1fr))">

    <input id="s_date" type="date" value="<?=date('Y-m-d')?>">

    <input id="plantName" placeholder="Plant Name">

    <input id="s_kg12" type="number" placeholder="12 KG Qty">
    <input id="s_kg15" type="number" placeholder="15 KG Qty">
    <input id="s_kg45" type="number" placeholder="45 KG Qty">

    <input id="s_rate" type="number" placeholder="Rate">

    <input id="s_baseRate" placeholder="Base Rate" readonly>

    <input id="s_cost12" placeholder="Cost 12KG" readonly>
    <input id="s_cost15" placeholder="Cost 15KG" readonly>
    <input id="s_cost45" placeholder="Cost 45KG" readonly>

    <input id="s_amount12" placeholder="12KG Amount" readonly>
    <input id="s_amount15" placeholder="15KG Amount" readonly>
    <input id="s_amount45" placeholder="45KG Amount" readonly>

    <input id="s_totalAmount" placeholder="Total Amount" readonly>

</div>

<br>

<!-- ===== STOCK BUTTON ===== -->
<div style="display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn save" onclick="saveStock()">💾 Save</button>
</div>

<!-- ===== TEMPORARY STOCK PREVIEW ===== -->
<div style="margin-top:15px;overflow:auto">
  <table width="100%" border="1" cellpadding="8" style="border-collapse:collapse">
    <thead>
      <tr style="background:#1e88e5;color:#fff;font-weight:bold">
        <th>Date</th>
        <th>Plant</th>
        <th>12KG</th>
        <th>15KG</th>
        <th>45KG</th>
        <th>Rate</th>
        <th>Base</th>
        <th>12KG Cost</th>
        <th>15KG Cost</th>
        <th>45KG Cost</th>
        <th>12KG Amt</th>
        <th>15KG Amt</th>
        <th>45KG Amt</th>
        <th>Total</th>
      </tr>
    </thead>
    <tbody id="stockTempRow"></tbody>
  </table>
</div>

</div>
</div>
<script>
function sn(v){ return Number(v) || 0; }

/* refs */
const s_date = document.getElementById('s_date');
const s_plantName = document.getElementById('plantName');

const s_kg12 = document.getElementById('s_kg12');
const s_kg15 = document.getElementById('s_kg15');
const s_kg45 = document.getElementById('s_kg45');
const s_rate = document.getElementById('s_rate');

const s_baseRate = document.getElementById('s_baseRate');
const s_cost12 = document.getElementById('s_cost12');
const s_cost15 = document.getElementById('s_cost15');
const s_cost45 = document.getElementById('s_cost45');

const s_amount12 = document.getElementById('s_amount12');
const s_amount15 = document.getElementById('s_amount15');
const s_amount45 = document.getElementById('s_amount45');
const s_totalAmount = document.getElementById('s_totalAmount');
const stockTempRow = document.getElementById('stockTempRow');

/* auto calc — SAME AS stock_form */
['s_rate','s_kg12','s_kg15','s_kg45'].forEach(id=>{
  document.getElementById(id).addEventListener('input', stockCalc);
});

function stockCalc(){
  const rate = sn(s_rate.value);
  if(rate <= 0) return;

  const base = rate / 11.8;
  s_baseRate.value = base.toFixed(2);

  const c12 = base*12, c15=base*15, c45=base*45.4;
  s_cost12.value = c12.toFixed(2);
  s_cost15.value = c15.toFixed(2);
  s_cost45.value = c45.toFixed(2);

  s_amount12.value = (c12*sn(s_kg12.value)).toFixed(2);
  s_amount15.value = (c15*sn(s_kg15.value)).toFixed(2);
  s_amount45.value = (c45*sn(s_kg45.value)).toFixed(2);

  s_totalAmount.value = (
    c12*sn(s_kg12.value)+
    c15*sn(s_kg15.value)+
    c45*sn(s_kg45.value)
  ).toFixed(2);
}

/* SAVE — EXACT SAME AS stock_form */
async function saveStock(){

  if(!s_plantName.value){
    alert("Enter Plant Name");
    return;
  }

  const payload = {
    date: s_date.value,
    plantName: s_plantName.value,

    kg12: sn(s_kg12.value),
    kg15: sn(s_kg15.value),
    kg45: sn(s_kg45.value),

    rate: sn(s_rate.value),
    baseRate: sn(s_baseRate.value),

    cost12: sn(s_cost12.value),
    cost15: sn(s_cost15.value),
    cost45: sn(s_cost45.value),

    amount12: sn(s_amount12.value),
    amount15: sn(s_amount15.value),
    amount45: sn(s_amount45.value),

    totalAmount: sn(s_totalAmount.value)
  };

  const res = await fetch('stock_save.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  });

  const j = await res.json();

  if(j.status === 'success'){
    showTempStock(payload);
    clearStock();
  } else {
    alert(j.message || "Save failed");
  }
}


function showTempStock(d){
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>${d.date}</td>
    <td>${d.plantName}</td>
    <td>${d.kg12}</td>
    <td>${d.kg15}</td>
    <td>${d.kg45}</td>
    <td>${d.rate}</td>
    <td>${d.baseRate}</td>
    <td>${d.cost12}</td>
    <td>${d.cost15}</td>
    <td>${d.cost45}</td>
    <td>${d.amount12}</td>
    <td>${d.amount15}</td>
    <td>${d.amount45}</td>
    <td><b>${d.totalAmount}</b></td>
  `;
  stockTempRow.appendChild(tr);
  setTimeout(()=>tr.remove(),20000);
}


function clearStock(){
  [
    s_plantName,s_kg12,s_kg15,s_kg45,s_rate,
    s_baseRate,s_cost12,s_cost15,s_cost45,
    s_amount12,s_amount15,s_amount45,s_totalAmount
  ].forEach(i=>i.value='');
}
</script>




<!-- ================= PARCHOON SECTION ================= -->
<div class="container" style="margin-top:20px">

<!-- ===== HEADER ===== -->
<div class="section-header" onclick="toggleParchoon()">
    <h2>🛒 PARCHOON</h2>
    <span id="parchoonArrow">▼</span>
</div>

<div id="parchoonBody" class="section-body">

<!-- ===== INPUTS ===== -->
<div class="grid" style="grid-template-columns:repeat(4,minmax(160px,1fr))">

    <input id="p_date" type="date" value="<?=date('Y-m-d')?>">

    <input id="p_rate" type="number" step="0.01" placeholder="Rate">

    <input id="p_kg" type="number" step="0.01" placeholder="KG">

    <input id="p_amount" type="number" step="0.01" placeholder="Total Amount">

</div>

<br>

<!-- ===== BUTTON ===== -->
<div style="display:flex;gap:10px">
    <button class="btn save" onclick="saveParchoon()">💾 Save</button>
</div>
<div id="parchoonMsg"
     style="display:none;
            margin-top:10px;
            padding:10px;
            border-radius:6px;
            background:#e8f5e9;
            color:#2e7d32;
            font-weight:bold;">
</div>

</div>
</div>
<script>
/* ================= PARCHOON LOGIC (FINAL + MESSAGE) ================= */

function pn(v){ return Number(v) || 0; }

/* ---------- REFS ---------- */
const p_date   = document.getElementById('p_date');
const p_rate   = document.getElementById('p_rate');
const p_kg     = document.getElementById('p_kg');
const p_amount = document.getElementById('p_amount');
const p_msg    = document.getElementById('parchoonMsg');

const parchoonBody  = document.getElementById('parchoonBody');
const parchoonArrow = document.getElementById('parchoonArrow');

/* ---------- AUTO CALC ---------- */
let lastEditedP = null;

p_rate.addEventListener('input', ()=>{ lastEditedP='rate'; calcParchoon(); });
p_kg.addEventListener('input', ()=>{ lastEditedP='kg'; calcParchoon(); });
p_amount.addEventListener('input', ()=>{ lastEditedP='amount'; calcParchoon(); });

function calcParchoon(){
    const rate = pn(p_rate.value);
    let kg = pn(p_kg.value);
    let amount = pn(p_amount.value);

    if(rate <= 0) return;

    if(lastEditedP === 'rate' || lastEditedP === 'kg'){
        p_amount.value = (rate * kg).toFixed(2);
    }

    if(lastEditedP === 'amount'){
        p_kg.value = (amount / rate).toFixed(3);
    }
}

/* ---------- SAVE ---------- */
async function saveParchoon(){

    const rate = pn(p_rate.value);
    const kg   = pn(p_kg.value);
    const amt  = pn(p_amount.value);

    if(rate <= 0 || kg <= 0){
        showMsg("❌ Enter valid rate and kg", true);
        return;
    }

    const fd = new FormData();
    fd.append('api','save_parchoon');
    fd.append('date', p_date.value);
    fd.append('rate', rate);
    fd.append('kg', kg);
    fd.append('amount', amt);

    try{
        const res = await fetch('daily_pg.php',{
            method:'POST',
            body: fd
        });

        const j = await res.json();

        if(j.success){
            p_rate.value = '';
            p_kg.value = '';
            p_amount.value = '';
            showMsg("✅ Parchoon saved successfully");
        } else {
            showMsg(j.message || "❌ Save failed", true);
        }

    }catch(e){
        console.error(e);
        showMsg("❌ Network error", true);
    }
}

/* ---------- MESSAGE ---------- */
function showMsg(text, error=false){
    p_msg.style.display = 'block';
    p_msg.style.background = error ? '#ffebee' : '#e8f5e9';
    p_msg.style.color = error ? '#c62828' : '#2e7d32';
    p_msg.innerText = text;

    setTimeout(()=> p_msg.style.display='none', 3000);
}

/* ---------- COLLAPSE ---------- */
function toggleParchoon(){
    parchoonBody.classList.toggle('hidden');
    parchoonArrow.textContent =
        parchoonBody.classList.contains('hidden') ? '▶' : '▼';
}
</script>




<!-- ================= EXPENDITURE SECTION ================= -->
<div class="container" style="margin-top:20px">

<!-- ===== HEADER ===== -->
<div class="section-header" onclick="toggleExpense()">
    <h2>💰 EXPENDITURE</h2>
    <span id="expenseArrow">▼</span>
</div>

<div id="expenseBody" class="section-body">

<!-- ===== INPUTS ===== -->
<div class="grid" style="grid-template-columns:repeat(5,minmax(160px,1fr))">

    <select id="exp_category">
        <option value="">Category</option>
        <option>Vehicle</option>
        <option>Shop</option>
        <option>Salaries</option>
        <option>Other</option>
    </select>

    <input id="exp_date" type="date" value="<?=date('Y-m-d')?>">

    <input id="exp_details" placeholder="Details">

    <input id="exp_byhand" placeholder="By Hand">

    <input id="exp_amount" type="number" step="0.01" placeholder="Amount">

</div>

<br>

<!-- ===== BUTTONS ===== -->
<div style="display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn save" onclick="saveExpense()">💾 Save</button>
</div>

</div>
</div>

<script>
/* ================= EXPENDITURE LOGIC ================= */

function toggleExpense(){
    expenseBody.classList.toggle('hidden');
    expenseArrow.textContent =
        expenseBody.classList.contains('hidden') ? '▶' : '▼';
}

/* SAVE */
async function saveExpense(){

    if(!exp_category.value || !exp_date.value || !exp_details.value || !exp_amount.value){
        alert("Fill all required fields");
        return;
    }

    const fd = new FormData();
    fd.append("action","save");
    fd.append("category", exp_category.value);
    fd.append("date", exp_date.value);
    fd.append("details", exp_details.value);
    fd.append("byhand", exp_byhand.value);
    fd.append("amount", exp_amount.value);

    const res = await fetch("expenditure.php", {
        method:"POST",
        body: fd
    });

    const j = await res.json();

    if(j.status === "success"){
        alert("Expenditure Saved");
        exp_details.value='';
        exp_byhand.value='';
        exp_amount.value='';
    } else {
        alert(j.message || "Save failed");
    }
}

/* REFS */
const exp_category = document.getElementById('exp_category');
const exp_date = document.getElementById('exp_date');
const exp_details = document.getElementById('exp_details');
const exp_byhand = document.getElementById('exp_byhand');
const exp_amount = document.getElementById('exp_amount');

const expenseBody = document.getElementById('expenseBody');
const expenseArrow = document.getElementById('expenseArrow');
</script>


<!-- ================= GUARANTY SECTION ================= -->
<div class="container" style="margin-top:20px">

<!-- ===== HEADER ===== -->
<div class="section-header" onclick="toggleGuaranty()">
    <h2>🧾 GUARANTY</h2>
    <span id="guarantyArrow">▼</span>
</div>

<div id="guarantyBody" class="section-body">

<!-- ===== INPUTS ===== -->
<div class="grid" style="grid-template-columns:repeat(6,minmax(160px,1fr))">

    <input id="g_date" type="date" value="<?=date('Y-m-d')?>">

    <input id="g_name" placeholder="Customer Name">

    <select id="g_cylinder">
	    <option value="12KG">12KG</option>
        <option value="15KG">15KG</option>
        <option value="45KG">45KG</option>
    </select>

    <input id="g_qty" type="number" min="1" value="1" placeholder="Qty">

    <select id="g_type">
        <option value="cash">Cash</option>
        <option value="person">Person</option>
    </select>

    <input id="g_cash" type="number" step="0.01" placeholder="Cash Amount">

    <input id="g_guarantor" placeholder="Guarantor Name">

</div>

<br>

<!-- ===== BUTTONS ===== -->
<div style="display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn save" onclick="saveGuaranty()">💾 Save</button>
</div>

</div>
</div>

<script>
/* ================= GUARANTY LOGIC ================= */

function toggleGuaranty(){
    guarantyBody.classList.toggle('hidden');
    guarantyArrow.textContent =
        guarantyBody.classList.contains('hidden') ? '▶' : '▼';
}

/* SAVE GUARANTY */
async function saveGuaranty(){

    if(!g_name.value || !g_date.value){
        alert("Customer name & date required");
        return;
    }

    const fd = new FormData();
    fd.append("api","save_guaranty");
    fd.append("date", g_date.value);
    fd.append("name", g_name.value);
    fd.append("ctype", g_cylinder.value);
    fd.append("qty", g_qty.value);
    fd.append("gtype", g_type.value);
    fd.append("cash", g_cash.value);
    fd.append("guarantor", g_guarantor.value);

    const res = await fetch("daily_pg.php",{
        method:"POST",
        body: fd
    });

    const j = await res.json();

    if(j.success){
        alert("Guaranty Saved");
        g_name.value='';
        g_qty.value=1;
        g_cash.value='';
        g_guarantor.value='';
    } else {
        alert(j.message || "Save failed");
    }
}

/* REFS */
const g_date = document.getElementById('g_date');
const g_name = document.getElementById('g_name');
const g_cylinder = document.getElementById('g_cylinder');
const g_qty = document.getElementById('g_qty');
const g_type = document.getElementById('g_type');
const g_cash = document.getElementById('g_cash');
const g_guarantor = document.getElementById('g_guarantor');

const guarantyBody = document.getElementById('guarantyBody');
const guarantyArrow = document.getElementById('guarantyArrow');
</script>


</body>
</html>
