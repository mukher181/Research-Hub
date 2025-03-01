<?php
session_start();
// Include database configuration
include 'config.php';

// Update the last login record with logout timestamp
$logout_sql = "UPDATE user_login_history 
               SET logout_timestamp = NOW() 
               WHERE username = ? AND logout_timestamp IS NULL 
               ORDER BY login_timestamp DESC 
               LIMIT 1";
$stmt = $conn->prepare($logout_sql);
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
session_destroy();
header("location: login.php");
exit;
?>