<?php
ob_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header("Content-Type: application/json");

if (!isset($_SESSION['username'])) {
    echo json_encode(["status"=>"error","message"=>"SESSION_EXPIRED"]);
    exit;
}

require_once "db_connect.php";

/* ================= READ INPUT ================= */
$raw = file_get_contents("php://input");

if (!$raw) {
    echo json_encode(["status"=>"error","message"=>"NO_INPUT"]);
    exit;
}

$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode(["status"=>"error","message"=>"INVALID_JSON"]);
    exit;
}

/* ================= INPUTS ================= */
$name = trim($data['name'] ?? '');
$details = trim($data['details'] ?? '');
if ($details === '') {
    $details = 'No';
}

$date = $data['date'] ?? date('Y-m-d');

$kg12 = (int)($data['kg12'] ?? 0);
$kg15 = (int)($data['kg15'] ?? 0);
$kg45 = (int)($data['kg45'] ?? 0);

$kg12Rec = (int)($data['kg12Received'] ?? 0);
$kg15Rec = (int)($data['kg15Received'] ?? 0);
$kg45Rec = (int)($data['kg45Received'] ?? 0);

$total     = (float)($data['total'] ?? 0);
$received  = (float)($data['receivedAmount'] ?? 0);
$remaining = (float)($data['amountRemaining'] ?? 0);

$cylRem = (int)($data['totalCylinderRemaining'] ?? 0);

$sale_rate = (float)($data['sale_rate'] ?? 0);
$base_rate = (float)($data['base_rate'] ?? 0);
$margin    = (float)($data['margin'] ?? 0);
$profit    = $margin;

/* ================= VALIDATION ================= */
if ($name === '') {
    echo json_encode(["status"=>"error","message"=>"NAME_REQUIRED"]);
    exit;
}

/* ================= INSERT SALE ================= */
$stmt = $conn->prepare("
INSERT INTO sale_details
(
 name,
 details,
 date,
 kg12,
 kg15,
 kg45,
 kg12Received,
 kg15Received,
 kg45Received,
 total,
 receivedAmount,
 amountRemaining,
 totalCylinderRemaining,
 sale_rate,
 base_rate,
 margin,
 profit
)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

$stmt->bind_param(
    "sssiiiiiidddidddd",
    $name,
    $details,
    $date,

    $kg12,
    $kg15,
    $kg45,

    $kg12Rec,
    $kg15Rec,
    $kg45Rec,

    $total,
    $received,
    $remaining,

    $cylRem,

    $sale_rate,
    $base_rate,
    $margin,
    $profit
);

$stmt->execute();
$sale_id = $stmt->insert_id;
$stmt->close();

/* ================= STOCK AUTO-DEDUCT ================= */
/*
  BUSINESS RULE:
  - ONLY SOLD QTY deducts stock
  - RECEIVED cylinders do NOT affect stock
*/
$plantName = "AUTO_DEDUCT - {$name} (sale_id:$sale_id)";

/* prevent duplicate auto-deduct */
$check = $conn->prepare("
    SELECT id FROM stock_data
    WHERE plantName = ?
    LIMIT 1
");
$check->bind_param("s", $plantName);
$check->execute();
$check->store_result();

if ($check->num_rows === 0 && ($kg12 || $kg15 || $kg45)) {

    $zero = 0;
    $neg12 = -$kg12;
    $neg15 = -$kg15;
    $neg45 = -$kg45;

    $stk = $conn->prepare("
        INSERT INTO stock_data (
            date,
            plantName,
            kg12,
            kg15,
            kg45,
            rate,
            cost12,
            cost15,
            cost45,
            totalAmount
        )
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");

    $stk->bind_param(
        "ssiiiddddd",
        $date,
        $plantName,
        $neg12,
        $neg15,
        $neg45,
        $zero,
        $zero,
        $zero,
        $zero,
        $zero
    );

    $stk->execute();
    $stk->close();
}

$check->close();

/* ================= DONE ================= */
echo json_encode([
    "status" => "success",
    "sale_id" => $sale_id
]);

ob_end_flush();
exit;
?>
