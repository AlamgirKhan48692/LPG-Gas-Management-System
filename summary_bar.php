<?php
include_once 'db_connect.php';
$page = basename($_SERVER['PHP_SELF']);

function makeButton($type) {
  echo "<button class='refresh-btn' data-type='$type'>📊 Refresh Totals</button>";
}

// 🔹 STOCK PAGE SUMMARY
if (in_array($page, ['stock_form.php', 'stock_record.php'])) {
  $r = $conn->query("SELECT IFNULL(SUM(kg15),0) AS k15, IFNULL(SUM(kg45),0) AS k45 FROM stock_data");
  $data = $r ? $r->fetch_assoc() : ['k15'=>0,'k45'=>0];
  $total = $data['k15'] + $data['k45'];
  echo "
  <section class='summary-bar' id='summary-stock'>
    <div>15KG Stock<span>".number_format($data['k15'])."</span></div>
    <div>45KG Stock<span>".number_format($data['k45'])."</span></div>
    <div>Total Cylinders<span>".number_format($total)."</span></div>";
    makeButton('stock');
  echo "</section>";
}

// 🔹 SAIL PAGE SUMMARY
elseif (in_array($page, ['sail_detail.php', 'sail_record.php'])) {
  $r = $conn->query("
    SELECT 
      IFNULL(SUM(total),0) AS total,
      IFNULL(SUM(receivedAmount),0) AS received,
      IFNULL(SUM(amountRemaining),0) AS remaining
    FROM sail_record
  ");
  $data = $r ? $r->fetch_assoc() : ['total'=>0,'received'=>0,'remaining'=>0];
  echo "
  <section class='summary-bar' id='summary-sail'>
    <div>Total Sale<span>Rs. ".number_format($data['total'],2)."</span></div>
    <div>Received<span>Rs. ".number_format($data['received'],2)."</span></div>
    <div>Remaining<span>Rs. ".number_format($data['remaining'],2)."</span></div>";
    makeButton('sail');
  echo "</section>";
}

// 🔹 EXPENDITURE PAGE SUMMARY
elseif ($page === 'expenditure.php') {
  $r = $conn->query("
    SELECT 
      IFNULL(SUM(CASE WHEN details LIKE '%vehicle%' THEN amount ELSE 0 END),0) AS vehicle,
      IFNULL(SUM(CASE WHEN details LIKE '%shop%' THEN amount ELSE 0 END),0) AS shop,
      IFNULL(SUM(CASE WHEN details LIKE '%salar%' THEN amount ELSE 0 END),0) AS salaries,
      IFNULL(SUM(CASE WHEN details NOT LIKE '%vehicle%' 
                      AND details NOT LIKE '%shop%' 
                      AND details NOT LIKE '%salar%' THEN amount ELSE 0 END),0) AS other
    FROM expenditure_data
  ");
  $data = $r ? $r->fetch_assoc() : ['vehicle'=>0,'shop'=>0,'salaries'=>0,'other'=>0];
  $total_exp = $data['vehicle'] + $data['shop'] + $data['salaries'] + $data['other'];

  echo "
  <section class='summary-bar' id='summary-expenditure'>
    <div>Total Expenditure<span>Rs. ".number_format($total_exp,2)."</span></div>
    <div>Vehicle<span>Rs. ".number_format($data['vehicle'],2)."</span></div>
    <div>Shop<span>Rs. ".number_format($data['shop'],2)."</span></div>
    <div>Salaries<span>Rs. ".number_format($data['salaries'],2)."</span></div>
    <div>Other<span>Rs. ".number_format($data['other'],2)."</span></div>";
    makeButton('expenditure');
  echo "</section>";
}
?>

<style>
.summary-bar {
  display: flex;
  justify-content: space-evenly;
  align-items: center;
  background: #fff;
  border: 2px solid #ddd;
  border-radius: 12px;
  padding: 12px;
  margin: 18px 0;
  font-weight: bold;
  text-align: center;
  box-shadow: 0 1px 5px rgba(0,0,0,0.08);
  flex-wrap: wrap;
}
.summary-bar div { flex: 1; min-width: 140px; margin: 4px; }
.summary-bar span {
  display:block;
  font-size:1.1em;
  color:#007bff;
  margin-top:4px;
  font-weight:700;
}
.refresh-btn {
  background:#0b5ed7;
  color:#fff;
  border:none;
  border-radius:8px;
  padding:8px 12px;
  cursor:pointer;
  margin-left:10px;
  font-size:14px;
}
.refresh-btn:hover { background:#084298; }
</style>

<script>
document.addEventListener("DOMContentLoaded", ()=>{
  document.querySelectorAll(".refresh-btn").forEach(btn=>{
    btn.addEventListener("click", async ()=>{
      const type = btn.dataset.type;
      try {
        const res = await fetch(`fetch_summary.php?page=${type}`);
        const json = await res.json();

        if(json.type === "stock"){
          const bar = document.getElementById("summary-stock");
          bar.querySelectorAll("span")[0].textContent = json.data.k15.toLocaleString();
          bar.querySelectorAll("span")[1].textContent = json.data.k45.toLocaleString();
          bar.querySelectorAll("span")[2].textContent = json.data.total.toLocaleString();
        }
        if(json.type === "sail"){
          const bar = document.getElementById("summary-sail");
          bar.querySelectorAll("span")[0].textContent = "Rs. " + json.data.total.toLocaleString();
          bar.querySelectorAll("span")[1].textContent = "Rs. " + json.data.received.toLocaleString();
          bar.querySelectorAll("span")[2].textContent = "Rs. " + json.data.remaining.toLocaleString();
        }
        if(json.type === "expenditure"){
          const bar = document.getElementById("summary-expenditure");
          bar.querySelectorAll("span")[0].textContent = "Rs. " + json.data.total_exp.toLocaleString();
          bar.querySelectorAll("span")[1].textContent = "Rs. " + json.data.vehicle.toLocaleString();
          bar.querySelectorAll("span")[2].textContent = "Rs. " + json.data.shop.toLocaleString();
          bar.querySelectorAll("span")[3].textContent = "Rs. " + json.data.salaries.toLocaleString();
          bar.querySelectorAll("span")[4].textContent = "Rs. " + json.data.other.toLocaleString();
        }

        btn.textContent = "✅ Updated";
        setTimeout(()=>btn.textContent = "📊 Refresh Totals", 2000);
      } catch(err){
        alert("⚠️ Failed to refresh totals. Check console.");
        console.error(err);
      }
    });
  });
});
</script>
