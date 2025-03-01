<?php
session_start();
include 'config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logError("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    die('Invalid request method');
}

if (!isset($_POST['id']) || !isset($_POST['content'])) {
    logError("Missing required parameters");
    die(json_encode(['success' => false, 'error' => 'Missing required parameters']));
}

$id = $_POST['id'];
$content = $_POST['content'];

// Validate content
if (empty(trim(strip_tags($content)))) {
    logError("Empty content received for document ID: $id");
    die(json_encode(['success' => false, 'error' => 'Content cannot be empty']));
}

// Get the file path from the database
$stmt = $conn->prepare("SELECT file_path FROM research_uploads WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $file_path = $row['file_path'];
    
    try {
        // Set maximum memory limit for large documents
        ini_set('memory_limit', '512M');
        
        // Verify file exists and is writable
        if (!file_exists($file_path)) {
            throw new Exception("Original file not found: $file_path");
        }
        
        if (!is_writable($file_path)) {
            throw new Exception("File is not writable: $file_path");
        }
        
        // Create backup of original file
        $backup_dir = "../backup_docs/";
        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        
        $backup_file = $backup_dir . basename($file_path) . '.' . date('Y-m-d-H-i-s') . '.bak';
        if (!copy($file_path, $backup_file)) {
            throw new Exception("Failed to create backup file");
        }
        
        // Get file extension
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        if ($extension === 'docx') {
            // For .docx files, create a new document
            $phpWord = new PhpWord();
            $section = $phpWord->addSection();
            
            // Clean and prepare HTML content
            $content = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');
            $content = str_replace("\0", "", $content);
            
            // Remove any problematic HTML elements
            $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $content);
            $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', "", $content);
            
            // Add the content
            \PhpOffice\PhpWord\Shared\Html::addHtml($section, $content, false, false);
            
            // Save as Word document
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            if (!$writer->save($file_path)) {
                throw new Exception("Failed to save DOCX file");
            }
        } else {
            // For .doc files, save as RTF
            $content = strip_tags($content, '<p><br><div><span><b><i><u><strong><em>');
            $content = str_replace(["\r\n", "\r", "\n"], "", $content);
            $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
            $content = preg_replace('/<\/p>/i', "\n\n", $content);
            $content = strip_tags($content);
            
            // Create RTF content
            $rtf = "{\\rtf1\\ansi\\deff0\n";
            $rtf .= "{\\fonttbl{\\f0\\froman Times New Roman;}}\n";
            $rtf .= "\\f0\\fs24\n";
            $rtf .= str_replace("\n", "\\par ", $content);
            $rtf .= "}";
            
            if (file_put_contents($file_path, $rtf) === false) {
                throw new Exception("Failed to save RTF file");
            }
        }
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        logError("Error saving document ID: $id, File: $file_path", $e);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    logError("Document not found in database for ID: $id");
    echo json_encode(['success' => false, 'error' => 'Document not found in database']);
}
?>
