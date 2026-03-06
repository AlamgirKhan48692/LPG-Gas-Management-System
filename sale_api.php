<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

if ($action === 'customer_history' && !empty($_GET['name'])) {
    $name = trim($_GET['name']);

    $stmt = $conn->prepare("
    SELECT 
        DATE(date) AS date,
        kg12, kg15, kg45,
        kg12Received, kg15Received, kg45Received,
        total, receivedAmount, amountRemaining,
        totalCylinderRemaining,
        sale_rate, base_rate, margin, profit
    FROM sale_details
    WHERE TRIM(LOWER(name)) = TRIM(LOWER(?))
    ORDER BY date ASC
");

    $stmt->bind_param("s", $name);
    $stmt->execute();

    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;

    echo json_encode($out);
    exit;
}

echo json_encode([]);
exit;
