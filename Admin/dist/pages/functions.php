<?php
function getChatNotifications($conn, $user_role){
    $notifications = [];
    if ($user_role !== 'user') {
        // Get the logged-in username from session
        $logged_in_username = $_SESSION['username'] ?? '';

        // Fetch last logout time
        $last_logout_sql = "SELECT MAX(logout_timestamp) as last_logout 
                            FROM user_login_history 
                            WHERE username = ?";
        $stmt = $conn->prepare($last_logout_sql);
        $stmt->bind_param("s", $logged_in_username);
        $stmt->execute();
        $result = $stmt->get_result();
        $last_viewed_at = $result->fetch_assoc()['last_logout'] ?? 
            date('Y-m-d H:i:s', strtotime('-30 days')); // Default to 30 days if no logout found

        // Error log for debugging
        error_log("Chat Notifications - Username: $logged_in_username, Last Logout At: $last_viewed_at");

        // Fetch latest chat messages with more comprehensive filtering
        $sql = "SELECT cm.message AS content, cm.sent_at AS timestamp, u.Name AS title, u.Image AS avatar 
                FROM chat_messages cm 
                JOIN users u ON cm.sender_id = u.Username 
                WHERE cm.receiver_id = ? 
                  AND cm.sender_id != ? 
                  AND cm.sent_at > ?
                  AND cm.is_read = 0  -- Only unread messages
                ORDER BY cm.sent_at DESC LIMIT 50";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $logged_in_username, $logged_in_username, $last_viewed_at);
        $stmt->execute();
        $result = $stmt->get_result();

        // Error log number of results
        error_log("Chat Notifications - Results Count: " . $result->num_rows);

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $row['type'] = 'chat_message';
                $notifications[] = $row;
            }
        }

        // Fetch latest paper access chat messages (only unread and after last logout time)
        $sql = "SELECT pac.message AS content, pac.timestamp AS timestamp, 
                       pac.sender_username AS title, u.Image AS avatar 
                FROM paper_access_chat pac 
                JOIN users u ON pac.sender_username = u.Username
                WHERE pac.sender_username != ? 
                  AND pac.timestamp > ?
                  AND NOT EXISTS (
                      SELECT 1 FROM paper_message_views pmv 
                      WHERE pmv.username = ? 
                        AND pmv.paper_id = pac.paper_id 
                        AND pmv.last_viewed >= pac.timestamp
                  )
                ORDER BY pac.timestamp DESC LIMIT 50";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $logged_in_username, $last_viewed_at, $logged_in_username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $row['type'] = 'paper_access_chat';
                $notifications[] = $row;
            }
        }

        // Fetch latest paper shares (excluding logged-in user's shares and only after last logout time)
        $sql = "SELECT CONCAT(' share access with ', shared_with) AS content, ps.created_at AS timestamp, ru.uploaded_by AS title, u.Image AS avatar 
                FROM paper_shares ps 
                JOIN research_uploads ru ON ps.paper_id = ru.id
                JOIN users u ON ps.shared_with = u.Username 
                WHERE ps.shared_with = ? AND ps.created_at > ?
                ORDER BY ps.created_at DESC LIMIT 50";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $logged_in_username, $last_viewed_at);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $row['type'] = 'paper_share';
                $notifications[] = $row;
            }
        }
        
        // Fetch latest research discussions (excluding logged-in user's discussions and users with admin role and only after last logout time)
        $sql = "SELECT rd.message AS content, rd.timestamp AS timestamp, IF(u.role = 'admin', 'BY Admin', u.Username) AS title, u.Image AS avatar 
                FROM research_discussions rd 
                JOIN users u ON rd.real_username = u.username 
                WHERE rd.real_username != ? AND rd.timestamp > ?
                ORDER BY rd.timestamp DESC LIMIT 50";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $logged_in_username, $last_viewed_at);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $row['type'] = 'research_discussion';
                $notifications[] = $row;
            }
        }
    }
    // Sort notifications by timestamp
    usort($notifications, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    return $notifications;
}

function getOtherNotifications($conn, $user_role) {
    $notifications = [];
    if ($user_role !== 'user') {
        // Get the logged-in username from session
        $logged_in_username = $_SESSION['username'] ?? '';

        // Fetch last logout time
        $last_logout_sql = "SELECT MAX(logout_timestamp) as last_logout 
                            FROM user_login_history 
                            WHERE username = ?";
        $stmt = $conn->prepare($last_logout_sql);
        $stmt->bind_param("s", $logged_in_username);
        $stmt->execute();
        $result = $stmt->get_result();
        $last_viewed_at = $result->fetch_assoc()['last_logout'] ?? 
            date('Y-m-d H:i:s', strtotime('-30 days')); // Default to 30 days if no logout found

        // Error log for debugging
        error_log("Other Notifications - Username: $logged_in_username, Last Logout At: $last_viewed_at");

        if ($user_role === 'admin') {
            // Fetch latest registered users (only after last logout time)
            $sql = "SELECT Name AS title, join_date AS timestamp, 
                    CONCAT('New ', IF(role = 'researcher', 'researcher', 'user'), ' registered: ', Username) AS content, 
                    Image AS avatar 
                    FROM users 
                    WHERE Username != ? 
                      AND join_date > ?
                    ORDER BY join_date DESC LIMIT 50";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $logged_in_username, $last_viewed_at);
            $stmt->execute();
            $result = $stmt->get_result();

            // Error log number of results
            error_log("Admin User Notifications - Results Count: " . $result->num_rows);

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $row['type'] = 'user';
                    $notifications[] = $row;
                }
            }

            // Fetch latest research papers (only after last logout time)
            $sql = "SELECT uploaded_by AS title, uploaded_at AS timestamp, 
                    title AS content, 'uploads/default-avatar.png' AS avatar 
                    FROM research_uploads 
                    WHERE uploaded_by != ? 
                      AND uploaded_at > ?
                    ORDER BY uploaded_at DESC LIMIT 50";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $logged_in_username, $last_viewed_at);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $row['type'] = 'paper';
                    $notifications[] = $row;
                }
            }
        } else {
            // Fetch count of personal chat messages (only unread and after last logout time)
            $sql = "SELECT COUNT(*) AS count 
                    FROM chat_messages 
                    WHERE receiver_id = ? 
                      AND sender_id != ? 
                      AND sent_at > ?
                      AND is_read = 0";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $logged_in_username, $logged_in_username, $last_viewed_at);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($row['count'] > 0) {
                    $notifications[] = [
                        'type' => 'personal_chat',
                        'content' => $row['count'] . ' new personal chat messages',
                        'timestamp' => date('Y-m-d H:i:s'),
                        'avatar' => 'uploads/default-avatar.png'
                    ];
                }
            }

            // Fetch count of paper group chat messages (excluding current user's messages and after last logout time)
            $sql = "SELECT COUNT(*) AS count FROM paper_access_chat WHERE sender_username != ? AND timestamp > ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $logged_in_username, $last_viewed_at);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($row['count'] > 0) {
                    $notifications[] = [
                        'type' => 'paper_group_chat',
                        'content' => $row['count'] . ' new paper group chat messages',
                        'timestamp' => date('Y-m-d H:i:s'),
                        'avatar' => 'uploads/default-avatar.png'
                    ];
                }
            }

            // Fetch count of comments from research discussions (excluding current user's comments and after last logout time)
            $sql = "SELECT COUNT(*) AS count FROM research_discussions WHERE sender != ? AND timestamp > ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $logged_in_username, $last_viewed_at);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($row['count'] > 0) {
                    $notifications[] = [
                        'type' => 'research_discussion',
                        'content' => $row['count'] . ' message in research discussions',
                        'timestamp' => date('Y-m-d H:i:s'),
                        'avatar' => 'uploads/default-avatar.png'
                    ];
                }
            }
        }

        // Fetch paper upload notifications for the logged-in user
        $sql = "SELECT title AS content, uploaded_at AS timestamp, 
                'Paper Submitted Successfully' AS title, 
                'uploads/default-avatar.png' AS avatar 
                FROM research_uploads 
                WHERE uploaded_by = ? AND status = 'inactive' AND uploaded_at > ?
                ORDER BY uploaded_at DESC LIMIT 50";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $logged_in_username, $last_viewed_at);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $row['type'] = 'paper_upload';
                $row['content'] = "Paper Submitted Successfully: " . $row['content'] . ". Wait for approval.";
                $notifications[] = $row;
            }
        }

        // Fetch paper approval notifications for the logged-in user
        $sql = "SELECT title AS content, uploaded_at AS timestamp, 
                'Paper Approved' AS title, 
                'uploads/default-avatar.png' AS avatar 
                FROM research_uploads 
                WHERE uploaded_by = ? AND status = 'active' AND uploaded_at > ?
                ORDER BY uploaded_at DESC LIMIT 50";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $logged_in_username, $last_viewed_at);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $row['type'] = 'paper_approval';
                $row['content'] = "Your Paper is Approved: " . $row['content'];
                $notifications[] = $row;
            }
        }
    }

    // Sort notifications by timestamp
    usort($notifications, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    return $notifications;
}

function ensureNotificationViewsTableExists($conn) {
    $create_table_sql = "CREATE TABLE IF NOT EXISTS notification_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL,
        notification_type ENUM('chat', 'notification', 'system') NOT NULL,
        last_viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_notification_type (username, notification_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($create_table_sql) === FALSE) {
        error_log("Critical Error: Unable to create notification_views table. Error: " . $conn->error);
        return false;
    }
    return true;
}

function saveNotificationViewTime($conn, $username, $notification_type) {
    // Validate input parameters
    if (empty($username) || empty($notification_type)) {
        error_log("Invalid parameters for saveNotificationViewTime: username=$username, type=$notification_type");
        return false;
    }

    // Ensure table exists
    if (!ensureNotificationViewsTableExists($conn)) {
        error_log("Failed to ensure notification_views table exists");
        return false;
    }
 
    try {
        // Use REPLACE INTO to handle both insert and update scenarios
        $sql = "REPLACE INTO notification_views 
                (username, notification_type, last_viewed_at) 
                VALUES (?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare statement failed: " . $conn->error);
            return false;
        }

        $stmt->bind_param("ss", $username, $notification_type);
        $result = $stmt->execute();

        if ($result) {
            error_log("Notification view tracked successfully: User=$username, Type=$notification_type");
            return true;
        } else {
            error_log("Failed to track notification view: " . $stmt->error);
            return false;
        }
    } catch (Exception $e) {
        error_log("Exception in saveNotificationViewTime: " . $e->getMessage());
        return false;
    }
}

function resetNotificationViews($conn, $username) {
    // Ensure table exists
    if (!ensureNotificationViewsTableExists($conn)) {
        error_log("Failed to ensure notification_views table exists for reset");
        return false;
    }

    $notification_types = ['chat', 'notification', 'system'];
    
    foreach ($notification_types as $type) {
        try {
            $sql = "REPLACE INTO notification_views 
                    (username, notification_type, last_viewed_at) 
                    VALUES (?, ?, NOW())";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("Prepare statement failed for reset: " . $conn->error);
                continue;
            }

            $stmt->bind_param("ss", $username, $type);
            $result = $stmt->execute();

            if ($result) {
                error_log("Notification view reset successfully: User=$username, Type=$type");
            } else {
                error_log("Failed to reset notification view: " . $stmt->error);
            }
        } catch (Exception $e) {
            error_log("Exception in resetNotificationViews: " . $e->getMessage());
        }
    }

    return true;
}

// Call this function during initial database connection or setup
ensureNotificationViewsTableExists($conn);