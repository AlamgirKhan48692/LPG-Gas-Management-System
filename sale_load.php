<?php
include 'db_connect.php';
header('Content-Type: application/json');

$sql = "
  SELECT 
    id,
    name,
    DATE_FORMAT(date, '%Y-%m-%d') AS date,

    kg12,
    kg15,
    kg45,

    total,
    receivedAmount,
    amountRemaining,

    kg12Received,
    kg15Received,
    kg45Received,

    totalCylinderRemaining
  FROM sale_details
  ORDER BY date DESC, id DESC
";

$res = $conn->query($sql);
$data = [];

while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
exit;
?>