<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paper_id = $_POST['paper_id'];
    $researcher = $_POST['researcher'];
    $access_type = $_POST['access_type'];
    
    // Get researcher ID
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $researcher);
    $stmt->execute();
    $result = $stmt->get_result();
    $researcher_data = $result->fetch_assoc();
    $researcher_id = $researcher_data['id'];
    
    // Check if access already exists
    $stmt = $conn->prepare("SELECT id FROM research_access WHERE paper_id = ? AND researcher_id = ?");
    $stmt->bind_param("ii", $paper_id, $researcher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing access
        $stmt = $conn->prepare("UPDATE research_access SET access_type = ? WHERE paper_id = ? AND researcher_id = ?");
        $stmt->bind_param("sii", $access_type, $paper_id, $researcher_id);
    } else {
        // Insert new access
        $stmt = $conn->prepare("INSERT INTO research_access (paper_id, researcher_id, access_type) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $paper_id, $researcher_id, $access_type);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Access shared successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error sharing access']);
    }
} 

function openShareModal(paperId) {
    document.getElementById('paper_id').value = paperId;
    document.getElementById('shareModal').style.display = 'block';
}

// Add to existing modal close handlers
window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = 'none';
    }
    if (event.target == document.getElementById('shareModal')) {
        document.getElementById('shareModal').style.display = 'none';
    }
}