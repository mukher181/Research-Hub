<?php
header('Content-Type: application/json');

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "research";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$ids = $data['ids'] ?? [];

if (empty($ids)) {
    die(json_encode(['success' => false, 'message' => 'No items selected']));
}

$response = ['success' => false, 'message' => ''];

// Convert ids array to string for SQL
$idList = implode(',', array_map('intval', $ids));

switch ($action) {
    case 'delete':
        // First, get file paths to delete actual files
        $query = "SELECT file_path FROM research_uploads WHERE id IN ($idList)";
        $result = $conn->query($query);
        
        while ($row = $result->fetch_assoc()) {
            if (file_exists($row['file_path'])) {
                unlink($row['file_path']);
            }
        }
        
        // Then delete records
        $query = "DELETE FROM research_uploads WHERE id IN ($idList)";
        if ($conn->query($query)) {
            $response = ['success' => true, 'message' => 'Selected items deleted successfully'];
        }
        break;

    case 'activate':
        $query = "UPDATE research_uploads SET status = 'active' WHERE id IN ($idList)";
        if ($conn->query($query)) {
            $response = ['success' => true, 'message' => 'Selected items activated successfully'];
        }
        break;

    case 'deactivate':
        $query = "UPDATE research_uploads SET status = 'inactive' WHERE id IN ($idList)";
        if ($conn->query($query)) {
            $response = ['success' => true, 'message' => 'Selected items deactivated successfully'];
        }
        break;

    default:
        $response = ['success' => false, 'message' => 'Invalid action'];
}

echo json_encode($response);