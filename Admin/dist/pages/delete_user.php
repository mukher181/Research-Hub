<?php
// delete_user.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the user ID from the request body
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $data['id'];

    // Database connection
    $pdo = new PDO('mysql:host=localhost;dbname=research', 'root', '');
    
    // Delete user query
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}
?>