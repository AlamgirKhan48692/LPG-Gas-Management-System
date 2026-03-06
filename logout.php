<?php
// 🧠 Start the session
session_start();

// 🧹 Clear all session data
$_SESSION = [];

// ❌ Destroy the session completely
session_destroy();

// 🚀 Redirect to login page
header("Location: login.html");
exit();
?>
