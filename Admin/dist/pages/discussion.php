<?php
session_start();
include 'config.php';

// Debugging: Log all incoming requests
error_log("Discussion Request - Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Session Username: " . ($_SESSION['username'] ?? 'Not Set'));
error_log("POST Data: " . print_r($_POST, true));

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    error_log("Unauthorized access attempt");
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access', 'details' => 'No username in session']);
    exit();
}

// Handle sending a message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = $_POST['message'] ?? '';
    $sender = $_SESSION['username'];

    if (empty($message)) {
        error_log("Empty message attempt");
        http_response_code(400);
        echo json_encode(['error' => 'Message cannot be empty']);
        exit();
    }

    // Debugging: Check database connection
    if (!$conn) {
        error_log("Database connection failed: " . mysqli_connect_error());
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed', 'details' => mysqli_connect_error()]);
        exit();
    }

    // Create table if not exists
    $create_table_sql = "CREATE TABLE IF NOT EXISTS research_discussions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender VARCHAR(100) NOT NULL,
        real_username VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        timestamp DATETIME NOT NULL
    )";
    if (!$conn->query($create_table_sql)) {
        error_log("Table creation failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create table', 'details' => $conn->error]);
        exit();
    }

    // Check if sender is admin and replace display name
    $display_sender = isset($_SESSION['role']) && $_SESSION['role'] === 'admin' ? 'admin' : $sender;
    $real_username = $_SESSION['username'];

    $sql = "INSERT INTO research_discussions (sender, real_username, message, timestamp) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Prepare statement failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to prepare statement', 'details' => $conn->error]);
        exit();
    }

    $stmt->bind_param("sss", $display_sender, $real_username, $message);

    if ($stmt->execute()) {
        error_log("Message sent successfully");
        echo json_encode(['status' => 'success', 'message' => 'Message sent']);
    } else {
        error_log("Message send failed: " . $stmt->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to send message', 'details' => $stmt->error]);
    }
    exit();
}

// Fetch messages
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $last_id = $_GET['last_id'] ?? 0;
    
    $sql = "SELECT id, sender, real_username, message, timestamp 
            FROM research_discussions 
            WHERE id > ? 
            ORDER BY timestamp ASC 
            LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $last_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        // Add real username to the message for potential future use
        $row['real_username'] = $row['real_username'];
        $messages[] = $row;
    }

    echo json_encode($messages);
    exit();
}
?>