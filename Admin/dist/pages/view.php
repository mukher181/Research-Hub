<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', 1);

// Debug information
$autoloaderPath = __DIR__ . '/../../../vendor/autoload.php';
if (!file_exists($autoloaderPath)) {
    die("Autoloader not found at: " . $autoloaderPath);
}

// Load the autoloader
require_once $autoloaderPath;
require_once 'config.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\PhpWord;

// Check if file ID is provided
if (!isset($_GET['id'])) {
    die('File ID not provided');
}

$fileId = $_GET['id'];

// Get file details from database
try {
    $stmt = $conn->prepare("SELECT file_name, file_path, document_content FROM research_uploads WHERE id = ?");
    $stmt->bind_param("i", $fileId);
    $stmt->execute();
    $result = $stmt->get_result();
    $file = $result->fetch_assoc();

    if (!$file) {
        die('File not found in database');
    }

    $filePath = $file['file_path'];
    $documentContent = $file['document_content'];
    
    // Check if file exists
    if (!file_exists($filePath)) {
        die('File does not exist on server: ' . htmlspecialchars($filePath));
    }

    // Prefer document_content if available, otherwise fall back to file
    if (!empty($documentContent)) {
        // If document_content exists, use it directly
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Document Viewer - <?php echo htmlspecialchars($file['file_name']); ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    line-height: 1.6; 
                    padding: 20px; 
                    max-width: 800px; 
                    margin: 0 auto; 
                }
                img { 
                    max-width: 100%; 
                    height: auto; 
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-bottom: 15px; 
                }
                table, th, td { 
                    border: 1px solid #ddd; 
                    padding: 8px; 
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1 class="mb-4"><?php echo htmlspecialchars($file['file_name']); ?></h1>
                <a href="view_papers.php" class="btn btn-secondary">Back to View Papers</a>
                <a href="total_papers.php" class="btn btn-secondary">Back to Total Papers</a>
                <div class="document-content">
                    <?php echo $documentContent; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }

    // If no document_content, fall back to original file processing
    // Configure HTML writer
    Settings::setOutputEscapingEnabled(true);
    
    // Load the document
    try {
        $phpWord = IOFactory::load($filePath);
    } catch (Exception $e) {
        die('Error loading document: ' . htmlspecialchars($e->getMessage()));
    }

    // Start output buffering
    ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Viewer - <?php echo htmlspecialchars($file['file_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .document-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        img { max-width: 100%; height: auto; }
        table { width: 100%; border-collapse: collapse; margin: 1em 0; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        pre { white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="container">
        <div class="mb-4">
            <h1 class="h3"><?php echo htmlspecialchars($file['file_name']); ?></h1>
            <div>
            <a href="view_papers.php" class="btn btn-secondary">Back to View Papers</a>
            </div><br>
            <div>
            <a href="total_papers.php" class="btn btn-secondary">Back to Total Papers</a>
            </div>
        </div>
        <div class="document-container">
            <?php
            try {
                $htmlWriter = new \PhpOffice\PhpWord\Writer\HTML($phpWord);
                $htmlWriter->save('php://output');
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">Error converting document to HTML: ' . 
                     htmlspecialchars($e->getMessage()) . '</div>';
                echo '<div class="alert alert-info">You can still download the original document to view it.</div>';
            }
            ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
    // Get the buffered content and display it
    $content = ob_get_clean();
    echo $content;

} catch (Exception $e) {
    die('Error: ' . htmlspecialchars($e->getMessage()));
}
?>