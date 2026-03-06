<?php
session_start();
if(!isset($_SESSION['username'])){header("Location: login.html");exit();}
$allowed_roles=["md","operator"];
if(!isset($_SESSION['role'])||!in_array($_SESSION['role'],$allowed_roles)){
    session_unset();session_destroy();header("Location: login.html");exit();
}
include 'db_connect.php';

$filterDate = isset($_GET['date']) ? $_GET['date'] : '';
$filterMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$filterYear  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

/* ================= TOTAL SALES (CUMULATIVE) ================= */
if(!empty($filterDate)){
    // OVERALL UP TO SELECTED DATE
    $stmt = $conn->prepare("
        SELECT 
        IFNULL(SUM(total),0) total_sale,
        IFNULL(SUM(receivedAmount),0) total_received,
        IFNULL(SUM(amountRemaining),0) total_remaining,
        IFNULL(SUM(kg12),0) total_kg12,
        IFNULL(SUM(kg15),0) total_kg15,
        IFNULL(SUM(kg45),0) total_kg45 
        FROM sale_details
        WHERE DATE(date) <= ?
    ");
    $stmt->bind_param("s",$filterDate);
    $stmt->execute();
    $saleTotals = $stmt->get_result()->fetch_assoc();
}else{
    // FULL HISTORY
    $saleTotals = $conn->query("
        SELECT 
        IFNULL(SUM(total),0) total_sale,
        IFNULL(SUM(receivedAmount),0) total_received,
        IFNULL(SUM(amountRemaining),0) total_remaining,
        IFNULL(SUM(kg12),0) total_kg12,
        IFNULL(SUM(kg15),0) total_kg15,
        IFNULL(SUM(kg45),0) total_kg45 
        FROM sale_details
    ")->fetch_assoc();
}

/* ================= TOTAL EXPENDITURE (CUMULATIVE) ================= */
if(!empty($filterDate)){
    $stmt = $conn->prepare("
        SELECT IFNULL(SUM(amount),0) total_exp 
        FROM expenditure_data 
        WHERE DATE(date) <= ?
    ");
    $stmt->bind_param("s",$filterDate);
    $stmt->execute();
    $exp = $stmt->get_result()->fetch_assoc();
}else{
    $exp = $conn->query("
        SELECT IFNULL(SUM(amount),0) total_exp 
        FROM expenditure_data
    ")->fetch_assoc();
}

/* ================= DAILY / MONTHLY SALES ================= */
if(!empty($filterDate)){
    // DAILY
    $ms = $conn->prepare("
        SELECT 
        IFNULL(SUM(total),0) m_sale,
        IFNULL(SUM(receivedAmount),0) m_received,
        IFNULL(SUM(amountRemaining),0) m_remaining,
        IFNULL(SUM(kg12),0) m_kg12,
        IFNULL(SUM(kg15),0) m_kg15,
        IFNULL(SUM(kg45),0) m_kg45
        FROM sale_details 
        WHERE DATE(date) = ?
    ");
    $ms->bind_param("s",$filterDate);
}else{
    // MONTHLY
    $ms = $conn->prepare("
        SELECT 
        IFNULL(SUM(total),0) m_sale,
        IFNULL(SUM(receivedAmount),0) m_received,
        IFNULL(SUM(amountRemaining),0) m_remaining,
        IFNULL(SUM(kg12),0) m_kg12,
        IFNULL(SUM(kg15),0) m_kg15,
        IFNULL(SUM(kg45),0) m_kg45
        FROM sale_details 
        WHERE MONTH(date)=? AND YEAR(date)=?
    ");
    $ms->bind_param("ii",$filterMonth,$filterYear);
}
$ms->execute();
$monthSale = $ms->get_result()->fetch_assoc();

/* ================= DAILY / MONTHLY EXPENDITURE ================= */
if(!empty($filterDate)){
    $mx = $conn->prepare("
        SELECT IFNULL(SUM(amount),0) m_exp 
        FROM expenditure_data 
        WHERE DATE(date) = ?
    ");
    $mx->bind_param("s",$filterDate);
}else{
    $mx = $conn->prepare("
        SELECT IFNULL(SUM(amount),0) m_exp 
        FROM expenditure_data 
        WHERE MONTH(date)=? AND YEAR(date)=?
    ");
    $mx->bind_param("ii",$filterMonth,$filterYear);
}
$mx->execute();
$monthExp = $mx->get_result()->fetch_assoc();

/* ================= STOCK ================= */
$stockQ = $conn->query("
    SELECT 
    IFNULL(SUM(kg12),0) AS stock12,
    IFNULL(SUM(kg15),0) AS stock15,
    IFNULL(SUM(kg45),0) AS stock45
    FROM stock_data
");
$stock = $stockQ->fetch_assoc();

$remaining12 = (float)$stock['stock12'];
$remaining15 = (float)$stock['stock15'];
$remaining45 = (float)$stock['stock45'];

/* ================= AVG COST ================= */
$avgQ = $conn->query("
    SELECT
    SUM(kg12*cost12)/NULLIF(SUM(kg12),0) AS avgCost12,
    SUM(kg15*cost15)/NULLIF(SUM(kg15),0) AS avgCost15,
    SUM(kg45*cost45)/NULLIF(SUM(kg45),0) AS avgCost45
    FROM stock_data
    WHERE kg12>0 OR kg15>0 OR kg45>0
");
$avg = $avgQ->fetch_assoc();

/* ================= STOCK VALUE ================= */
$stockValue =
    ($remaining12 * $avg['avgCost12']) +
    ($remaining15 * $avg['avgCost15']) +
    ($remaining45 * $avg['avgCost45']);

?>

<!DOCTYPE html>
<html>
<head>
<title>Insaf AK LPG — Dashboard</title>

<style>
body{font-family:Poppins;background:#eef2f7;margin:0}
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

.container{max-width:1100px;margin:20px auto;padding:20px;background:#fff;border-radius:12px}
h1,h3{color:#1e88e5}

.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
.card{padding:20px;border-radius:12px;color:#fff;font-weight:bold;box-shadow:0 12px 30px rgba(0,0,0,.12)}
.c-blue{background:#0072ff}
.c-green{background:#00b09b}
.c-orange{background:#fb8c00}
.c-purple{background:#7b1fa2}
.c-red{background:#ff512f}
.value{font-size:22px;margin-top:6px}

.stock-bar{
background:linear-gradient(90deg,#0072ff,#00c6ff);
color:#fff;padding:16px;border-radius:12px;
font-weight:800;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px
}
form select,form button{padding:8px 12px;border-radius:6px;border:none;font-weight:bold}
form button{background:#1e88e5;color:#fff}
</style>
</head>

<body>

<div class="nav">
  <a href="quick_entry.php">Addition</a>
 
  <a href="daily_parchoon.php">Parchoon</a>
  <a href="sale_record.php">Sale Record</a>
  <a href="stock_records.php">Stock Record</a>
  <a href="expenditure.php">Expenditure</a>
  <a href="daily_guaranty.php">Guaranty</a>
  <a href="daily_closing.php">Daily Sumary</a>
  <a href="cash_in_hand.php">Cash in Hand</a>
  <?php if($_SESSION['role']==='md'): ?><a href="profit.php">Profit</a><?php endif; ?>
  <?php if($_SESSION['role']==='md'): ?><a href="assets.php">Assets</a><?php endif; ?>
  
  <a href="image_notes.php">Image Notes</a>
  <a href="checklist.php">Check List</a>
  
</div>

<div class="container">

<h1>Insaf AK LPG — Dashboard</h1>

<div class="stock-bar">
  <div>✔ Available Stock</div>
  <div>
    12KG ➤ <?=number_format($remaining12)?> |
    15KG ➤ <?=number_format($remaining15)?> |
    45KG ➤ <?=number_format($remaining45)?>
  </div>
  <div>💰 Rs <?=number_format($stockValue,2)?></div>
</div>

<h3>
<?php if(!empty($filterDate)): ?>
Overall Summary — Up to <?=date("d F Y",strtotime($filterDate))?>
<?php else: ?>
Overall Summary
<?php endif; ?>
</h3>



<form method="GET" style="display:flex;gap:10px;margin-bottom:15px;flex-wrap:wrap;">
<input type="date" name="date" value="<?= htmlspecialchars($filterDate ?: date('Y-m-d')) ?>">

<select name="month">
<?php for($m=1;$m<=12;$m++){ $s=$m==$filterMonth?'selected':''; echo "<option $s value='$m'>".date("F",mktime(0,0,0,$m,1))."</option>"; } ?>
</select>
<select name="year">
<?php for($y=date("Y")-5;$y<=date("Y");$y++){ $s=$y==$filterYear?'selected':''; echo "<option $s>$y</option>"; } ?>
</select>
<button>Filter</button>
</form>

<div class="cards">
  <div class="card c-blue">Month Sale<div class="value">Rs <?=number_format($monthSale['m_sale'])?></div></div>
  <div class="card c-green">Received<div class="value">Rs <?=number_format($monthSale['m_received'])?></div></div>
  <div class="card c-orange">Remaining<div class="value">Rs <?=number_format($monthSale['m_remaining'])?></div></div>
  <div class="card c-purple">12KG Sold<div class="value"><?=number_format($monthSale['m_kg12'])?></div></div>
  <div class="card c-purple">15KG Sold<div class="value"><?=number_format($monthSale['m_kg15'])?></div></div>
  <div class="card c-purple">45KG Sold<div class="value"><?=number_format($monthSale['m_kg45'])?></div></div>
  <div class="card c-red">Expenditure<div class="value">Rs <?=number_format($monthExp['m_exp'])?></div></div>
</div>

<h3 style="margin-top:25px;">Overall Summary</h3>

<div class="cards">
  <div class="card c-blue">Total Sale<div class="value">Rs <?=number_format($saleTotals['total_sale'])?></div></div>
  <div class="card c-green">Total Received<div class="value">Rs <?=number_format($saleTotals['total_received'])?></div></div>
  <div class="card c-orange">Total Remaining<div class="value">Rs <?=number_format($saleTotals['total_remaining'])?></div></div>
  <div class="card c-purple">12KG Sold<div class="value"><?=number_format($saleTotals['total_kg12'])?></div></div>
  <div class="card c-purple">15KG Sold<div class="value"><?=number_format($saleTotals['total_kg15'])?></div></div>
  <div class="card c-purple">45KG Sold<div class="value"><?=number_format($saleTotals['total_kg45'])?></div></div>
  <div class="card c-red">Total Expenditure<div class="value">Rs <?=number_format($exp['total_exp'])?></div></div>
</div>

<h3 style="margin-top:35px;">📉 Stock vs Sale</h3>
<canvas id="stockSaleChart" height="120"></canvas>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('stockSaleChart'),{
type:'bar',
data:{
labels:['12KG','15KG','45KG'],
datasets:[
{label:'Available Stock',data:[<?=$remaining12?>,<?=$remaining15?>,<?=$remaining45?>],backgroundColor:'#42a5f5'},
{label:'Sold',data:[<?=$saleTotals['total_kg12']?>,<?=$saleTotals['total_kg15']?>,<?=$saleTotals['total_kg45']?>],backgroundColor:'#ef5350'}
]},
options:{responsive:true,scales:{y:{beginAtZero:true}}}
});
</script>

</body>
</html>
