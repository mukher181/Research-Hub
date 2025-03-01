<?php
session_start();
include 'config.php';

// Check if user is not logged in
if (!isset($_SESSION['username']) && !isset($_SESSION['user_email'])) {
    header("Location: login.php"); // Redirect to login page
    exit();
}

// Fetch user role and details
$username = $_SESSION['username'];
$query = "SELECT role, id, Email FROM users WHERE username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$role = $user['role'];
$user_id = $user['id'];
$user_email = $user['Email'];

// Handle support ticket submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ticket'])) {
    $ticket_subject = $_POST['ticket_subject'];
    $ticket_category = $_POST['ticket_category'];
    $ticket_description = $_POST['ticket_description'];
    
    $insert_query = "INSERT INTO support_tickets (user_id, username, email, subject, category, description, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, 'Open', NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("isssss", $user_id, $username, $user_email, $ticket_subject, $ticket_category, $ticket_description);
    
    if ($insert_stmt->execute()) {
        $ticket_success = "Support ticket submitted successfully. Our team will respond soon.";
    } else {
        $ticket_error = "Failed to submit support ticket. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Research Hub | Support</title>
    
   
    <!--begin::Primary Meta Tags-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="title" content="Research Hub | Support" />
    <meta name="author" content="Research Hub" />
    <meta
      name="description"
      content="Research Hub Support Dashboard - Submit support tickets and manage your support requests."
    />
    <meta
      name="keywords"
      content="research hub, research papers, academic research, research management, support dashboard, support tickets"
    />
    <!--end::Primary Meta Tags-->
 


    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/adminlte.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">

     <!--begin::Fonts-->
     <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css"
      integrity="sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q="
      crossorigin="anonymous"
    />
    <!--end::Fonts-->
    <!--begin::Third Party Plugin(OverlayScrollbars)-->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css"
      integrity="sha256-tZHrRjVqNSRyWg2wbppGnT833E/Ys0DHWGwT04GiqQg="
      crossorigin="anonymous"
    />
    <!--end::Third Party Plugin(OverlayScrollbars)-->
    <!--begin::Third Party Plugin(Bootstrap Icons)-->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
      integrity="sha256-9kPW/n5nn53j4WMRYAxe9c1rCY96Oogo/MKSVdKzPmI="
      crossorigin="anonymous"
    />
    <!--end::Third Party Plugin(Bootstrap Icons)-->
    <!--begin::Required Plugin(AdminLTE)-->
    <link rel="stylesheet" href="../../dist/css/adminlte.css" />
    <!--end::Required Plugin(AdminLTE)-->
    <!-- apexcharts -->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css"
      integrity="sha256-4MX+61mt9NVvvuPjUWdUdyfZfxSB1/Rf9WtqRHgG5S0="
      crossorigin="anonymous"
    />
    <!-- jsvectormap -->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/css/jsvectormap.min.css"
      integrity="sha256-+uGLJmmTKOqBr+2E6KDYs/NRsHxSkONXFHUL0fy2O/4="
      crossorigin="anonymous" 
    />
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
        <?php require_once "topnav.php"; ?>
        <?php require_once "sidenav.php"; ?>

        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">Support Center</h1>
                        </div>
                    </div>
                </div>
            </div>

            <div class="app-content">
                <div class="container-fluid">
                    <div class="row">
                        <?php if ($role == 'admin'): ?>
                            <div class="col-md-12">
                                <div class="card card-primary card-outline">
                                    <div class="card-header">
                                        <h3 class="card-title">Admin Support Center</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="card">
                                                    <div class="card-header bg-info">
                                                        <h5 class="card-title text-white">Admin FAQs</h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <ul class="list-unstyled">
                                                            <li><a href="#" data-bs-toggle="modal" data-bs-target="#adminFaq1">User Management</a></li>
                                                            <li><a href="#" data-bs-toggle="modal" data-bs-target="#adminFaq2">System Configuration</a></li>
                                                            <li><a href="#" data-bs-toggle="modal" data-bs-target="#adminFaq3">Reporting Tools</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card">
                                                    <div class="card-header bg-success">
                                                        <h5 class="card-title text-white">Admin Resources</h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <ul class="list-unstyled">
                                                            <li><a href="#">Admin Dashboard Guide</a></li>
                                                            <li><a href="#">User Role Management</a></li>
                                                            <li><a href="#">System Backup Procedures</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card">
                                                    <div class="card-header bg-warning">
                                                        <h5 class="card-title text-white">Admin Support Channels</h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <p><strong>Technical Support:</strong> admin.support@researchhub.com</p>
                                                        <p><strong>Emergency Hotline:</strong> +1 (555) 888-9999</p>
                                                        <p><strong>Support Hours:</strong> 24/7</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($role == 'researcher'): ?>
                            <div class="col-md-12">
                                <div class="card card-primary card-outline">
                                    <div class="card-header">
                                        <h3 class="card-title">Researcher Support Center</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="card">
                                                    <div class="card-header bg-info">
                                                        <h5 class="card-title text-white">Researcher FAQs</h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <ul class="list-unstyled">
                                                            <li><a href="#" data-bs-toggle="modal" data-bs-target="#researcherFaq1">Research Project Management</a></li>
                                                            <li><a href="#" data-bs-toggle="modal" data-bs-target="#researcherFaq2">Data Collection Tools</a></li>
                                                            <li><a href="#" data-bs-toggle="modal" data-bs-target="#researcherFaq3">Publication Guidelines</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card">
                                                    <div class="card-header bg-success">
                                                        <h5 class="card-title text-white">Research Resources</h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <ul class="list-unstyled">
                                                            <li><a href="#">Research Methodology Guide</a></li>
                                                            <li><a href="#">Data Analysis Tools</a></li>
                                                            <li><a href="#">Collaboration Platforms</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card">
                                                    <div class="card-header bg-warning">
                                                        <h5 class="card-title text-white">Researcher Support</h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <p><strong>Research Support:</strong> research.help@researchhub.com</p>
                                                        <p><strong>Consultation Hotline:</strong> +1 (555) 777-5555</p>
                                                        <p><strong>Support Hours:</strong> Mon-Fri, 9 AM - 6 PM</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($role == 'user'): ?>
                            <div class="col-md-12">
                                <div class="card card-primary card-outline">
                                    <div class="card-header">
                                        <h3 class="card-title">User Support Center</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="card">
                                                    <div class="card-header bg-info">
                                                        <h5 class="card-title text-white">User FAQs</h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <ul class="list-unstyled">
                                                            <li><a href="#" data-bs-toggle="modal" data-bs-target="#userFaq1">Account Management</a></li>
                                                            <li><a href="#" data-bs-toggle="modal" data-bs-target="#userFaq2">Platform Navigation</a></li>
                                                            <li><a href="#" data-bs-toggle="modal" data-bs-target="#userFaq3">Profile Settings</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card">
                                                    <div class="card-header bg-success">
                                                        <h5 class="card-title text-white">User Resources</h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <ul class="list-unstyled">
                                                            <li><a href="#">Getting Started Guide</a></li>
                                                            <li><a href="#">User Tutorials</a></li>
                                                            <li><a href="#">Frequently Used Features</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card">
                                                    <div class="card-header bg-warning">
                                                        <h5 class="card-title text-white">User Support Channels</h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <p><strong>User Support:</strong> user.help@researchhub.com</p>
                                                        <p><strong>Help Desk:</strong> +1 (555) 666-4444</p>
                                                        <p><strong>Support Hours:</strong> Mon-Fri, 8 AM - 8 PM</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="col-md-12">
                                <div class="alert alert-warning">
                                    You do not have access to specific support resources. Please contact system administrator.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="app-content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-12 mt-3">
                            <div class="card card-primary card-outline">
                                <div class="card-header">
                                    <h3 class="card-title">Submit Support Ticket</h3>
                                </div>
                                <div class="card-body">
                                    <?php if(isset($ticket_success)): ?>
                                        <div class="alert alert-success"><?php echo $ticket_success; ?></div>
                                    <?php endif; ?>
                                    <?php if(isset($ticket_error)): ?>
                                        <div class="alert alert-danger"><?php echo $ticket_error; ?></div>
                                    <?php endif; ?>
                                    <form method="POST" action="">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="ticket_subject" class="form-label">Subject</label>
                                                <input type="text" class="form-control" id="ticket_subject" name="ticket_subject" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="ticket_category" class="form-label">Category</label>
                                                <select class="form-select" id="ticket_category" name="ticket_category" required>
                                                    <option value="">Select Category</option>
                                                    <option value="Technical Support">Technical Support</option>
                                                    <option value="Account Issue">Account Issue</option>
                                                    <option value="Research Assistance">Research Assistance</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="ticket_description" class="form-label">Description</label>
                                            <textarea class="form-control" id="ticket_description" name="ticket_description" rows="4" required></textarea>
                                        </div>
                                        <button type="submit" name="submit_ticket" class="btn btn-primary">Submit Ticket</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>


           

           
    <!-- FAQ Modals -->
    <div class="modal fade" id="faqModal1" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">How to Add a New Researcher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>To add a new researcher, navigate to the 'Total Researchers' page and click 'Add New Researcher'. Fill in the required details and submit the form.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- More modals for other FAQs -->

    <!-- Admin FAQ Modals -->
    <?php if ($role == 'admin'): ?>
    <div class="modal fade" id="adminFaq1" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Management</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Learn how to add, modify, and manage user accounts and roles in the Research Hub system.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="adminFaq2" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">System Configuration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Detailed guide on configuring system settings, security protocols, and integration options.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="adminFaq3" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reporting Tools</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Instructions for generating comprehensive system reports and analytics.</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Researcher FAQ Modals -->
    <?php if ($role == 'researcher'): ?>
    <div class="modal fade" id="researcherFaq1" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Research Project Management</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Best practices for managing research projects, tracking progress, and collaborating with team members.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="researcherFaq2" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Data Collection Tools</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Overview of available data collection and research tools integrated with the Research Hub platform.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="researcherFaq3" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Publication Guidelines</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Comprehensive guidelines for publishing research, including formatting, citation, and submission processes.</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- User FAQ Modals -->
    <?php if ($role == 'user'): ?>
    <div class="modal fade" id="userFaq1" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Account Management</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Learn how to manage your account, update personal information, and maintain account security.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="userFaq2" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Platform Navigation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Comprehensive guide to navigating the Research Hub platform, understanding different sections and features.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="userFaq3" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Profile Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Detailed instructions on customizing your profile, privacy settings, and notification preferences.</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Required JavaScript files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/adminlte.min.js"></script>

    <!--begin::Third Party Plugin(OverlayScrollbars)-->
    <script
      src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/browser/overlayscrollbars.browser.es6.min.js"
      integrity="sha256-dghWARbRe2eLlIJ56wNB+b760ywulqK3DzZYEpsg2fQ="
      crossorigin="anonymous"
    ></script>
    <!--end::Third Party Plugin(OverlayScrollbars)--><!--begin::Required Plugin(popperjs for Bootstrap 5)-->
    <script
      src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
      integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
      crossorigin="anonymous"
    ></script>
    <!--end::Required Plugin(popperjs for Bootstrap 5)--><!--begin::Required Plugin(Bootstrap 5)-->
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"
      integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT0to5eqruptLy"
      crossorigin="anonymous"
    ></script>
    <!--end::Required Plugin(Bootstrap 5)--><!--begin::Required Plugin(AdminLTE)-->
    <script src="../../dist/js/adminlte.js"></script>
    <!--end::Required Plugin(AdminLTE)--><!--begin::OverlayScrollbars Configure-->
    <script>
      const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
      const Default = {
        scrollbarTheme: 'os-theme-light',
        scrollbarAutoHide: 'leave',
        scrollbarClickScroll: true,
      };
      document.addEventListener('DOMContentLoaded', function () {
        const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
        if (sidebarWrapper && typeof OverlayScrollbarsGlobal?.OverlayScrollbars !== 'undefined') {
          OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
            scrollbars: {
              theme: Default.scrollbarTheme,
              autoHide: Default.scrollbarAutoHide,
              clickScroll: Default.scrollbarClickScroll,
            },
          });
        }
      });
    </script>
    <!--end::OverlayScrollbars Configure-->
    <!-- OPTIONAL SCRIPTS -->
    <!-- sortablejs -->
    <script
      src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"
      integrity="sha256-+ipiJrswvAR4VAx/th+6zWsdeYmVae0iJuiR+6OqHJHQ="
      crossorigin="anonymous"
    ></script>
    <!-- sortablejs -->
    <script>
      const connectedSortables = document.querySelectorAll('.connectedSortable');
      connectedSortables.forEach((connectedSortable) => {
        let sortable = new Sortable(connectedSortable, {
          group: 'shared',
          handle: '.card-header',
        });
      });

      const cardHeaders = document.querySelectorAll('.connectedSortable .card-header');
      cardHeaders.forEach((cardHeader) => {
        cardHeader.style.cursor = 'move';
      });
    </script>
    <!-- apexcharts -->
    <script
      src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js"
      integrity="sha256-+vh8GkaU7C9/wbSLIcwq82tQ2wTf44aOHA8HlBMwRI8="
      crossorigin="anonymous"
    ></script>
    <script
      src="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/js/jsvectormap.min.js"
      integrity="sha256-/t1nN2956BT869E6H4V1dnt0X5pAQHPytli+1nTZm2Y="
      crossorigin="anonymous"
    ></script>
    <script
      src="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/maps/world.js"
      integrity="sha256-XPpPaZlU8S/HWf7FZLAncLg2SAkP8ScUTII89x9D3lY="
      crossorigin="anonymous"
    ></script>

    
        <!-- Add this modal at the bottom of the body tag -->
<!-- Add required Bootstrap JS at the end of the body -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all dropdowns
    var dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'))
    var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl)
    });
});
</script>
</body>
</html>