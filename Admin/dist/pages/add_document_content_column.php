<?php
include 'config.php';

try {
    // Add document_content column if it doesn't exist
    $sql = "ALTER TABLE research_uploads ADD COLUMN IF NOT EXISTS document_content LONGTEXT";
    $conn->query($sql);
    echo "Document content column added successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
