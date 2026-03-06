<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
$date = trim($date);

if ($date === '') {
    echo json_encode([
        "status" => "error",
        "msg" => "date_missing"
    ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT rate_15kg 
    FROM daily_rate 
    WHERE rate_date = ?
    LIMIT 1
");
$stmt->bind_param("s", $date);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        "status" => "ok",
        "rate"   => (float)$row['rate_15kg']
    ]);
} else {
    echo json_encode([
        "status" => "none"
    ]);
}

$stmt->close();
