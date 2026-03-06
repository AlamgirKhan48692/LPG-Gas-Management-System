<?php
include 'db_connect.php';
header('Content-Type: application/json');

$sql = "
  SELECT
    COALESCE(SUM(total),0) AS sale,
    COALESCE(SUM(receivedAmount),0) AS received,
    COALESCE(SUM(amountRemaining),0) AS remaining
  FROM sale_details
";

$res = $conn->query($sql);
$row = $res->fetch_assoc();

echo json_encode([
  'sale'      => (float)$row['sale'],
  'received'  => (float)$row['received'],
  'remaining' => (float)$row['remaining']
]);
?>
