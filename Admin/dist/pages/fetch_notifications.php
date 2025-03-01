<?php
session_start();
include 'config.php';
include 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not logged in'
    ]);
    exit();
}

// Get user role from session
$user_role = $_SESSION['role'] ?? 'user';

// Fetch notifications
$chatNotifications = getChatNotifications($conn, $user_role);
$otherNotifications = getOtherNotifications($conn, $user_role);

// Return notifications as JSON
echo json_encode([
    'status' => 'success',
    'chatNotifications' => $chatNotifications,
    'otherNotifications' => $otherNotifications
]);
exit();