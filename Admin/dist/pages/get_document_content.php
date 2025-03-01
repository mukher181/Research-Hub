<?php
session_start();
include 'config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

// Suppress deprecation warnings
error_reporting(E_ALL & ~E_DEPRECATED);

// Function to log errors
function logError($message, $error = null) {
    $logFile = __DIR__ . '/../logs/document_errors.log';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $errorMessage = "[$timestamp] $message";
    if ($error) {
        $errorMessage .= "\nError: " . $error->getMessage();
        $errorMessage .= "\nStack Trace: " . $error->getTraceAsString();
    }
    $errorMessage .= "\n----------------------------------------\n";
    
    file_put_contents($logFile, $errorMessage, FILE_APPEND);
}

if (!isset($_GET['id'])) {
    logError("No document ID provided");
    die('No document ID provided');
}

$id = $_GET['id'];

// Get the file path and document content from the database
$stmt = $conn->prepare("SELECT file_path, document_content FROM research_uploads WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Prefer document_content if available
    if (!empty($row['document_content'])) {
        echo $row['document_content'];
        exit();
    }

    $file_path = $row['file_path'];
    
    if (file_exists($file_path)) {
        try {
            // Set maximum memory limit for large documents
            ini_set('memory_limit', '512M');
            
            // Load the document
            $phpWord = IOFactory::load($file_path);
            
            // Create temporary directory for media
            $tempDir = sys_get_temp_dir() . '/phpword_media_' . uniqid();
            if (!file_exists($tempDir)) {
                mkdir($tempDir);
            }
            
            // Convert to HTML
            $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
            
            // Save to temporary file
            $tempFile = $tempDir . '/output.html';
            $htmlWriter->save($tempFile);
            
            // Read the HTML content
            $html = file_get_contents($tempFile);
            
            // Clean up temporary files
            array_map('unlink', glob("$tempDir/*.*"));
            rmdir($tempDir);
            
            // Output the processed HTML
            echo $html;
            
        } catch (Exception $e) {
            logError("Error processing document", $e);
            echo "Error processing document: " . $e->getMessage();
        }
    } else {
        logError("File not found: $file_path");
        echo "File not found";
    }
} else {
    logError("Document not found in database for ID: $id");
    echo "Document not found in database";
}
?>
