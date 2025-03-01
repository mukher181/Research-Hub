<?php

include 'functions.php';
// Handle notification view tracking via AJAX with comprehensive error handling
if (isset($_POST['action']) && $_POST['action'] === 'track_notification_view') {
    header('Content-Type: application/json');
    
    // Validate session
    if (!isset($_SESSION['username'])) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'User not logged in'
        ]);
        exit();
    }

    // Validate notification type
    $notification_type = $_POST['notification_type'] ?? '';
    $valid_types = ['chat', 'notification', 'system'];
    
    if (!in_array($notification_type, $valid_types)) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Invalid notification type'
        ]);
        exit();
    }

    // Attempt to save notification view time
    $result = saveNotificationViewTime($conn, $_SESSION['username'], $notification_type);
    
    if ($result) {
        // Update the last viewed timestamp
        $update_sql = "UPDATE notification_views SET last_viewed_at = NOW() WHERE username = ? AND notification_type = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ss", $_SESSION['username'], $notification_type);
        $update_stmt->execute();

        echo json_encode([
            'status' => 'success', 
            'message' => 'Notification view tracked'
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Failed to track notification view'
        ]);
    }
    exit();
}
$conn = new mysqli("localhost", "root", "", "research");
$user_role = $_SESSION['role'] ?? 'user';

if (isset($_SESSION['username'])) {
    $chatNotifications = getChatNotifications($conn, $user_role);
    $otherNotifications = getOtherNotifications($conn, $user_role);
}

// Ensure session variables exist to prevent undefined key warnings
$_SESSION['user_image'] = $_SESSION['user_image'] ?? 'default-user.png'; // Provide a default profile image
$_SESSION['email'] = $_SESSION['email'] ?? 'Not Available';
$_SESSION['bio'] = $_SESSION['bio'] ?? 'No bio available';
$_SESSION['interests'] = $_SESSION['interests'] ?? 'No interests listed';
$_SESSION['profile_image'] = $_SESSION['profile_image'] ?? 'default-user.png'; // Provide a default profile image
$_SESSION['user_name'] = $_SESSION['user_name'] ?? 'Guest';

// Check if user is not logged in
if (!isset($_SESSION['username']) && !isset($_SESSION['user_email'])) {
    header("Location: login.php"); // Redirect to login page
    exit();
} 

// Get user data from session
$user_name = $_SESSION['username'] ?? '';
$user_role = $_SESSION['role'] ?? 'Admin';

// Fetch additional user data from database only if not already stored
if (empty($_SESSION['profile_image']) || empty($_SESSION['email'])) {
    $sql = "SELECT Image, email, bio_description, role, research_interests, join_date FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $user_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $_SESSION['profile_image'] = $_SESSION['profile_image'] ?? $row['Image'];
        $_SESSION['email'] = $_SESSION['email'] ?? $row['email'];
        $_SESSION['bio'] = $_SESSION['bio'] ?? $row['bio_description'];
        $_SESSION['interests'] = $_SESSION['interests'] ?? $row['research_interests'];
        $_SESSION['role'] = $_SESSION['role'] ?? $row['role'];
        $_SESSION['join_date'] = $_SESSION['join_date'] ?? $row['join_date']; // Fetch and store join_date
    } else {
        echo "No user data found.";
    }
}

// Use join_date from session
$join_date = $_SESSION['join_date'] ?? '';
?>

<!--begin::Header-->
<nav class="app-header navbar navbar-expand bg-body">
    <!--begin::Container-->
    <div class="container-fluid">
        <!--begin::Start Navbar Links-->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="bi bi-list"></i>
                </a>
            </li>
            <li class="nav-item d-none d-md-block"><a href="../../index.html" class="nav-link">Dashboard</a></li>
            <li class="nav-item d-none d-md-block"><a href="support.php" class="nav-link">Support</a></li>
        </ul>
        <!--end::Start Navbar Links-->
        <!--begin::End Navbar Links-->
        <ul class="navbar-nav ms-auto">
            <!--begin::Navbar Search-->
            <li class="nav-item">
                <a class="nav-link" data-widget="navbar-search" href="#" role="button">
                    <i class="bi bi-search"></i>
                </a>
            </li>
            <!--end::Navbar Search-->
            <!--begin::Messages Dropdown Menu-->
            <li class="nav-item dropdown">
                <a class="nav-link chat-icon" data-bs-toggle="dropdown" href="#">
                    <i class="bi bi-chat-text"></i>
                    <?php if (count($chatNotifications) > 0): ?>
                        <span class="navbar-badge badge text-bg-danger"><?php echo count($chatNotifications); ?></span>
                    <?php endif; ?>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                  <?php foreach ($chatNotifications as $index => $notification): ?>
                    <a href="#" class="dropdown-item <?php echo $index >= 3 ? 'd-none' : ''; ?>">
                      <div class="media" style="display:flex;">
                        <img
                          src="<?php echo htmlspecialchars($notification['avatar']); ?>"
                          alt="User Avatar"
                          class="img-size-50 rounded-circle me-3"
                          style="width: 50px; height: 50px; object-fit: cover;"
                        />
                        <div class="media-body">
                          <h3 class="dropdown-item-title">
                            <?php echo htmlspecialchars($notification['title']); ?>
                          </h3>
                          <p class="fs-7"><?php echo htmlspecialchars($notification['content']); ?></p>
                          <p class="fs-7 text-secondary">
                            <i class="bi bi-clock-fill me-1"></i> <?php echo htmlspecialchars($notification['timestamp']); ?>
                          </p>
                        </div>
                      </div>
                      <!--end::Message-->
                    </a>
                    <div class="dropdown-divider <?php echo $index >= 3 ? 'd-none' : ''; ?>"></div>
                  <?php endforeach; ?>
                  <a href="#" class="dropdown-item dropdown-footer" id="seeAllMessages"> See All Messages </a>
                </div>
            </li>
            <!--end::Messages Dropdown Menu-->
            <!--begin::Notifications Dropdown Menu-->
            <li class="nav-item dropdown">
                <a class="nav-link notification-icon" data-bs-toggle="dropdown" href="#">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (count($otherNotifications) > 0): ?>
                    <span class="navbar-badge badge text-bg-warning"><?php echo count($otherNotifications); ?></span>
                    <?php endif; ?>
                </a>
                <div class="dropdown-menu dropdown-menu-lg2 dropdown-menu-end">
                    <span class="dropdown-item dropdown-header"><?php echo count($otherNotifications); ?> Notifications</span>
                    <div class="dropdown-divider"></div>
                    <?php foreach ($otherNotifications as $index => $notification): ?>
                        <a href="#" class="dropdown-item <?php echo $index >= 3 ? 'd-none' : ''; ?>">
                            <i class="bi bi-<?php echo $notification['type'] == 'user' ? 'people-fill' : 'file-earmark-fill'; ?> me-2"></i> 
                            <strong><?php echo $notification['content']; ?></strong>
                            <?php if ($notification['type'] == 'paper'): ?>
                                <br><small class="text-muted">Uploaded by <?php echo $notification['title']; ?></small>
                            <?php endif; ?>
                            <span class="float-end text-secondary fs-7"><?php echo $notification['timestamp']; ?></span>
                        </a>
                        <div class="dropdown-divider <?php echo $index >= 3 ? 'd-none' : ''; ?>"></div>
                    <?php endforeach; ?>
                    <a href="#" class="dropdown-item dropdown-footer" id="seeAllNotifications"> See All Notifications </a>
                </div>
            </li>
            <!--end::Notifications Dropdown Menu-->
            <!--begin::Fullscreen Toggle-->
            <li class="nav-item">
                <a class="nav-link" href="#" data-lte-toggle="fullscreen">
                    <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                    <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i>
                </a>
            </li>
            <!--end::Fullscreen Toggle-->
            <!--begin::User Menu Dropdowns-->
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <!-- Admin User Dropdown -->
                <li class="nav-item dropdown user-menu">
                    <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                        <img src="<?php echo $_SESSION['profile_image']; ?>" class="user-image rounded-circle shadow" alt="User Image" />
                        <span class="d-none d-md-inline"><?php echo htmlspecialchars($user_name); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                        <!--begin::User Image-->
                        <li class="user-header text-bg-primary">
                            <img src="<?php echo $_SESSION['profile_image']; ?>" class="rounded-circle shadow" alt="User Image" />
                            <p>
                                <?php echo htmlspecialchars($user_name); ?> - Administrator
                                <small>Member since <?php echo htmlspecialchars($join_date); ?></small>
                            </p>
                        </li>
                        <!--end::User Image-->
                        <!--begin::Menu Body-->
                        <li class="user-body">
                            <!--begin::Row-->
                            <div class="row">
                                <div class="col-4 text-center"><a href="manage_papers.php">Papers</a></div>
                                <div class="col-4 text-center"><a href="manage_users.php">Users</a></div>
                                <div class="col-4 text-center"><a href="reports.php">Reports</a></div>
                            </div>
                            <!--end::Row-->
                        </li>
                        <!--end::Menu Body-->
                        <!--begin::Menu Footer-->
                        <li class="user-footer">
                            <a href="edit_profile.php" class="btn btn-default btn-flat">Profile</a>
                            <a href="logout.php" class="btn btn-default btn-flat float-end">Sign out</a>
                        </li>
                        <!--end::Menu Footer-->
                    </ul>
                </li>
            <?php else: ?>
                <!-- Regular User Dropdown -->
                <li class="nav-item dropdown user-menu">
                    <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                        <img src="<?php echo $_SESSION['profile_image']; ?>" class="user-image rounded-circle shadow" alt="User Image" />
                        <span class="d-none d-md-inline"><?php echo htmlspecialchars($user_name); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                        <!--begin::User Image-->
                        <li class="user-header text-bg-primary">
                            <img src="<?php echo isset($_SESSION['user_image']) ? htmlspecialchars($_SESSION['user_image']) : 'default-user.png'; ?>" 
                                 class="rounded-circle shadow" alt="User Image" />

                            <p>
                                <?php echo htmlspecialchars($user_name); ?> 
                                <small>Member since <?php echo htmlspecialchars($join_date); ?></small>
                            </p>
                        </li>
                        <!--end::User Image-->
                        <!--begin::Menu Body-->
                        <li class="user-body">
                            <div class="row">
                                <div class="col-12">
                                    <div class="text-muted mb-2">
                                        <strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['email']); ?>
                                    </div>
                                    <div class="text-muted mb-2">
                                        <strong>Bio:</strong> <?php echo htmlspecialchars($_SESSION['bio']); ?>
                                    </div>
                                    <div class="text-muted">
                                        <strong>Research Interests:</strong> <?php echo htmlspecialchars($_SESSION['interests']); ?>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <!--end::Menu Body-->
                        <!--begin::Menu Footer-->
                        <li class="user-footer">
                            <a href="edit_profile.php" class="btn btn-default btn-flat">
                                Edit Profile
                            </a>
                            <a href="logout.php" class="btn btn-default btn-flat float-end">Sign out</a>
                        </li>
                        <!--end::Menu Footer-->
                    </ul>
                </li>
            <?php endif; ?>
            <!--end::User Menu Dropdowns-->
        </ul>
        <!--end::End Navbar Links-->
    </div>
    <!--end::Container-->
</nav>
<!--end::Header-->

<script>
    function showAllItems(buttonId, itemClass, dividerClass) {
        document.getElementById(buttonId).addEventListener('click', function(event) {
            event.preventDefault();
            document.querySelectorAll(itemClass).forEach(function(item) {
                item.classList.remove('d-none');
            });
            document.querySelectorAll(dividerClass).forEach(function(item) {
                item.classList.remove('d-none');
            });
            this.style.display = 'none';
        });
    }

    showAllItems('seeAllMessages', '.dropdown-menu-lg .dropdown-item.d-none', '.dropdown-menu-lg .dropdown-divider.d-none');
    showAllItems('seeAllNotifications', '.dropdown-menu-lg2 .dropdown-item.d-none', '.dropdown-menu-lg2 .dropdown-divider.d-none');

    // Ensure dropdown does not close on click
    document.querySelectorAll('.dropdown-menu').forEach(function(dropdown) {
        dropdown.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    });

    // Function to track notification/chat icon views
    function trackNotificationView(notificationType) {
        $.ajax({
            url: 'topnav.php',
            method: 'POST',
            data: {
                action: 'track_notification_view',
                notification_type: notificationType
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    console.log('Notification view tracked successfully');
                } else {
                    console.error('Failed to track notification view', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error tracking notification view', error);
            }
        });
    }

    // Add click event listeners for chat and notification icons
    $(document).ready(function() {
        // Chat icon tracking
        $('.chat-icon').on('click', function() {
            trackNotificationView('chat');
        });

        // Notification icon tracking
        $('.notification-icon').on('click', function() {
            trackNotificationView('notification');
        });
    });
</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Function to fetch and update notifications
    function fetchNotifications() {
        $.ajax({
            url: 'fetch_notifications.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                // Update chat notifications
                updateChatNotifications(response.chatNotifications);
                
                // Update other notifications
                updateOtherNotifications(response.otherNotifications);
            },
            error: function(xhr, status, error) {
                console.error("Error fetching notifications:", error);
            }
        });
    }

    // Function to update chat notifications
    function updateChatNotifications(chatNotifications) {
        const chatDropdown = $('.chat-icon .dropdown-menu');
        chatDropdown.find('.dropdown-item:not(.dropdown-footer)').remove();
        chatDropdown.find('.dropdown-divider:not(:last)').remove();

        if (chatNotifications.length > 0) {
            $('.chat-icon .navbar-badge').text(chatNotifications.length).show();
            
            chatNotifications.slice(0, 3).forEach((notification, index) => {
                const notificationHtml = `
                    <a href="#" class="dropdown-item">
                        <div class="media" style="display:flex;">
                            <img src="${notification.avatar}" alt="User Avatar" 
                                class="img-size-50 rounded-circle me-3" 
                                style="width: 50px; height: 50px; object-fit: cover;"/>
                            <div class="media-body">
                                <h3 class="dropdown-item-title">${notification.title}</h3>
                                <p class="fs-7">${notification.content}</p>
                                <p class="fs-7 text-secondary">
                                    <i class="bi bi-clock-fill me-1"></i> ${notification.timestamp}
                                </p>
                            </div>
                        </div>
                    </a>
                    <div class="dropdown-divider"></div>
                `;
                chatDropdown.find('.dropdown-footer').before(notificationHtml);
            });
        } else {
            $('.chat-icon .navbar-badge').hide();
        }
    }

    // Function to update other notifications
    function updateOtherNotifications(otherNotifications) {
        const notificationDropdown = $('.notification-icon .dropdown-menu');
        notificationDropdown.find('.dropdown-item:not(.dropdown-footer)').remove();
        notificationDropdown.find('.dropdown-divider:not(:last)').remove();

        if (otherNotifications.length > 0) {
            $('.notification-icon .navbar-badge').text(otherNotifications.length).show();
            
            otherNotifications.slice(0, 3).forEach((notification, index) => {
                const notificationHtml = `
                    <a href="#" class="dropdown-item">
                        <i class="bi bi-${notification.type == 'user' ? 'people-fill' : 'file-earmark-fill'} me-2"></i> 
                        <strong>${notification.content}</strong>
                        ${notification.type == 'paper' ? 
                            `<br><small class="text-muted">Uploaded by ${notification.title}</small>` : 
                            ''
                        }
                        <span class="float-end text-secondary fs-7">${notification.timestamp}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                `;
                notificationDropdown.find('.dropdown-footer').before(notificationHtml);
            });
        } else {
            $('.notification-icon .navbar-badge').hide();
        }
    }

    // Fetch notifications every 30 seconds
    fetchNotifications();
    setInterval(fetchNotifications, 30000);
});
</script>
