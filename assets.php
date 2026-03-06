<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit;
}
include 'db_connect.php';

/* ================= SUMMARY CALCULATIONS ================= */

$partnerOneAssets = 0;
$partnerTwoAssets = 0;
$bothPartnersAssets = 0;

$res = $conn->query("SELECT purchase_amount, partner FROM assets");
while ($r = $res->fetch_assoc()) {
    $amount = (float)$r['purchase_amount'];

    if ($r['partner'] === 'Partner One') {
        $partnerOneAssets += $amount;
    } elseif ($r['partner'] === 'Partner Two') {
        $partnerTwoAssets += $amount;
    } elseif ($r['partner'] === 'Both Partners') {
        $bothPartnersAssets += $amount;
    }
}
$totalAssets = $partnerOneAssets + $partnerTwoAssets + $bothPartnersAssets;

/* ================= SAVE ASSET ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $asset_name = $_POST['asset_name'];
    $category   = $_POST['category'];
    $partner    = $_POST['partner'];
    $purchase_date = $_POST['purchase_date'];
    $purchase_date_display = $_POST['purchase_date_display'];
    $purchase_amount = (float)$_POST['purchase_amount'];

    $quantity = (!empty($_POST['quantity']) && $_POST['quantity'] > 0)
        ? (int)$_POST['quantity']
        : 1;

    $purchase_amount = $purchase_amount * $quantity;


    $stmt = $conn->prepare("
        INSERT INTO assets
        (asset_name, category, partner, purchase_date, purchase_date_display, purchase_amount, quantity)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sssssdi",
        $asset_name,
        $category,
        $partner,
        $purchase_date,
        $purchase_date_display,
        $purchase_amount,
        $quantity
    );
    $stmt->execute();
    $stmt->close();

    header("Location: assets.php");
    exit;
}

/* ================= DELETE ================= */

if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM assets WHERE id=$id");
    header("Location: assets.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Assets — Business</title>

<style>
:root{
  --bg-main: linear-gradient(135deg,#0f2027,#203a43,#2c5364);
  --card-blue: linear-gradient(135deg,#2193b0,#6dd5ed);
  --card-green: linear-gradient(135deg,#11998e,#38ef7d);
  --card-orange: linear-gradient(135deg,#f12711,#f5af19);
}
body{margin:0;font-family:Poppins,Arial;background:var(--bg-main);}

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

.container{
  max-width:1100px;margin:25px auto;background:#fff;
  padding:22px;border-radius:16px;
  box-shadow:0 20px 50px rgba(0,0,0,.25);
}
.header{display:flex;align-items:center;gap:12px}

/* SUMMARY */
.summary-bar{
  display:grid;
  grid-template-columns:repeat(4,minmax(0,1fr));
  gap:16px;margin:20px 0;
}
.summary-card{
  padding:18px;border-radius:16px;color:#fff;
  text-align:center;font-weight:700;
}
.card-blue{background:var(--card-blue)}
.card-green{background:var(--card-green)}
.card-orange{background:var(--card-orange)}
.card-purple{background:linear-gradient(135deg,#7f00ff,#e100ff)}
.kv{font-size:22px;font-weight:900;margin-top:6px}

/* FORM */
.form-section{
  background:#f1f5f9;padding:16px;border-radius:14px;
  display:flex;gap:12px;flex-wrap:wrap;
}
.form-section input,
.form-section select{
  padding:10px;
  border-radius:10px;
  border:1px solid #cbd5e1;
  width:153px;       /* ⬅️ fixed width */
  font-size:14px;
}


.button{
  padding:10px 18px;border:none;border-radius:10px;
  background:linear-gradient(135deg,#7c3aed,#22d3ee);
  color:#fff;font-weight:800;cursor:pointer;
}
.left-buttons,.right-buttons{display:flex;gap:10px}
.left-buttons{margin-right:auto}
.excel-btn{background:linear-gradient(135deg,#16a34a,#4ade80)}
.pdf-btn{background:linear-gradient(135deg,#dc2626,#f87171)}
.collapse-btn{background:linear-gradient(135deg,#475569,#94a3b8)}

/* TABLE */
table{width:100%;border-collapse:collapse;margin-top:20px}
th{background:#e0f2fe;padding:12px}
td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:center}
.asset-body.collapsed{display:none}

/* RESPONSIVE */
@media(max-width:1100px){
  .summary-bar{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:600px){
  .summary-bar{grid-template-columns:1fr}
}

</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
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
  <a href="daily_closing.php">Daily Sumary</a>
  
  <?php if($_SESSION['role']==='md'): ?><a href="cash_in_hand.php">Cash in Hand</a><?php endif; ?>
  <?php if($_SESSION['role']==='md'): ?><a href="profit.php">Profit</a><?php endif; ?>
  <a href="image_notes.php">Image Notes</a>
  <a href="checklist.php">Check List</a>
  
</div>

<main class="container">

<div class="header">
  <div style="font-size:34px">💻</div>
  <h1>Business Assets</h1>
</div>

<section class="summary-bar">
  <div class="summary-card card-blue">Total Assets<span class="kv"><br>Rs.  <?=number_format($totalAssets,)?></span></div>
  <div class="summary-card card-green">Partner One<span class="kv"><br>Rs.  <?=number_format($partnerOneAssets,)?></span></div>
  <div class="summary-card card-orange">Partner Two<span class="kv"><br>Rs.  <?=number_format($partnerTwoAssets,)?></span></div>
  <div class="summary-card card-purple">Both Partners<span class="kv"><br>Rs.  <?=number_format($bothPartnersAssets,)?></span></div>
</section>

<form method="post" class="form-section">

  <input name="asset_name" placeholder="Asset Name" required>

  <select name="category" id="categorySelect" required>
  <option disabled selected>Select Category</option>
  <option>Office Equipment</option>
  <option>Furniture</option>
  <option>Vehicle</option>
  <option>Cylinder</option>
  <option>Money</option>   <!-- ✅ Added -->
  <option>Others</option>
</select>


  <select name="partner" required>
    <option disabled selected>Select Partner</option>
    <option>Partner One</option>
    <option>Partner Two</option>
    <option>Both Partners</option>
  </select>

  <input 
  type="number" 
  name="quantity" 
  id="quantityField" 
  placeholder="Quantity" 
  min="1" 
  required
>


  <input type="date" id="purchaseDate" name="purchase_date" required>
  <input type="hidden" id="purchaseDateFormatted" name="purchase_date_display">

  <input type="number" name="purchase_amount" placeholder="Purchase Amount" required>

  <div class="left-buttons">
    <button class="button" type="submit">💾 Save Asset</button>
  </div>

  <div class="right-buttons">
    <button type="button" class="button collapse-btn" onclick="toggleTable()">➖ Collapse</button>
    <button type="button" id="exportExcelAssets" class="button excel-btn">📊 Excel</button>
    <button type="button" id="exportPdfAssets" class="button pdf-btn">📄 PDF</button>
  </div>

</form>

<table id="assetsTable">
<thead>
<tr>
  <th>Asset</th>
  <th>Category</th>
  <th>Partner</th>
  <th>Qty</th>
  <th>Date</th>
  <th>Cost</th>
  <th>Action</th>

</tr>
</thead>
<tbody id="assetTableBody" class="asset-body">
<?php
$res = $conn->query("SELECT * FROM assets ORDER BY id DESC");
while($r=$res->fetch_assoc()){
echo "<tr>
  <td>{$r['asset_name']}</td>
  <td>{$r['category']}</td>
  <td>{$r['partner']}</td>
  <td>{$r['quantity']}</td>
  <td>{$r['purchase_date_display']}</td>
  <td>Rs ".number_format($r['purchase_amount'],)."</td>
  <td>
    <a href='assets.php?action=delete&id={$r['id']}' 
       onclick=\"return confirm('Delete asset?')\">🗑️</a>
  </td>
</tr>";

}
?>
</tbody>
</table>

</main>

<script>
const d=new Date();
purchaseDate.value=d.toISOString().slice(0,10);
purchaseDateFormatted.value=
String(d.getMonth()+1).padStart(2,'0')+'/'+
String(d.getDate()).padStart(2,'0')+'/'+d.getFullYear();

function toggleTable(){
  assetTableBody.classList.toggle('collapsed');
}

document.getElementById("exportExcelAssets").onclick=()=>{
  const wb=XLSX.utils.book_new();
  const ws=XLSX.utils.table_to_sheet(assetsTable);
  XLSX.utils.book_append_sheet(wb,ws,"Assets");
  XLSX.writeFile(wb,"assets.xlsx");
};

document.getElementById("exportPdfAssets").onclick=async()=>{
  const canvas=await html2canvas(assetsTable,{scale:2});
  const img=canvas.toDataURL("image/png");
  const {jsPDF}=window.jspdf;
  const pdf=new jsPDF("p","mm","a4");
  const w=pdf.internal.pageSize.getWidth();
  pdf.addImage(img,"PNG",0,0,w,(canvas.height*w)/canvas.width);
  pdf.save("assets.pdf");
};
</script>

</body>
</html>
