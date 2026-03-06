<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Spare Parts Management</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

<style>
* { box-sizing: border-box; }

body {
  font-family: 'Inter', sans-serif;
  background: linear-gradient(135deg,#eef2f7,#f8fafc);
  margin: 0;
  padding: 30px;
  color: #1f2933;
}

h1 {
  text-align: center;
  margin-bottom: 25px;
  font-size: 32px;
}

.cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px,1fr));
  gap: 20px;
  margin-bottom: 25px;

}

.card {
  background: #fff;
  border-radius: 16px;
  padding: 22px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.07);
  transition: 0.2s ease;
  color: white !important;
}

.card:hover { transform: translateY(-3px); }

.card h2 {
  margin: 0;
  font-size: 16px;
  color: white;
}


.total {
  font-size: 34px;
  font-weight: 700;
  margin-top: 10px;
}

.form {
  background: #fff;
  padding: 20px;
  border-radius: 16px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.07);
  margin-bottom: 30px;
  display: flex;
  justify-content: center;
  border:2px solid #000;
}

.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px,1fr));
  gap: 12px;
  width: 100%;
  max-width: 1200px;
}

.form input, .form select, .form button {
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid #d1d5db;
  font-size: 14px;
}

.form button {
  background: linear-gradient(135deg,#2563eb,#1d4ed8);
  color: #fff;
  font-weight: 600;
  border: none;
  cursor: pointer;
}

.dashboard-btn {
  cursor: pointer;
  text-align: center;
  transition: 0.25s ease;
}

.dashboard-btn:hover { transform: scale(1.04); }

.modal {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.6);
  display: none;
  justify-content: center;
  align-items: center;
  z-index: 999;
}

.modal-content {
  background: white;
  width: 95%;
  height: 95%;
  border-radius: 20px;
  padding: 20px;
  overflow: auto;
  position: relative;
}

.close-btn {
  position: absolute;
  top: 15px;
  right: 20px;
  border: none;
  background: #ef4444;
  color: white;
  padding: 8px 14px;
  border-radius: 8px;
  cursor: pointer;
}

/* MODAL TABLE FIX */
#modalTable {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
}

#modalTable th {
  background: #f1f5f9;
  padding: 12px;
  border-bottom: 2px solid #e5e7eb;
  text-align: center;
  font-weight: 600;
}

#modalTable td {
  padding: 10px;
  border-bottom: 1px solid #e5e7eb;
  text-align: center;
}

#modalTable tr:hover {
  background: #f8fafc;
}

.border{
	border:2px solid #000;
	padding: 10px;
	border-collapse: collapse;
	border-radius: 16px;
    
    box-shadow: 0 10px 25px rgba(0,0,0,0.07);
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
</style>
</head>
<body style="background:#FEF9C3; border:2px solid #000;">

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
  <a href="checklist.php">Check List</a>
  
</div>

<h1>🔧 Spare Parts Management Dashboard</h1>

<div class="border">
<div class="cards">
  <div class="card" style="background:#0072ff"><h2>Total Spare Parts In</h2><div class="total" id="topInTotal">0</div></div>
  <div class="card" style="background:#00b09b"><h2>Total Spare Parts Out</h2><div class="total" id="topOutTotal">0</div></div>
  <div class="card" style="background:#ff512f"><h2>Total Remaining</h2><div class="total" id="topRemainTotal">0</div></div>
</div>

<div class="form" style="background:lightblue">
  <div class="form-row">
    <select id="category">
      <option value="in">Spare Parts IN</option>
      <option value="out">Spare Parts OUT</option>
    </select>
    <input type="date" id="date" />
    <input type="text" id="type" placeholder="Spare Part Name" />
    <input type="text" id="details" placeholder="Details" />
    <input type="text" id="byhand" placeholder="Received/Issued By" />
    <input type="number" id="amount" placeholder="Amount" />
    <button onclick="addEntry()">➕ Add Entry</button>
  </div>
</div>

<div class="cards">
  <div class="card dashboard-btn" onclick="openModal('in')" style="background:#0072ff"><h2>📥 Spare Parts In</h2><div class="total" id="inTotal">0</div></div>
  <div class="card dashboard-btn" onclick="openModal('out')" style="background:#00b09b"><h2>📤 Spare Parts Out</h2><div class="total" id="outTotal">0</div></div>
  <div class="card dashboard-btn" onclick="openModal('remain')" style="background:#ff512f"><h2>📦 Remaining Stock</h2><div class="total" id="remainTotal">0</div></div>
</div>
</div>
<script>
let totalIn = 0;
let totalOut = 0;
const remaining = {};

document.getElementById("date").value = new Date().toISOString().split("T")[0];

function addEntry() {
  const date = document.getElementById("date").value;
  const type = document.getElementById("type").value;
  const detailsText = document.getElementById("details").value;
  const detailsNumber = parseFloat(detailsText) || 0;
  const byhand = document.getElementById("byhand").value;
  const amount = parseFloat(document.getElementById("amount").value) || 0;
  const category = document.getElementById("category").value;

  if (!date || !type || !amount) return alert("Fill required fields!");

  const row = `<tr>
    <td>${date}</td>
    <td>${type}</td>
    <td>${detailsText}</td>
    <td>${byhand}</td>
    <td>${amount}</td>
  </tr>`;

  if (category === "in") {
    document.querySelector("#inTable tbody").insertAdjacentHTML("beforeend", row);
    totalIn += amount;
    topInTotal.innerText = totalIn;
    inTotal.innerText = totalIn;

    const oldAmount = remaining[type]?.amount || 0;
    const oldDetail = remaining[type]?.detailAmount || 0;

    remaining[type] = {
      amount: oldAmount + amount,
      detailAmount: oldDetail + detailsNumber,
      date: date
    };

  } else {
    document.querySelector("#outTable tbody").insertAdjacentHTML("beforeend", row);
    totalOut += amount;
    topOutTotal.innerText = totalOut;
    outTotal.innerText = totalOut;

    const oldAmount = remaining[type]?.amount || 0;
    const oldDetail = remaining[type]?.detailAmount || 0;

    remaining[type] = {
      amount: oldAmount - amount,
      detailAmount: oldDetail - detailsNumber,
      date: date
    };
  }

  updateRemaining();

}

function updateRemaining() {
  const tbody = document.querySelector("#remainTable tbody");
  tbody.innerHTML = "";
  let sum = 0;

  for (const type in remaining) {
    const item = remaining[type];
    sum += item.amount;

    tbody.insertAdjacentHTML("beforeend", `
      <tr>
        <td>${item.date}</td>
        <td>${type}</td>
        <td>${item.detailAmount}</td>
        <td>System</td>
        <td>${item.amount}</td>
      </tr>
    `);
  }

  remainTotal.innerText = sum;
  topRemainTotal.innerText = sum;
}



function openModal(type) {
  const modal = document.getElementById("modal");
  const title = document.getElementById("modalTitle");
  const table = document.getElementById("modalTable");

  let sourceTable;
  if (type === "in") { title.innerText = "📥 Spare Parts In"; sourceTable = inTable; }
  else if (type === "out") { title.innerText = "📤 Spare Parts Out"; sourceTable = outTable; }
  else { title.innerText = "📦 Remaining Stock"; sourceTable = remainTable; }

  table.innerHTML = sourceTable.innerHTML;
  modal.style.display = "flex";
}

function closeModal() {
  modal.style.display = "none";
}
</script>

<div style="display:none">
<table id="inTable"><thead><tr><th>Date</th><th>Type</th><th>Details</th><th>ByHand</th><th>Amount</th></tr></thead><tbody></tbody></table>
<table id="outTable"><thead><tr><th>Date</th><th>Type</th><th>Details</th><th>ByHand</th><th>Amount</th></tr></thead><tbody></tbody></table>
<table id="remainTable"><thead><tr><th>Date</th><th>Type</th><th>Details</th><th>ByHand</th><th>Amount</th></tr></thead><tbody></tbody></table>
</div>

<div class="modal" id="modal">
  <div class="modal-content">
    <button class="close-btn" onclick="closeModal()">Close</button>
    <h2 id="modalTitle"></h2>
    <table id="modalTable"></table>
  </div>
</div>

</body>
</html>
