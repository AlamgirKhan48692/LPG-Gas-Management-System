<?php
header('Content-Type: application/json');
include 'db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status'=>'error','message'=>'No input']);
    exit;
}

$date = $input['date'] ?? '';
$plantName = $input['plantName'] ?? '';

$kg12 = floatval($input['kg12'] ?? 0);
$kg15 = floatval($input['kg15'] ?? 0);
$kg45 = floatval($input['kg45'] ?? 0);

$rate = floatval($input['rate'] ?? 0);
$baseRate = floatval($input['baseRate'] ?? 0);

$cost12 = floatval($input['cost12'] ?? 0);
$cost15 = floatval($input['cost15'] ?? 0);
$cost45 = floatval($input['cost45'] ?? 0);

$amount12 = floatval($input['amount12'] ?? 0);
$amount15 = floatval($input['amount15'] ?? 0);
$amount45 = floatval($input['amount45'] ?? 0);

$totalAmount = floatval($input['totalAmount'] ?? 0);

$sql = "
INSERT INTO stock_data (
    date, plantName,
    kg12, kg15, kg45,
    rate, baseRate,
    cost12, cost15, cost45,
    amount12, amount15, amount45,
    totalAmount
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['status'=>'error', 'message'=>'Prepare failed: '.$conn->error]);
    exit;
}

$stmt->bind_param(
    "ssdddddddddddd",
    $date, $plantName,
    $kg12, $kg15, $kg45,
    $rate, $baseRate,
    $cost12, $cost15, $cost45,
    $amount12, $amount15, $amount45,
    $totalAmount
);


if ($stmt->execute()) {
    echo json_encode(['status'=>'success','message'=>'Saved']);
} else {
    echo json_encode(['status'=>'error','message'=>$stmt->error]);
}

$stmt->close();
$conn->close();
?>