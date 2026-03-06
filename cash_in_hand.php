<?php
mysqli_report(MYSQLI_REPORT_OFF);
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit;
}
include 'db_connect.php';

function respond_json($data){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

$api = $_GET['api'] ?? $_POST['api'] ?? null;

if ($api) {

    // ================= GET SAVED DAY =================
    if ($api === 'get_saved_day') {
        $date = $_GET['date'];
        $q = $conn->prepare("SELECT * FROM cash_money_records WHERE record_date=?");
        $q->bind_param("s",$date);
        $q->execute();
        $r = $q->get_result()->fetch_assoc();
        respond_json($r ?: []);
    }

    // ================= CASH CALC =================
    if ($api === 'cash_money_calc') {
        $date = $_GET['date'];
        $add = floatval($_GET['add_money'] ?? 0);

        $q = $conn->prepare("
        SELECT COALESCE(SUM(receivedAmount),0) r 
        FROM sale_details 
        WHERE DATE(date)=?
        AND TRIM(LOWER(name)) != 'parchoon'");
        $q->bind_param("s",$date); 
        $q->execute();
        $received = floatval($q->get_result()->fetch_assoc()['r']);
    


        $q = $conn->prepare("SELECT COALESCE(SUM(amount),0) a FROM daily_parchoon WHERE date=?");
        $q->bind_param("s",$date); 
        $q->execute();
        $parchoon = floatval($q->get_result()->fetch_assoc()['a']);

        $q = $conn->prepare("SELECT COALESCE(SUM(amount),0) e FROM expenditure_data WHERE DATE(date)=?");
        $q->bind_param("s",$date); 
        $q->execute();
        $exp = floatval($q->get_result()->fetch_assoc()['e']);

        $q = $conn->prepare("
        SELECT COALESCE(SUM(totalAmount),0) s
        FROM stock_data
        WHERE date >= ?
        AND date < DATE_ADD(?, INTERVAL 1 DAY)
        AND plantName NOT LIKE '%AUTO_DEDUCT%'
        ");

        $q->bind_param("ss",$date,$date);

        $q->execute();
        $stock = floatval($q->get_result()->fetch_assoc()['s']);

        $cash = $add + $received + $parchoon - $exp - $stock;

        respond_json(compact('received','parchoon','exp','stock','cash'));
    }

    // ================= SAVE =================
    if ($api === 'save_cash_money') {
        $date = $_POST['date'];

        $chk = $conn->prepare("SELECT id FROM cash_money_records WHERE record_date=?");
        $chk->bind_param("s",$date); 
        $chk->execute();
        $res = $chk->get_result();

        if($res->num_rows){
            $q = $conn->prepare("UPDATE cash_money_records SET
                add_money=?, today_received=?, parchoon_amount=?, today_expenditure=?, stock_total=?, cash_money=?
                WHERE record_date=?");
            $q->bind_param("dddddds",
                $_POST['add_money'],
                $_POST['today_received'],
                $_POST['parchoon'],
                $_POST['today_exp'],
                $_POST['stock_total'],
                $_POST['cash_money'],
                $date
            );
            $q->execute();
            respond_json(['success'=>true,'updated'=>true]);
        }

        $q = $conn->prepare("INSERT INTO cash_money_records
        (record_date, add_money, today_received, parchoon_amount, today_expenditure, stock_total, cash_money)
        VALUES (?,?,?,?,?,?,?)");
        $q->bind_param("sdddddd",
            $date,
            $_POST['add_money'],
            $_POST['today_received'],
            $_POST['parchoon'],
            $_POST['today_exp'],
            $_POST['stock_total'],
            $_POST['cash_money']
        );
        $q->execute();
        respond_json(['success'=>true,'inserted'=>true]);
    }

    // ================= HISTORY =================
    if ($api === 'history') {
        $rows = [];
        $res = $conn->query("SELECT * FROM cash_money_records ORDER BY record_date DESC");

        $sum = ['add'=>0,'rec'=>0,'par'=>0,'exp'=>0,'stk'=>0,'cash'=>0];

        while($r = $res->fetch_assoc()){
            $sum['add'] += $r['add_money'];
            $sum['rec'] += $r['today_received'];
            $sum['par'] += $r['parchoon_amount'];
            $sum['exp'] += $r['today_expenditure'];
            $sum['stk'] += $r['stock_total'];
            $sum['cash'] += $r['cash_money'];
            $rows[] = $r;
        }

        respond_json(['rows'=>$rows,'sum'=>$sum]);
    }

    // ================= CIHW ADD =================
    if($api === 'cihw_add'){
        $q = $conn->prepare("INSERT INTO cash_in_hand_with (record_date, from_account, to_account, amount)
                             VALUES (?,?,?,?)");
        $q->bind_param("sssd", $_POST['date'], $_POST['from'], $_POST['to'], $_POST['amount']);
        $q->execute();
        respond_json(['success'=>true]);
    }

    // ================= CIHW LOAD =================
    if($api === 'cihw_get'){
        $q = $conn->prepare("SELECT from_account, to_account, amount FROM cash_in_hand_with WHERE record_date=?");
        $q->bind_param("s", $_GET['date']);
        $q->execute();
        $r = $q->get_result();
        $data=[];
        while($x=$r->fetch_assoc()) $data[]=$x;
        respond_json($data);
    }
	
	// ================= CIHW LOAD ALL =================
    if($api === 'cihw_get_all'){
    $res = $conn->query("SELECT record_date, from_account, to_account, amount FROM cash_in_hand_with ORDER BY record_date ASC");

    $data = [];
    while($x = $res->fetch_assoc()){
        $data[] = $x;
    }

    respond_json($data);
   } 
   
   // ================= DELETE CIHW DAY =================
   if($api === 'cihw_delete_day'){
    $date = $_POST['date'];

    $q = $conn->prepare("DELETE FROM cash_in_hand_with WHERE record_date=?");
    $q->bind_param("s",$date);
    $q->execute();

    respond_json(['success'=>true]);
   }


    exit;
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Cash In Hand</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:Poppins;background:#eef2f7;margin:0;padding:0}
.container{max-width:1100px;margin:20px auto;padding:20px;background:#fff;border-radius:12px}

.grid{
  display: grid !important;
  grid-template-columns: repeat(3, 1fr) !important;
  gap: 12px !important;
}


.card{
  padding:10px;
  border-radius:14px;
  font-size:13px;
  min-width:0;
  text-align:center;
  min-height:90px;         /* ✅ FIXED HEIGHT */
  display:flex;            /* ✅ CENTER CONTENT */
  flex-direction:column;
  justify-content:center;
}


.card .label{
  font-size:20px;
  color:white;
}

.card .value{
  font-size:22px;
  font-weight:bold;
  color:white;
}


.blue{background:#0072ff}
.green{background:#00b09b}
.orange{background:#fb8c00}
.pink{background:#ff512f}

table{width:100%;border-collapse:collapse}
th, td {
  padding: 10px;
  border-bottom: 1px solid #ccc;
  text-align: center;
}

th {
  text-align: center !important;
  vertical-align: middle;
}


/* ===== PRIMARY BUTTON ===== */
.btn-primary{
  padding:10px 18px;
  border:none;
  border-radius:10px;
  background:linear-gradient(135deg,#1e88e5,#1565c0);
  color:#fff;
  font-weight:700;
  font-size:15px;
  cursor:pointer;
  box-shadow:0 4px 10px rgba(0,0,0,.15);
  transition:.2s ease;
}

.btn-primary:hover{
  transform:translateY(-1px);
  box-shadow:0 6px 14px rgba(0,0,0,.25);
  opacity:.95;
}

.btn-primary:active{
  transform:translateY(0);
  box-shadow:0 3px 8px rgba(0,0,0,.2);
}

/* ===== ACTION BUTTON BASE ===== */
.btn-action{
  padding:10px 18px;
  border:none;
  border-radius:10px;
  font-weight:700;
  font-size:15px;
  cursor:pointer;
  transition:all .2s ease;
  box-shadow:0 4px 10px rgba(0,0,0,.15);
  color:#fff;
}

/* ===== SAVE BUTTON ===== */
.btn-save{
  background:linear-gradient(135deg,#00b09b,#00a08a);
}

.btn-save:hover{
  transform:translateY(-1px);
  box-shadow:0 6px 14px rgba(0,0,0,.25);
  filter:brightness(1.05);
}

.btn-save:active{
  transform:translateY(0);
  box-shadow:0 3px 8px rgba(0,0,0,.2);
}

/* ===== HIDE BUTTON ===== */
.btn-hide{
  background:linear-gradient(135deg,#ff512f,#dd2476);
  font-size:13px;
  padding:6px 14px;
  border-radius:20px;
}

.btn-hide:hover{
  transform:scale(1.05);
  box-shadow:0 5px 12px rgba(0,0,0,.25);
}

.btn-hide:active{
  transform:scale(1);
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

.btn-transfer{
  min-width: 120px;      /* same width */
  height: 44px;          /* same height */
  font-size: 16px;
  border-radius: 12px;
  padding: 0 20px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

</style>
<body>

<div class="nav">
  <a href="quick_entry.php">Addition</a>
 
  <a href="daily_parchoon.php">Parchoon</a>
  <a href="sale_record.php">Sale Record</a>
  <a href="stock_records.php">Stock Record</a>
  <a href="expenditure.php">Expenditure</a>
  <a href="daily_guaranty.php">Guaranty</a>
  <a href="daily_closing.php">Daily Sumary</a>
  
  <?php if($_SESSION['role']==='md'): ?><a href="profit.php">Profit</a><?php endif; ?>
  <?php if($_SESSION['role']==='md'): ?><a href="assets.php">Assets</a><?php endif; ?>
  
  <a href="image_notes.php">Image Notes</a>
  <a href="checklist.php">Check List</a>
  
</div>

<div class="container">
<h2>💰 Cash In Hand</h2>

<div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:20px;">

  <div></div>

  <div>
    <input type="date" id="theDate" value="<?=date('Y-m-d')?>">
    <button class="btn-primary" onclick="loadAll()">🔄 Load</button>
  </div>

</div>

<!-- CASH SUMMARY CARDS -->
<div class="grid" style="margin:20px 0">

  <div class="card blue">
    <div class="label">Received</div>
    <div class="value" id="sum_received">0</div>
  </div>

  <div class="card green">
    <div class="label">Parchoon</div>
    <div class="value" id="sum_parchoon">0</div>
  </div>

  <div class="card orange">
    <div class="label">Expense</div>
    <div class="value" id="sum_expense">0</div>
  </div>

  <div class="card pink">
    <div class="label">Stock</div>
    <div class="value" id="sum_stock">0</div>
  </div>

  <div class="card" style="background:#6a1b9a">
    <div class="label">Add Money</div>
    <div class="value" id="sum_addmoney">0</div>
  </div>

  <div class="card" style="background:#2e7d32">
    <div class="label">Cash In Hand</div>
    <div class="value" id="sum_cashhand">0</div>
  </div>
  <div class="card" style="background:#5e35b1">
  <div class="label">Asim</div>
  <div class="value" id="sum_asim">0</div>
</div>

<div class="card" style="background:#00897b">
  <div class="label">Shop</div>
  <div class="value" id="sum_shop">0</div>
</div>

<div class="card" style="background:#6d4c41">
  <div class="label">Neak</div>
  <div class="value" id="sum_neak">0</div>
</div>

</div>

<!-- ===== MODE BUTTONS ===== -->
<div style="display:flex; gap:10px; justify-content:center; margin:20px 0;">
  <button class="btn-action btn-save" onclick="showSection('normal')">💰 Cash In Hand</button>
  <button class="btn-action btn-primary" onclick="showSection('with')">📦 Cash In Hand With</button>
</div>

<!-- ================= NORMAL SECTION ================= -->
<div id="sectionNormal">

  <div style="display:flex; gap:10px; align-items:center; margin-bottom:15px;">
    <b>Add Money:</b>
    <input type="number" id="addMoneyInput" value="0" style="padding:8px;border-radius:8px;border:1px solid #ccc;">
    <button class="btn-action btn-save" onclick="applyAddMoney()">Add</button>
  </div>

  <table>
    <tr style="font-weight:bold;background:#f3e5f5">
      <td>Add Money</td>
      <td id="cm_addmoney">0</td>
    </tr>

    <tr><td>Received</td><td id="cm_received"></td></tr>
    <tr><td>Parchoon</td><td id="cm_parchoon"></td></tr>
    <tr><td>Expense</td><td id="cm_exp"></td></tr>
    <tr><td>Stock</td><td id="cm_stock"></td></tr>

    <tr style="font-weight:bold">
      <td>Cash In Hand</td>
      <td id="cm_total"></td>
    </tr>
  </table>

  <br>
  <button class="btn-action btn-save" onclick="saveCash()">💾 Save</button>

  <hr>

  <div style="display:flex; justify-content:space-between; align-items:center;">
    <h3 style="margin:0;">📜 History</h3>
    <button class="btn-action btn-hide" onclick="toggleHistory()">Hide</button>
  </div>

  <br>

  <div id="historyBox">
    <table>
      <thead>
        <tr>
          <th>Date</th><th>Add</th><th>Received</th><th>Parchoon</th><th>Expense</th><th>Stock</th><th>Cash In Hand</th>
        </tr>
      </thead>
      <tbody id="totalRow"></tbody>
      <tbody id="historyBody"></tbody>
    </table>
  </div>

</div>

<div id="sectionWith" style="display:none; padding:20px;">

  <h3>📦 Cash In Hand With (Transfer System)</h3>

  <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:20px;">

    <select id="fromAccount" style="padding:8px;border-radius:8px;">
      <option value="asim">Asim</option>
      <option value="shop">Shop</option>
      <option value="neak">Neak</option>
    </select>

    <b>➡️</b>

    <select id="toAccount" style="padding:8px;border-radius:8px;">
      <option value="asim">Asim</option>
      <option value="shop">Shop</option>
      <option value="neak">Neak</option>
    </select>

    <input type="number" id="transferAmount" placeholder="Amount" style="padding:8px;border-radius:8px;border:1px solid #ccc;">

    <button class="btn-action btn-save btn-transfer" onclick="doTransfer('add')">➕ Add</button>
    <button class="btn-action btn-hide btn-transfer" onclick="doTransfer('sub')">➖ Subtract</button>

  </div>

  <table>
    <thead>
      <tr style="background:#f3e5f5;font-weight:bold;">
        <th>Date</th>
        <th>Asim</th>
        <th>Shop</th>
        <th>Neak</th>
        <th>Total</th>
        <th>Action</th>

      </tr>
    </thead>
    <tbody id="transferBody"></tbody>
  </table>

</div>


</div> <!-- END CONTAINER -->


<script>
let ADD_MONEY = 0;

const $ = id => document.getElementById(id);
const fmt = v => Number(v||0).toLocaleString();

// ================= LOAD DAY =================
async function loadAll(){
  const d = $('theDate').value;

  // 1) Load saved record if exists
  const saved = await fetch(`cash_in_hand.php?api=get_saved_day&date=${d}`).then(r=>r.json());

  if(saved && saved.record_date){
    ADD_MONEY = Number(saved.add_money || 0);
    $('addMoneyInput').value = ADD_MONEY;
  } else {
    ADD_MONEY = 0;
    $('addMoneyInput').value = '';
  }

  // 2) Calculate live values
  await recalcCash(saved);
  loadHistory();
  loadCIHW(); // ✅ load transfer system
}

// ================= RECALC =================
async function recalcCash(saved=null){
  const d = $('theDate').value;

  const r = await fetch(`cash_in_hand.php?api=cash_money_calc&date=${d}&add_money=${ADD_MONEY}`).then(r=>r.json());

  if(saved && saved.record_date){
    r.received = Number(saved.today_received);
    r.parchoon = Number(saved.parchoon_amount);
    r.exp = Number(saved.today_expenditure);
    r.stock = Number(saved.stock_total);
    r.cash = Number(saved.cash_money);
  }

  $('sum_received').innerText = fmt(r.received);
  $('sum_parchoon').innerText = fmt(r.parchoon);
  $('sum_expense').innerText = fmt(r.exp);
  $('sum_stock').innerText = fmt(r.stock);
  $('sum_addmoney').innerText = fmt(ADD_MONEY);
  $('sum_cashhand').innerText = fmt(r.cash);

  $('cm_addmoney').innerText = fmt(ADD_MONEY);
  $('cm_received').innerText = fmt(r.received);
  $('cm_parchoon').innerText = fmt(r.parchoon);
  $('cm_exp').innerText = fmt(r.exp);
  $('cm_stock').innerText = fmt(r.stock);
  $('cm_total').innerText = fmt(r.cash);

  window.__cash = r;
}

// ================= APPLY ADD MONEY =================
function applyAddMoney(){
  ADD_MONEY = Number($('addMoneyInput').value || 0);
  recalcCash();
}

// ================= SAVE =================
async function saveCash(){
  if(!window.__cash){
    alert("Please click Load first.");
    return;
  }

  const d = window.__cash;

  const fd = new FormData();
  fd.append('api','save_cash_money');
  fd.append('date',$('theDate').value);
  fd.append('add_money', ADD_MONEY);
  fd.append('today_received', d.received);
  fd.append('parchoon', d.parchoon);
  fd.append('today_exp', d.exp);
  fd.append('stock_total', d.stock);
  fd.append('cash_money', d.cash);

  const r = await fetch('cash_in_hand.php',{method:'POST',body:fd}).then(r=>r.json());

  if(r.success){
    alert(r.updated ? 'Updated' : 'Saved');
    $('addMoneyInput').value = '';
    loadAll();
  } else {
    alert(r.message || 'Save failed');
  }
}

// ================= HISTORY =================
async function loadHistory(){
  const r = await fetch('cash_in_hand.php?api=history').then(r=>r.json());
  const tb = $('historyBody');
  const t = $('totalRow');
  tb.innerHTML=''; 
  t.innerHTML='';

  t.innerHTML = `<tr style="font-weight:bold;background:#e3f2fd">
    <td>TOTAL</td>
    <td>${fmt(r.sum.add)}</td>
    <td>${fmt(r.sum.rec)}</td>
    <td>${fmt(r.sum.par)}</td>
    <td>${fmt(r.sum.exp)}</td>
    <td>${fmt(r.sum.stk)}</td>
    <td>${fmt(r.sum.cash)}</td>
  </tr>`;

  r.rows.forEach(x=>{
    tb.innerHTML += `<tr>
      <td>${x.record_date}</td>
      <td>${fmt(x.add_money)}</td>
      <td>${fmt(x.today_received)}</td>
      <td>${fmt(x.parchoon_amount)}</td>
      <td>${fmt(x.today_expenditure)}</td>
      <td>${fmt(x.stock_total)}</td>
      <td><b>${fmt(x.cash_money)}</b></td>
    </tr>`;
  });
}

// ================= TOGGLE =================
function toggleHistory(){
  const b = $('historyBox');
  const btn = event.target;

  if(b.style.display === 'none'){
    b.style.display = 'block';
    btn.innerText = 'Hide';
  } else {
    b.style.display = 'none';
    btn.innerText = 'Show';
  }
}

// ================= SECTION TOGGLE =================
function showSection(type){
  const normal = document.getElementById('sectionNormal');
  const withs = document.getElementById('sectionWith');

  if(type === 'normal'){
    normal.style.display = 'block';
    withs.style.display = 'none';
  } else {
    normal.style.display = 'none';
    withs.style.display = 'block';
  }
}

// ================= TRANSFER SYSTEM =================

async function doTransfer(type){
  const from = document.getElementById('fromAccount').value;
  const to = document.getElementById('toAccount').value;
  let amount = Number(document.getElementById('transferAmount').value || 0);
  const date = document.getElementById('theDate').value;

  if(amount <= 0){
    alert("Enter amount");
    return;
  }

  // If subtract, make it negative
  if(type === 'sub'){
    amount = -amount;
  }

  const fd = new FormData();
  fd.append('api','cihw_add');
  fd.append('date',date);
  fd.append('from',from);
  fd.append('to',to);
  fd.append('amount',amount);

  const r = await fetch('cash_in_hand.php',{method:'POST',body:fd}).then(r=>r.json());

  if(r.success){
    document.getElementById('transferAmount').value = '';
    loadCIHW();
  } else {
    alert('Failed');
  }
}


async function loadCIHW(){
  const selectedDate = document.getElementById('theDate').value;

  const rows = await fetch(`cash_in_hand.php?api=cihw_get_all`)
    .then(r => r.json());

  // per-day data
  let days = {};

  // overall totals
  let grand = { asim:0, shop:0, neak:0 };

  rows.forEach(x=>{
    const d = x.record_date;

    if(!days[d]){
      days[d] = { asim:0, shop:0, neak:0 };
    }

    if(x.from_account === x.to_account){
      days[d][x.to_account] += Number(x.amount);
    } else {
      days[d][x.from_account] -= Number(x.amount);
      days[d][x.to_account] += Number(x.amount);
    }
  });

  const tb = document.getElementById('transferBody');
  tb.innerHTML = '';

  // ===== CALCULATE GRAND TOTALS =====
  Object.values(days).forEach(r=>{
    grand.asim += r.asim;
    grand.shop += r.shop;
    grand.neak += r.neak;
  });

  const grandTotal = grand.asim + grand.shop + grand.neak;


  // ===== DAILY ROWS =====
  let selected = { asim:0, shop:0, neak:0 };

  Object.keys(days).sort().forEach(date=>{
    const r = days[date];
    const total = r.asim + r.shop + r.neak;

    if(date === selectedDate){
      selected = {...r};
    }

    tb.innerHTML += `
     <tr>
       <td>${date}</td>
       <td>${fmt(r.asim)}</td>
       <td>${fmt(r.shop)}</td>
       <td>${fmt(r.neak)}</td>
       <td><b>${fmt(total)}</b></td>
       <td>
         <button class="btn-hide" style="background:#d32f2f" onclick="deleteCIHWDay('${date}')">🗑️ Delete</button>
       </td>
     </tr>
`   ;

  });

    // ===== FIND LAST DATE =====
   const dates = Object.keys(days).sort(); // ascending
   let last = { asim:0, shop:0, neak:0 };

   if(dates.length){
     const lastDate = dates[dates.length - 1];
     last = days[lastDate];
   }

   // ===== UPDATE CARDS WITH LAST DAY =====
   document.getElementById('sum_asim').innerText = fmt(last.asim);
   document.getElementById('sum_shop').innerText = fmt(last.shop);
   document.getElementById('sum_neak').innerText = fmt(last.neak);

}


// ================= INIT =================
window.onload = loadAll;
$('theDate').addEventListener('change', loadAll);

async function deleteCIHWDay(date){
  if(!confirm("Delete all transfers of " + date + " ?")) return;

  const fd = new FormData();
  fd.append('api','cihw_delete_day');
  fd.append('date',date);

  const r = await fetch('cash_in_hand.php',{method:'POST', body:fd}).then(r=>r.json());

  if(r.success){
    alert("Deleted");
    loadCIHW();
  }
}


</script>



</body>
</html>
