<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Content-Type: application/json");
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "SESSION_EXPIRED"
    ]);
    exit;
}

include 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_POST['date'], $_POST['rate'])) {
    echo json_encode(["status" => "error", "msg" => "missing"]);
    exit;
}

$date = $_POST['date'];
$rate = floatval($_POST['rate']);

if ($rate <= 0) {
    echo json_encode(["status" => "error", "msg" => "invalid_rate"]);
    exit;
}

/* ✅ TABLE NAME FIXED HERE */
$check = $conn->prepare("SELECT rate_date FROM daily_rate WHERE rate_date=?");
$check->bind_param("s", $date);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode(["status" => "locked"]);
    exit;
}
$check->close();

/* ✅ insert */
$rateStr = number_format($rate, 2, '.', '');

$ins = $conn->prepare("
    INSERT INTO daily_rate (rate_date, rate_15kg, created_at)
    VALUES (?, ?, NOW())
");
$ins->bind_param("ss", $date, $rateStr);

if ($ins->execute()) {
    echo json_encode(["status" => "saved"]);
} else {
    echo json_encode([
        "status" => "error",
        "sql_error" => $ins->error
    ]);
}
$ins->close();
