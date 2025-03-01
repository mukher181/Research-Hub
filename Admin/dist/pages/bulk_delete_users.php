<?php
session_start();
include 'config.php';

// Check if user is admin
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$data = json_decode(file_get_contents('php://input'), true);
$userIds = $data['userIds'] ?? [];

if (empty($userIds)) {
    die(json_encode(['success' => false, 'message' => 'No users selected']));
}

// Convert array to comma-separated string for SQL
$userIdString = implode(',', array_map('intval', $userIds));

// Delete users
$sql = "DELETE FROM users WHERE id IN ($userIdString) AND role != 'admin'";
$result = mysqli_query($conn, $sql);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Users deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting users']);
} 