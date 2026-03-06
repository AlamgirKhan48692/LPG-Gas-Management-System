<?php
$db_host = "localhost";   // Localhost
$db_user = "root";        // Default MySQL user
$db_pass = "";            // Default password (empty for XAMPP/WAMP)
$db_name = "insaf_lpg_db";       // Your local database name

/* -------------------------
   CONNECT
   ------------------------- */
$conn = mysqli_connect("localhost","your_db_user","your_password","your_database");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
