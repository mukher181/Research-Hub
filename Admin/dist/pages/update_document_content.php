<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = array();
    
    try {
        if (!isset($_POST['id']) || !isset($_POST['content'])) {
            throw new Exception("Missing required parameters");
        }
        
        $id = $_POST['id'];
        $content = $_POST['content'];
        
        // Update document content
        $sql = "UPDATE research_uploads SET document_content = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $content, $id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "Document content updated successfully!";
        } else {
            throw new Exception("Failed to update document content");
        }
        
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = "Error: " . $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
} else {
    $response['success'] = false;
    $response['message'] = "Invalid request method";
    echo json_encode($response);
    exit();
}
?>
