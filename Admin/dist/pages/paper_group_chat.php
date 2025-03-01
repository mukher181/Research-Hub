<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    die("Unauthorized access");
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Send message
    $paper_id = isset($_POST['paper_id']) ? intval($_POST['paper_id']) : 0;
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $username = $_SESSION['username'];

    // Validate input
    if ($paper_id <= 0 || empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }

    // Check paper access
    $access_query = "SELECT 1 FROM (
                        SELECT shared_with AS username FROM paper_shares WHERE paper_id = ?
                        UNION
                        SELECT uploaded_by FROM research_uploads WHERE id = ?
                    ) AS paper_access
                    WHERE username = ?";
    $stmt = $conn->prepare($access_query);
    $stmt->bind_param("iis", $paper_id, $paper_id, $username);
    $stmt->execute();
    $access_result = $stmt->get_result();

    if ($access_result->num_rows == 0) {
        echo json_encode(['success' => false, 'error' => 'No access to this paper']);
        exit;
    }

    // Insert message
    $insert_query = "INSERT INTO paper_access_chat (paper_id, sender_username, message) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("iss", $paper_id, $username, $message);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send message']);
    }
    exit;
}

// Handle GET request for messages
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_messages') {
    $paper_id = isset($_GET['paper_id']) ? intval($_GET['paper_id']) : 0;
    $username = $_SESSION['username'];

    // Check paper access
    $access_query = "SELECT 1 FROM (
                        SELECT shared_with AS username FROM paper_shares WHERE paper_id = ?
                        UNION
                        SELECT uploaded_by FROM research_uploads WHERE id = ?
                    ) AS paper_access
                    WHERE username = ?";
    $stmt = $conn->prepare($access_query);
    $stmt->bind_param("iis", $paper_id, $paper_id, $username);
    $stmt->execute();
    $access_result = $stmt->get_result();

    if ($access_result->num_rows == 0) {
        echo json_encode(['error' => 'No access to this paper']);
        exit;
    }

    // Fetch messages for this paper
    $query = "SELECT pac.*, u.Image 
              FROM paper_access_chat pac
              JOIN users u ON pac.sender_username = u.Username
              WHERE pac.paper_id = ?
              ORDER BY pac.timestamp";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $paper_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }

    echo json_encode($messages);
    exit;
}

// Get the paper ID from URL
$paper_id = isset($_GET['paper_id']) ? intval($_GET['paper_id']) : 0;

if ($paper_id <= 0) {
    die("Invalid paper ID");
}

// Check if user has access to this paper
$username = $_SESSION['username'];
$access_query = "SELECT 1 FROM (
                    SELECT shared_with AS username FROM paper_shares WHERE paper_id = ?
                    UNION
                    SELECT uploaded_by FROM research_uploads WHERE id = ?
                ) AS paper_access
                WHERE username = ?";
$stmt = $conn->prepare($access_query);
$stmt->bind_param("iis", $paper_id, $paper_id, $username);
$stmt->execute();
$access_result = $stmt->get_result();

if ($access_result->num_rows == 0) {
    die("You do not have access to this paper's group chat");
}

// Get paper details with uploader information
$paper_query = "SELECT ru.id, ru.title, ru.uploaded_by 
                FROM research_uploads ru 
                WHERE ru.id = ?";
$stmt = $conn->prepare($paper_query);
$stmt->bind_param("i", $paper_id);
$stmt->execute();
$paper_result = $stmt->get_result();
$paper = $paper_result->fetch_assoc();

// Get users with access to this paper, including the uploader
$users_query = "SELECT DISTINCT u.* FROM users u 
                JOIN (
                    SELECT shared_with AS username FROM paper_shares WHERE paper_id = ?
                    UNION
                    SELECT uploaded_by FROM research_uploads WHERE id = ?
                ) ps ON u.Username = ps.username";
$stmt = $conn->prepare($users_query);
$stmt->bind_param("ii", $paper_id, $paper_id);
$stmt->execute();
$users_result = $stmt->get_result();
$access_users = $users_result->fetch_all(MYSQLI_ASSOC);

// Add this at the end of the file, after loading messages
// Update last viewed time
$update_view_query = "INSERT INTO paper_message_views (username, paper_id) 
                      VALUES (?, ?) 
                      ON DUPLICATE KEY UPDATE last_viewed = CURRENT_TIMESTAMP";
$stmt = $conn->prepare($update_view_query);
$stmt->bind_param("si", $username, $paper_id);
$stmt->execute();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Paper Group Chat - <?= htmlspecialchars($paper['title']) ?></title>
    
    <!-- Ensure Font Awesome is loaded -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
        }
        .chat-container {
            max-width: 800px;
            margin: 20px auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .chat-header {
            background-color: #c43b68;
            color: white;
            padding: 15px;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .chat-messages {
            height: 500px;
            overflow-y: auto;
            padding: 15px;
            display: flex;
            flex-direction: column;
            background-color: #f0f0f0;
        }
        .message {
            max-width: 70%;
            margin: 5px 0;
            padding: 8px 12px;
            border-radius: 12px;
            clear: both;
            position: relative;
        }
        .message.sent {
            background-color: #dcf8c6;
            align-self: flex-end;
            margin-left: auto;
        }
        .message.received {
            background-color: white;
            align-self: flex-start;
        }
        .message-sender {
            font-weight: bold;
            margin-bottom: 4px;
            font-size: 0.9em;
        }
        .message-sender.uploader {
            color: #25d366; /* WhatsApp-like green for admin/uploader */
        }
        .message-time {
            font-size: 0.7em;
            color: #999;
            text-align: right;
            margin-top: 4px;
        }
        .user-list {
            background-color: #f8f9fa;
            padding: 15px;
            border-right: 1px solid #e0e0e0;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .uploader {
            font-weight: bold;
        }
        /* Update message input and send button styles */
        .message-input-container {
            display: flex;
            align-items: center;
            padding: 10px;
            background-color: #f0f2f5;
            border-top: 1px solid #e0e0e0;
        }
        #messageInput {
            flex-grow: 1;
            margin-right: 10px;
            border: 1px solid #ccc;
            border-radius: 20px;
            padding: 10px 15px;
            font-size: 16px;
            transition: border-color 0.2s;
        }
        #messageInput.is-invalid {
            border-color: #dc3545;
            animation: shake 0.5s;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        #sendMessageBtn {
            background-color: #c43b68; /* Match chat header color */
            color: white;
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            transition: background-color 0.2s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        #sendMessageBtn:hover {
            background-color: #a62f55; /* Slightly darker shade */
        }
        #sendMessageBtn:disabled {
            background-color: #e0e0e0;
            cursor: not-allowed;
        }
        #sendMessageBtn i {
            font-size: 20px;
            color: white !important; /* Ensure white color */
        }
        .emoji-btn {
            margin-right: 10px;
            color: #666;
            cursor: pointer;
        }
        .fas {
            display: inline-block !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 user-list">
                <h5>Participants</h5>
                <ul class="list-unstyled">
                    <?php foreach ($access_users as $user): ?>
                        <li class="mb-2 d-flex align-items-center">
                            <img src="<?= htmlspecialchars($user['Image'] ?: 'default_avatar.png') ?>" 
                                 alt="<?= htmlspecialchars($user['Username']) ?>" 
                                 class="user-avatar">
                            <?php if ($user['Username'] === $paper['uploaded_by']): ?>
                                <span class="uploader"><?= htmlspecialchars($user['Username']) ?></span>
                            <?php else: ?>
                                <?= htmlspecialchars($user['Username']) ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="col-md-9">
                <div class="chat-container">
                    <div class="chat-header">
                        <h5 class="m-0">
                            Group Chat: <?= htmlspecialchars($paper['title']) ?> (Uploaded by <span class="uploader"><?= htmlspecialchars($paper['uploaded_by']) ?></span>)
                        </h5>
                    </div>
                    <div class="chat-messages" id="chatMessages">
                        <!-- Messages will be loaded here dynamically -->
                    </div>
                    <div class="message-input-container">
                        <span class="emoji-btn">
                            <i class="far fa-smile"></i>
                        </span>
                        <input type="text" id="messageInput" class="form-control" placeholder="Type a message...">
                        <button id="sendMessageBtn" title="Send">
                            <i class="fas fa-paper-plane" style="color: white !important;"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        const paperId = <?= $paper_id ?>;
        const username = '<?= htmlspecialchars($username) ?>';
        const uploader = '<?= htmlspecialchars($paper['uploaded_by']) ?>';

        function formatTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        }

        function loadMessages() {
            $.ajax({
                url: 'paper_group_chat.php',
                method: 'GET',
                data: { 
                    action: 'get_messages',
                    paper_id: paperId 
                },
                dataType: 'json', // Explicitly parse JSON
                success: function(response) {
                    const chatMessages = $('#chatMessages');
                    chatMessages.empty();

                    if (!response || response.length === 0) {
                        chatMessages.append('<div class="text-center text-muted mt-3">No messages yet</div>');
                        return;
                    }

                    response.forEach(message => {
                        const isCurrentUser = message.sender_username === username;
                        const isUploader = message.sender_username === uploader;
                        const messageClass = isCurrentUser ? 'sent' : 'received';
                        
                        chatMessages.append(`
                            <div class="message ${messageClass}">
                                <div class="message-sender ${isUploader ? 'uploader' : ''}">
                                    ${message.sender_username}
                                    ${isUploader ? ' (Admin)' : ''}
                                </div>
                                <div class="message-text">${message.message}</div>
                                <div class="message-time">${formatTime(message.timestamp)}</div>
                            </div>
                        `);
                    });

                    // Scroll to bottom
                    chatMessages.scrollTop(chatMessages[0].scrollHeight);
                },
                error: function(xhr, status, error) {
                    console.error('Failed to load messages:', status, error);
                    $('#chatMessages').html(`
                        <div class="alert alert-danger text-center">
                            Failed to load messages. Please try again.
                        </div>
                    `);
                }
            });
        }

        function sendMessage() {
            const messageInput = $('#messageInput');
            const sendButton = $('#sendMessageBtn');
            const sendIcon = sendButton.find('i');
            const message = messageInput.val().trim();

            if (!message) {
                // Shake input and add invalid state
                messageInput.addClass('is-invalid');
                setTimeout(() => messageInput.removeClass('is-invalid'), 500);
                return;
            }

            // Disable send button during send
            sendButton.prop('disabled', true);
            sendIcon.removeClass('fa-paper-plane').addClass('fa-spinner fa-spin');

            $.ajax({
                url: 'paper_group_chat.php',
                method: 'POST',
                data: {
                    paper_id: paperId,
                    message: message
                },
                dataType: 'json',
                success: function(result) {
                    if (result.success) {
                        messageInput.val(''); // Clear input
                        loadMessages(); // Refresh messages
                    } else {
                        alert(result.error || 'Failed to send message');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Send message error:', status, error);
                    alert('Failed to send message. Please try again.');
                },
                complete: function() {
                    // Re-enable send button
                    sendButton.prop('disabled', false);
                    sendIcon.removeClass('fa-spinner fa-spin').addClass('fa-paper-plane');
                }
            });
        }

        // Add event listeners
        $(document).ready(function() {
            // Send message on button click
            $('#sendMessageBtn').on('click', sendMessage);

            // Send message on Enter key
            $('#messageInput').on('keypress', function(e) {
                if (e.which === 13) {  // Enter key
                    sendMessage();
                    e.preventDefault(); // Prevent default enter key behavior
                }
            });

            // Optional: Emoji button placeholder
            $('.emoji-btn').on('click', function() {
                alert('Emoji picker coming soon!');
            });

            loadMessages();
            setInterval(loadMessages, 5000);
        });
        
        // Debugging for icon visibility
        $(document).ready(function() {
            console.log('Send button icon:', $('#sendMessageBtn i').length);
            console.log('Send button icon classes:', $('#sendMessageBtn i').attr('class'));
        });
    </script>
</body>
</html>