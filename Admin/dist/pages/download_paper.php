<?php
session_start();
include 'config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\PhpWord;

// Check if user is not logged in
if (!isset($_SESSION['username']) && !isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

// Get the file information from the database
$stmt = $conn->prepare("SELECT file_path, file_name, document_content FROM research_uploads WHERE id = ?");
$stmt->bind_param("i", $_GET['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $file = $result->fetch_assoc();
    $filepath = $file['file_path'];
    $filename = $file['file_name'];
    $document_content = $file['document_content'];

    // Sanitize the filename
    $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename) . '_' . date('Y-m-d') . '.docx';

    // If document content exists, generate Word file
    if (!empty($document_content)) {
        try {
            // Create a new PhpWord instance
            $phpWord = new PhpWord();
            $section = $phpWord->addSection();

            // Convert HTML to Word document
            Html::addHtml($section, $document_content, false, false);

            // Set headers for Word download
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            header('Pragma: public');
            header('Expires: 0');

            // Save and output file
            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
            ob_clean(); // Clear output buffer to prevent corruption
            flush();
            $objWriter->save('php://output');
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error generating document: " . $e->getMessage();
            header('Location: total_papers.php');
            exit();
        }
    }

    // If no document_content, fall back to original file
    if (!file_exists($filepath)) {
        $_SESSION['error'] = "File not found.";
        header('Location: total_papers.php');
        exit();
    }

    // Serve the existing Word file
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Expires: 0');

    // Clear output buffer to prevent corruption
    ob_clean();
    flush();

    // Read file and output to user
    readfile($filepath);
    exit();
} else {
    $_SESSION['error'] = "Invalid file request.";
    header('Location: total_papers.php');
    exit();
}
?>
