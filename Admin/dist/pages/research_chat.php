<?php
session_start();
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$current_user = $_SESSION['username'];

// Get the researcher to chat with from URL parameter
$chat_with = isset($_GET['chat_with']) ? $_GET['chat_with'] : null;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (isset($data['action'])) {
        header('Content-Type: application/json');
        
        switch($data['action']) {
            case 'send_message':
                $receiver = $data['receiver'];
                $message = $data['message'];
                
                // Insert message with read status
                $insert = "INSERT INTO chat_messages (sender_id, receiver_id, message, is_read) VALUES (?, ?, ?, 0)";
                $stmt = $conn->prepare($insert);
                $stmt->bind_param("sss", $current_user, $receiver, $message);
                $success = $stmt->execute();
                
                echo json_encode(['success' => $success]);
                break;
                
            case 'get_messages':
                $other_user = $data['user'];
                
                // Mark all messages from other user as read
                $mark_read = "UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
                $stmt = $conn->prepare($mark_read);
                $stmt->bind_param("ss", $other_user, $current_user);
                $stmt->execute();
                
                // Get messages between current user and other user
                $query = "SELECT * FROM chat_messages WHERE 
                    (sender_id = ? AND receiver_id = ?) OR 
                    (sender_id = ? AND receiver_id = ?) 
                    ORDER BY sent_at ASC";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssss", $current_user, $other_user, $other_user, $current_user);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $messages = [];
                while ($row = $result->fetch_assoc()) {
                    $messages[] = $row;
                }
                
                echo json_encode(['messages' => $messages]);
                break;
                
            case 'get_user_details':
                $username = $data['username'];
                
                // Get user details including last activity
                $query = "SELECT username, Image, role, last_activity, 
                         (SELECT COUNT(*) FROM chat_messages 
                          WHERE receiver_id = ? AND sender_id = ? AND is_read = 0) as unread_count 
                         FROM users 
                         WHERE username = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sss", $current_user, $username, $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $user_details = $result->fetch_assoc();
                echo json_encode($user_details);
                break;
                
            case 'get_chat_list':
                // Get list of all researchers with unread message count
                $query = "SELECT u.username, u.Image, u.role, 
                         (SELECT COUNT(*) FROM chat_messages 
                          WHERE receiver_id = ? AND sender_id = u.username AND is_read = 0) as unread_count 
                         FROM users u 
                         WHERE u.role = 'researcher' AND u.username != ?
                         ORDER BY unread_count DESC";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $current_user, $current_user);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $chat_list = [];
                while ($row = $result->fetch_assoc()) {
                    $chat_list[] = $row;
                }
                
                echo json_encode(['chat_list' => $chat_list]);
                break;
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: white;
        }
        .chat-container {
            height: calc(100vh - 60px);
            display: flex;
            background-color: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        .chat-list {
            width: 350px;
            border-right: 1px solid #f0d0d8;
            background-color: white;
            overflow-y: auto;
        }
        .chat-list-header {
            padding: 15px;
            background-color: #c43b68;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .chat-messages {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .chat-header {
            padding: 15px;
            background-color: #fff5f5;
            border-bottom: 1px solid #f0d0d8;
            display: flex;
            align-items: center;
        }
        .chat-header img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 15px;
        }
        .message-container {
            flex-grow: 1;
            overflow-y: auto;
            padding: 20px;
            background-color: white;
        }
        .message {
            margin-bottom: 15px;
            padding: 10px 15px;
            border-radius: 15px;
            max-width: 70%;
            position: relative;
            clear: both;
        }
        .message.sent {
            background-color: #c43b68;
            color: white;
            float: right;
            border-bottom-right-radius: 5px;
        }
        .message.received {
            background-color: #ffe5e5;
            color: #212529;
            float: left;
            border-bottom-left-radius: 5px;
        }
        .message-time {
            font-size: 0.7em;
            opacity: 0.7;
            margin-top: 5px;
        }
        .input-container {
            padding: 15px;
            background-color: white;
            border-top: 1px solid #f0d0d8;
        }
        .chat-user {
            padding: 15px;
            border-bottom: 1px solid #f0d0d8;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: background-color 0.2s;
        }
        .chat-user:hover {
            background-color: #f0f0f0;
        }
        .chat-user.active {
            background-color: #f0f0f0;
            color: #c43b68;
            font-weight: bold;
        }
        .chat-user.active img {
            border: 2px solid #c43b68;
        }
        .unread-badge {
            background-color: #c43b68;
            color: white;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 0.8em;
            margin-left: auto;
        }
        .input-group .btn-primary {
            background-color: #c43b68;
            border-color: #c43b68;
        }
        .input-group .btn-primary:hover {
            background-color: #a62b50;
            border-color: #a62b50;
        }
        .notification {
            font-size: 0.8em;
            color: #c43b68;
            margin-left: 10px;
        }
        #searchResearchers {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .chat-user {
            padding: 10px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s;
        }
        
        .chat-user:hover {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-3">
        <div class="chat-container">
            <div class="chat-list">
                <div class="chat-list-header">
                    <h5 class="m-0">Research Chats</h5>
                    <i class="bi bi-search"></i>
                </div>
                <input type="text" id="searchResearchers" placeholder="Search Researchers..." />
                <div id="chatList">
                    <!-- Chat users will be loaded here -->
                </div>
            </div>
            <div class="chat-messages">
                <div class="chat-header" id="chatHeader">
                    <!-- Selected user details will be loaded here -->
                    <div class="text-center w-100">
                        <p class="m-0">Select a researcher to start chatting</p>
                    </div>
                </div>
                <div class="message-container" id="messageContainer">
                    <!-- Messages will be loaded here -->
                </div>
                <div class="input-container">
                    <div class="input-group">
                        <input type="text" class="form-control" id="messageInput" placeholder="Type your message..." disabled>
                        <button class="btn btn-primary" id="sendButton" disabled>
                            <i class="bi bi-send"></i>
                        </button>
                       
                    </div>
                </div>
            </div>
        </div>
        <a href="index.php" class="back-link">
                            Back to Home
                        </a>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let currentChatUser = null;
        let messageCheckInterval = null;

        function formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diffMinutes = Math.floor((now - date) / (1000 * 60));

            if (diffMinutes < 1) return 'Just now';
            if (diffMinutes < 60) return `${diffMinutes} min ago`;
            if (diffMinutes < 1440) return `${Math.floor(diffMinutes/60)} hours ago`;
            return date.toLocaleDateString();
        }

        function loadChatList() {
            $.ajax({
                url: 'research_chat.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    action: 'get_chat_list'
                }),
                success: function(response) {
                    const chatList = $('#chatList');
                    chatList.empty();
                    
                    // Get the researcher to chat with from URL parameter
                    const urlParams = new URLSearchParams(window.location.search);
                    const chatWithParam = urlParams.get('chat_with');
                    
                    // Retrieve last chatted user from local storage if no URL param
                    const lastChattedUser = chatWithParam || localStorage.getItem('lastChattedUser');
                    
                    response.chat_list.forEach(user => {
                        const isActive = lastChattedUser && user.username === lastChattedUser;
                        const unreadCount = user.unread_count > 0 ? `<span class="notification">${user.unread_count} unread messages</span>` : '';
                        const userElement = $(`
                            <div class="chat-user ${isActive ? 'active' : ''}" data-username="${user.username}">
                                <div class="position-relative me-3">
                                    <img src="${user.Image || 'default_avatar.png'}" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;">
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center">
                                        <span class="fw-bold">${user.username}</span>
                                        ${unreadCount}
                                    </div>
                                    <small class="text-muted">${user.role}</small>
                                </div>
                            </div>
                        `);
                        
                        userElement.on('click', function() {
                            currentChatUser = user.username;
                            
                            // Store last chatted user in local storage
                            localStorage.setItem('lastChattedUser', currentChatUser);
                            
                            // Update chat list header
                            $('.chat-list-header').html(`
                                <h5 class="m-0">Chat with ${currentChatUser}</h5>
                                <i class="bi bi-search"></i>
                            `);
                            
                            loadUserDetails(currentChatUser);
                            loadMessages(currentChatUser);
                            
                            // Enable message input and send button
                            $('#messageInput').prop('disabled', false);
                            $('#sendButton').prop('disabled', false);
                        });
                        
                        chatList.append(userElement);
                        
                        // If last chatted user exists, automatically load messages for that user
                        if (isActive) {
                            currentChatUser = user.username;
                            
                            // Simulate click to load user details and messages
                            userElement.click();
                        }
                    });
                    
                    // If no user was active, but last chatted user exists, trigger its selection
                    if (lastChattedUser && !currentChatUser) {
                        $(`.chat-user[data-username="${lastChattedUser}"]`).click();
                    }
                }
            });
        }

        function loadUserDetails(username) {
            $.ajax({
                url: 'research_chat.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    action: 'get_user_details',
                    username: username
                }),
                success: function(user) {
                    const chatHeader = $('#chatHeader');
                    chatHeader.html(`
                        <img src="${user.Image || 'default_avatar.png'}" alt="${username}">
                        <div>
                            <h5 class="m-0">${username}</h5>
                            <small class="text-muted">${user.role}</small>
                        </div>
                    `);
                    
                    // Update the chat list to highlight the current user
                    $('.chat-user').removeClass('active');
                    $(`.chat-user[data-username="${username}"]`).addClass('active');
                }
            });
        }

        function loadMessages(user) {
            $.ajax({
                url: 'research_chat.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    action: 'get_messages',
                    user: user
                }),
                success: function(response) {
                    const messageContainer = $('#messageContainer');
                    messageContainer.empty();
                    
                    response.messages.forEach(message => {
                        const messageClass = message.sender_id === '<?php echo $current_user; ?>' ? 'sent' : 'received';
                        messageContainer.append(`
                            <div class="message ${messageClass}">
                                ${message.message}
                                <div class="message-time">${formatTimestamp(message.sent_at)}</div>
                            </div>
                        `);
                    });
                    
                    // Auto-scroll to bottom
                    messageContainer.scrollTop(messageContainer[0].scrollHeight);
                }
            });
        }

        function sendMessage() {
            const messageInput = $('#messageInput');
            const message = messageInput.val().trim();
            
            if (message && currentChatUser) {
                $.ajax({
                    url: 'research_chat.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: 'send_message',
                        receiver: currentChatUser,
                        message: message
                    }),
                    success: function(response) {
                        if (response.success) {
                            messageInput.val('');
                            loadMessages(currentChatUser);
                            loadChatList(); // Refresh chat list to update unread counts
                            
                            // Ensure the last chatted user remains the same
                            localStorage.setItem('lastChattedUser', currentChatUser);
                        }
                    }
                });
            }
        }

        // Send message on button click
        $('#sendButton').on('click', sendMessage);

        // Send message on Enter key press
        $('#messageInput').on('keypress', function(e) {
            if (e.which === 13) {
                sendMessage();
            }
        });

        // Periodically refresh chat list and messages
        function refreshChat() {
            if (currentChatUser) {
                loadMessages(currentChatUser);
                loadChatList();
            }
        }
        setInterval(refreshChat, 30000); // Refresh every 30 seconds

        // Load chat list on page load
        $(document).ready(function() {
            loadChatList();
        });

        // Search functionality
        $(document).ready(function() {
            $('#searchResearchers').on('input', function() {
                const query = $(this).val().toLowerCase();
                $('.chat-user').each(function() {
                    const username = $(this).data('username').toLowerCase();
                    $(this).toggle(username.includes(query));
                });
            });
        });
    </script>
</body>
</html>
