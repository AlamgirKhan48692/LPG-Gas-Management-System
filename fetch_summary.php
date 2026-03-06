<?php
include 'db_connect.php';

header('Content-Type: application/json');

$page = $_GET['page'] ?? '';

$result = [];

if ($page === 'stock') {
  $r = $conn->query("
    SELECT 
      IFNULL(SUM(kg15),0) AS k15,
      IFNULL(SUM(kg45),0) AS k45,
      IFNULL(SUM(kg15 * cost15),0) AS total15amount,
      IFNULL(SUM(kg45 * cost45),0) AS total45amount
    FROM stock_data
  ");
  $data = $r ? $r->fetch_assoc() : ['k15'=>0,'k45'=>0,'total15amount'=>0,'total45amount'=>0];
  $data['total'] = $data['k15'] + $data['k45'];
  $data['grand_amount'] = $data['total15amount'] + $data['total45amount'];
  $result = ['type'=>'stock','data'=>$data];
}

elseif ($page === 'sale') {
  $r = $conn->query("
    SELECT 
      IFNULL(SUM(total),0) AS total,
      IFNULL(SUM(receivedAmount),0) AS received,
      IFNULL(SUM(amountRemaining),0) AS remaining
    FROM sale_record
  ");
  $result = ['type'=>'sale','data'=>$r ? $r->fetch_assoc() : ['total'=>0,'received'=>0,'remaining'=>0]];
}

elseif ($page === 'expenditure') {
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
  $data['total_exp'] = $data['vehicle'] + $data['shop'] + $data['salaries'] + $data['other'];
  $result = ['type'=>'expenditure','data'=>$data];
}

echo json_encode($result);
?>
