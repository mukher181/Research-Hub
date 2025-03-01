<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$username = $_SESSION['username'];

// Get papers shared with the user or uploaded by the user
$query = "SELECT DISTINCT paper_id 
          FROM (
              SELECT paper_id FROM paper_shares WHERE shared_with = ?
              UNION
              SELECT id AS paper_id FROM research_uploads WHERE uploaded_by = ?
          ) AS user_papers";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

$user_papers = [];
while ($row = $result->fetch_assoc()) {
    $user_papers[] = $row['paper_id'];
}

if (empty($user_papers)) {
    echo json_encode(['papers_with_new_messages' => []]);
    exit;
}

// Prepare the list of papers as a comma-separated string for the IN clause
$papers_list = implode(',', $user_papers);

// Add this query to find papers with new messages
$new_messages_query = "
    SELECT DISTINCT pac.paper_id
    FROM paper_access_chat pac
    LEFT JOIN paper_message_views pmv ON pac.paper_id = pmv.paper_id AND pmv.username = ?
    WHERE pac.paper_id IN ($papers_list)
    AND (pmv.last_viewed IS NULL OR pac.timestamp > pmv.last_viewed)
    AND pac.sender_username != ?
";
$stmt = $conn->prepare($new_messages_query);
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$new_messages_result = $stmt->get_result();

$papers_with_new_messages = [];
while ($row = $new_messages_result->fetch_assoc()) {
    $papers_with_new_messages[] = $row['paper_id'];
}

// Return the papers with new messages
echo json_encode([
    'papers_with_new_messages' => $papers_with_new_messages
]);
exit;
?>