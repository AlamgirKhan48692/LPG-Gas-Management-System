<?php
header('Content-Type: application/json');
include 'db_connect.php';

$data = [];

$sql = "SELECT * FROM stock_data ORDER BY date DESC, id DESC";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {

        $row['kg15'] = floatval($row['kg15']);
        $row['kg45'] = floatval($row['kg45']);
        $row['rate'] = floatval($row['rate']);

        // Base rate calculation
        $row['baseRate'] = round(($row['rate'] / 11.8), 2);

        // Cost calculations
        $row['cost15'] = round(($row['baseRate'] * 15), 2);
        $row['cost45'] = round(($row['baseRate'] * 45.4), 2);

        // Amount calculations
        $row['amount15'] = round(($row['cost15'] * $row['kg15']), 2);
        $row['amount45'] = round(($row['cost45'] * $row['kg45']), 2);

        $row['totalAmount'] = round(($row['amount15'] + $row['amount45']), 2);

        $data[] = $row;
    }
}

echo json_encode($data);
$conn->close();
?>
