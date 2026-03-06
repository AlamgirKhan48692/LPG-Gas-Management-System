<?php

session_start();
if(!isset($_SESSION['username'])){
    header("Location: login.html");
    exit();
}
$allowed_roles=["md","operator"];
if(!isset($_SESSION['role']) || !in_array($_SESSION['role'],$allowed_roles)){
    session_unset();
    session_destroy();
    header("Location: login.html");
    exit();
}

include 'db_connect.php';

// 4) Full details of inactive customers (excluding zero remaining & zero cylinder)
$q4 = "
SELECT s.*
FROM sale_details s
JOIN (
    SELECT name, MAX(date) AS last_date
    FROM sale_details
    GROUP BY name
    HAVING MAX(date) < DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
) inactive ON s.name = inactive.name
WHERE NOT (s.amountRemaining = 0 AND s.totalCylinderRemaining = 0)
ORDER BY s.name, s.date DESC
";

$r4 = $conn->query($q4);


// 2) Customers with remaining above 50000
$q2 = "
SELECT 
    name,
    SUM(total) AS total_sum,
    SUM(receivedAmount) AS received_sum,
    SUM(amountRemaining) AS remaining_sum
FROM sale_details
GROUP BY name
HAVING SUM(amountRemaining) > 50000
ORDER BY remaining_sum DESC
";
$r2 = $conn->query($q2);

// 3) Customers with cylinder above 15
$q3 = "
SELECT 
    name,
    (SUM(kg12) + SUM(kg15) + SUM(kg45)) AS sold_cyl,
    (SUM(kg12Received) + SUM(kg15Received) + SUM(kg45Received)) AS rec_cyl,
    (
      (SUM(kg12) + SUM(kg15) + SUM(kg45))
      -
      (SUM(kg12Received) + SUM(kg15Received) + SUM(kg45Received))
    ) AS cyl_remaining
FROM sale_details
GROUP BY name
HAVING cyl_remaining > 15
ORDER BY cyl_remaining DESC
";
$r3 = $conn->query($q3);

?>
<!DOCTYPE html>
<html>
<head>
<title>Checklist Page</title>

<style>
body{
    font-family:Poppins,system-ui,Arial;
    margin:0;
    background:#eef2f7;
}

.container{
    max-width:1200px;
    margin:20px auto;
    background:#fff;
    padding:20px;
    border-radius:14px;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
}

h1{
    color:#1e88e5;
    margin-bottom:20px;
    font-weight:900;
}

.section{ margin-bottom:30px; }

.section h2{
    background:linear-gradient(135deg,#1e88e5,#42a5f5);
    color:#fff;
    padding:12px 16px;
    border-radius:10px;
    margin-bottom:10px;
    font-size:18px;
}

.table-wrap{
    overflow:auto;
    background:#fff;
    border-radius:12px;
}

table{ width:100%; border-collapse:collapse; }

th,td{
    padding:12px 10px;
    border-bottom:1px solid #e5e7eb;
    text-align: left;
}


th{
    background:#f1f5ff;
    color:#1e3a8a;
    font-weight:800;
    text-align: left;
}


tr:hover{ background:#f8fbff; }

.bad{ color:#d32f2f; font-weight:900; }

@media(max-width:600px){
    h1{font-size:22px}
    .section h2{font-size:16px}
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

.collapsible {
    cursor: pointer;
    user-select: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.collapsible span {
    font-size: 20px;
    transition: transform .2s ease;
}

.collapsible.active span {
    transform: rotate(90deg);
}

.content {
    display: none;
    animation: fadeIn .25s ease;
}

@keyframes fadeIn {
    from {opacity:0; transform: translateY(-5px);}
    to {opacity:1; transform: translateY(0);}
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
  <a href="daily_closing.php">Daily Sumary</a>
  
  <?php if($_SESSION['role']==='md'): ?><a href="cash_in_hand.php">Cash in Hand</a><?php endif; ?>
  <?php if($_SESSION['role']==='md'): ?><a href="profit.php">Profit</a><?php endif; ?>
  <?php if($_SESSION['role']==='md'): ?><a href="assets.php">Assets</a><?php endif; ?>
  
  <a href="image_notes.php">Image Notes</a>
  
</div>

<div class="container">

<h1>📋 Business Checklist</h1>

<!-- ================= SECTION 1 ================= -->
<div class="section">
<h2 class="collapsible">1) Full Details of Inactive Customers (Last 2 Month) <span>▶</span></h2>
<div class="content">
<div class="table-wrap">

<table>
<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Date</th>
    <th>KG15</th>
    
    <th>KG45</th>
    
    <th>Total</th>
    <th>Received</th>
    <th>Remaining</th>
    <th>C Remaining</th>
</tr>

<?php if ($r4 && $r4->num_rows > 0): ?>
<?php while($row = $r4->fetch_assoc()): ?>
<tr>
    <td><?= (int)$row['id'] ?></td>
    <td><b><?= htmlspecialchars($row['name']) ?></b></td>
    <td><?= htmlspecialchars($row['date']) ?></td>
    <td><?= (int)$row['kg15'] ?></td>
    
    <td><?= (int)$row['kg45'] ?></td>
    
    <td><?= number_format($row['total']) ?></td>
    <td><?= number_format($row['receivedAmount']) ?></td>
    <td class="bad"><?= number_format($row['amountRemaining']) ?></td>
    <td class="bad"><?= (int)$row['totalCylinderRemaining'] ?></td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="13">No record found</td></tr>
<?php endif; ?>

</table>
</div>
</div>
</div>


<!-- ================= SECTION 2 ================= -->
<div class="section">
<h2 class="collapsible">2) Customers with Remaining Amount above 50000 <span>▶</span></h2>
<div class="content">
<div class="table-wrap">

<table>
<tr>
    <th>Customer</th>
    <th>Total Sale</th>
    <th>Total Received</th>
    <th>Total Remaining</th>
</tr>

<?php if ($r2 && $r2->num_rows > 0): ?>
<?php while($row = $r2->fetch_assoc()): ?>
<tr>
    <td><b><?= htmlspecialchars($row['name']) ?></b></td>
    <td><?= number_format($row['total_sum']) ?></td>
    <td><?= number_format($row['received_sum']) ?></td>
    <td class="bad"><?= number_format($row['remaining_sum']) ?></td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="4">No record found</td></tr>
<?php endif; ?>
</table>
</div>
</div>
</div>


<!-- ================= SECTION 3 ================= -->
<div class="section">
<h2 class="collapsible">3) Customers with cylinder above 15 <span>▶</span></h2>
<div class="content">
<div class="table-wrap">

<table>
<tr>
    <th>Customer</th>
    <th>Sold Cylinders</th>
    <th>Received Cylinders</th>
    <th>Remaining Cylinders</th>
</tr>

<?php if ($r3 && $r3->num_rows > 0): ?>
<?php while($row = $r3->fetch_assoc()): ?>
<tr>
    <td><b><?= htmlspecialchars($row['name']) ?></b></td>
    <td><?= (int)$row['sold_cyl'] ?></td>
    <td><?= (int)$row['rec_cyl'] ?></td>
    <td class="bad"><?= (int)$row['cyl_remaining'] ?></td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="4">No record found</td></tr>
<?php endif; ?>
</table>
</div>
</div>
</div>


</div> <!-- container -->
<script>
document.querySelectorAll('.collapsible').forEach(header => {
    header.addEventListener('click', () => {
        header.classList.toggle('active');
        const content = header.nextElementSibling;

        if (content.style.display === "block") {
            content.style.display = "none";
        } else {
            content.style.display = "block";
        }
    });
});
</script>

</body>
</html>

<?php $conn->close(); ?>
