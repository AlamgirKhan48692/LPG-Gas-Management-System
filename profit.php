<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

include 'db_connect.php';


// ================= DAILY PROFIT FUNCTION =================
function dayProfit($conn, $date) {

    // ✅ profit directly from sales
    $saleProfit = $conn->query("
        SELECT IFNULL(SUM(profit),0) AS sale_profit
        FROM sale_details
        WHERE DATE(date) = '$date'
    ")->fetch_assoc()['sale_profit'];

    // ✅ expenses (REMOVED 'other')
    $exp = $conn->query("
        SELECT IFNULL(SUM(amount),0) AS exp
        FROM expenditure_data
        WHERE DATE(date) = '$date'
          AND LOWER(category) IN ('shop','vehicle')
    ")->fetch_assoc()['exp'];

    // ✅ permanent daily expense
    $permanent = 0;

    return [
        "sale"   => $saleProfit,
        "exp"    => $exp + $permanent,
        "profit" => $saleProfit - ($exp + $permanent)
    ];
}



// ================= RANGE PROFIT (MONTH/YEAR) =================
function rangeProfit($conn, $start, $end) {

    // ✅ total sales profit
    $saleProfit = $conn->query("
        SELECT IFNULL(SUM(profit),0) AS sale_profit
        FROM sale_details
        WHERE DATE(date) BETWEEN '$start' AND '$end'
    ")->fetch_assoc()['sale_profit'];

    // ✅ expenses (REMOVED 'other')
    $exp = $conn->query("
        SELECT IFNULL(SUM(amount),0) AS exp
        FROM expenditure_data
        WHERE DATE(date) BETWEEN '$start' AND '$end'
          AND LOWER(category) IN ('shop','vehicle')
    ")->fetch_assoc()['exp'];

    // ✅ permanent expense (per day)
    $days = (new DateTime($start))->diff(new DateTime($end))->days + 1;
    $permanent = 0 * $days;

    return [
        "sale"   => $saleProfit,
        "exp"    => $exp + $permanent,
        "profit" => $saleProfit - ($exp + $permanent)
    ];
}



// ================= TODAY, MONTH, YEAR =================
$today = date("Y-m-d");
$todayProfit = dayProfit($conn, $today);

$currentMonth = $_GET['month'] ?? date("Y-m");
$filter_start = $currentMonth . "-01";
$filter_end   = date("Y-m-t", strtotime($filter_start));
$monthProfit = rangeProfit($conn, $filter_start, $filter_end);

// ================= YEAR SELECTOR =================
$selectedYear = $_GET['year'] ?? date("Y");

$ystart = $selectedYear . "-01-01";
$yend   = $selectedYear . "-12-31";

$yearProfit = rangeProfit($conn, $ystart, $yend);



// ================= BUILD DAILY LIST =================
$list = [];
$loop = new DatePeriod(
    new DateTime($filter_start),
    new DateInterval('P1D'),
    (new DateTime($filter_end))->modify('+1 day')
);

foreach ($loop as $d) {
    $day = $d->format("Y-m-d");
    $list[] = ["date" => $day] + dayProfit($conn, $day);
}


// ================= TOTALS FOR BOTTOM ROW =================
$total_sale = array_sum(array_column($list, 'sale'));
$total_exp = array_sum(array_column($list, 'exp'));
$total_profit = array_sum(array_column($list, 'profit'));
?>
<!DOCTYPE html>
<html>
<head>
<title>Profit Report</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
body{font-family:Poppins;background:#eef2f7;margin:0;padding:0;}
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
.container{max-width:1100px;margin:20px auto;padding:20px;background:#fff;border-radius:12px;}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;}
.card{padding:20px;border-radius:12px;color:#fff;font-weight:bold;text-align:center;}
.c1{background:#0072ff;} 
.c2{background:#00b09b;} 
.c3{background:#ff512f;}
table{width:100%;border-collapse:collapse;margin-top:20px;}
th,td{padding:10px;border-bottom:1px solid #ccc;text-align:center;}
th{background:#1e88e5;color:#fff;}
.filterBox{margin-top:20px;padding:10px;background:#f0f0f0;border-radius:8px;}
button{padding:6px 12px;border:none;background:#1e88e5;color:#fff;border-radius:5px;font-weight:600;font-size:13px;cursor:pointer;}
.total-row{background:#e3f2fd;font-weight:bold;}
.profit{color:green;}
.loss{color:red;}
</style>
</head>
<body>

<!-- NAVIGATION -->
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
  
  <?php if($_SESSION['role']==='md'): ?><a href="assets.php">Assets</a><?php endif; ?>
  
  <a href="image_notes.php">Image Notes</a>
  <a href="checklist.php">Check List</a>
  
</div>

<div class="container">

<h2>📊 Profit & Loss Summary</h2>

<div class="cards">
    <div class="card c1">
        Today Profit
        <div style="font-size:22px;">Rs <?= number_format($todayProfit['profit']) ?></div>
    </div>

    <div class="card c2">
        This Month Profit
        <div style="font-size:22px;">Rs <?= number_format($monthProfit['profit']) ?></div>
    </div>

    <div class="card c3">
        Yearly Profit (<?= $selectedYear ?>)
        <div style="font-size:22px;">Rs <?= number_format($yearProfit['profit']) ?></div>
    </div>
</div>

<div class="filterBox">
<form method="GET">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <label>Select Month:</label>
            <input type="month" name="month" value="<?= $currentMonth ?>">
            <label>Select Year:</label>
            <select name="year">
                <?php for ($y = date("Y") - 5; $y <= date("Y") + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= ($y == $selectedYear) ? "selected" : "" ?>>
                        <?= $y ?>
                    </option>
                <?php endfor; ?>
            </select>
            <button>Show</button>
        </div>

        <div style="display:flex; gap:10px;">
            <button type="button" onclick="exportExcel()">Excel</button>
            <button type="button" onclick="window.print()">PDF</button>
        </div>
    </div>
</form>
</div>

<canvas id="profitChart" height="100"></canvas>

<script>
let labels = <?= json_encode(array_column($list, 'date')) ?>;
let profits = <?= json_encode(array_column($list, 'profit')) ?>;

new Chart(document.getElementById('profitChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Daily Profit',
            data: profits,
            borderWidth: 2,
            borderColor: 'blue',
            fill: false
        }]
    }
});
</script>

<table id="profitTable">
<tr>
    <th>Date</th>
    <th>Sale</th>
    <th>Expenditure</th>
    <th>Profit / Loss</th>
</tr>

<tr class="total-row">
    <td><b>Total</b></td>
    <td><b>Rs <?= number_format($total_sale) ?></b></td>
    <td><b>Rs <?= number_format($total_exp) ?></b></td>
    <td class="<?= $total_profit>=0 ? 'profit' : 'loss' ?>">
        <b>Rs <?= number_format($total_profit) ?></b>
    </td>
</tr>

<?php foreach($list as $r): ?>
<tr>
    <td><?= $r['date'] ?></td>
    <td>Rs <?= number_format($r['sale']) ?></td>
    <td>Rs <?= number_format($r['exp']) ?></td>
    <td class="<?= $r['profit']>=0 ? 'profit' : 'loss' ?>">
        Rs <?= number_format($r['profit']) ?>
    </td>
</tr>
<?php endforeach; ?>
</table>

<script>
function exportExcel(){
    let wb = XLSX.utils.book_new();
    let ws = XLSX.utils.table_to_sheet(document.getElementById("profitTable"));
    XLSX.utils.book_append_sheet(wb, ws, "Monthly Profit");
    XLSX.writeFile(wb, "Profit_Report_<?= $currentMonth ?>.xlsx");
}
</script>

</body>
</html>
