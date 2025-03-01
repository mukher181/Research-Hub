<?php
session_start();
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('HTTP/1.1 403 Forbidden');
    die('Access denied');
}

if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileType = $file['type'];
    
    // Only allow image files
    if (!in_array($fileType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
        header('HTTP/1.1 400 Bad Request');
        die('Invalid file type');
    }
    
    // Generate unique filename
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    $uniqueName = uniqid() . '.' . $extension;
    
    // Create uploads directory if it doesn't exist
    $uploadDir = '../../../uploads/images/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Move file to uploads directory
    $uploadPath = $uploadDir . $uniqueName;
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Return the URL for TinyMCE
        $location = 'uploads/images/' . $uniqueName;
        echo json_encode(['location' => $location]);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        die('Failed to upload file');
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    die('No file uploaded');
}
