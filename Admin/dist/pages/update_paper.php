<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update basic information
        $sql = "UPDATE research_uploads SET title = ?, description = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $title, $description, $id);
        $stmt->execute();

        // Handle file upload if a new file is provided
        if (isset($_FILES['file']) && $_FILES['file']['size'] > 0) {
            // Get the old file path to delete later
            $sql = "SELECT file_path FROM research_uploads WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $old_file = $result->fetch_assoc();

            // Process new file upload
            $file_name = $_FILES['file']['name'];
            $file_type = $_FILES['file']['type'];
            $upload_dir = "../uploads/";
            
            // Create upload directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_file_name = time() . '_' . uniqid() . '.' . $file_extension;
            $new_file_path = $upload_dir . $new_file_name;

            // Move uploaded file
            if (move_uploaded_file($_FILES['file']['tmp_name'], $new_file_path)) {
                // Update database with new file information
                $sql = "UPDATE research_uploads SET file_name = ?, file_path = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $file_name, $new_file_path, $id);
                $stmt->execute();

                // Delete old file if it exists
                if ($old_file && file_exists($old_file['file_path'])) {
                    unlink($old_file['file_path']);
                }
            } else {
                throw new Exception("Failed to upload file.");
            }
        }

        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Research paper updated successfully!";
        header("Location: total_papers.php");
        exit();

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = "Error updating research paper: " . $e->getMessage();
        header("Location: total_papers.php");
        exit();
    }
}

header("Location: total_papers.php");
exit();
?>