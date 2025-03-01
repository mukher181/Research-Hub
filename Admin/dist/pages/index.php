<?php
session_start();
include 'config.php';

// Check if user is not logged in
if (!isset($_SESSION['username']) && !isset($_SESSION['user_email'])) {
  header("Location: login.php"); // Redirect to login page
  exit();
}

// Get user identifier (either username or email)
$identifier = $_SESSION['username'] ?? $_SESSION['user_email'];
$identifier_type = isset($_SESSION['username']) ? 'username' : 'email';

// Check if account is active
$check_active_sql = "SELECT is_active FROM users WHERE $identifier_type = ?";
$check_stmt = $conn->prepare($check_active_sql);
$check_stmt->bind_param("s", $identifier);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $user_data = $check_result->fetch_assoc();
    if ($user_data['is_active'] == 0) {
        // Account is inactive
        session_destroy();
        header("Location: login.php?error=inactive_account");
        exit();
    }
}

// Get user data from session
$user_name = $_SESSION['username'] ?? '';
$user_role = $_SESSION['role'] ?? 'Admin';
$join_date = $_SESSION['join_date'] ?? 'Jan. 2024';

// Fetch additional user data from database
$sql = "SELECT Image, email, bio_description, role, research_interests FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $_SESSION['profile_image'] = $row['Image'];
    $_SESSION['email'] = $row['email'];
    $_SESSION['bio'] = $row['bio_description'] ?? '';
    $_SESSION['interests'] = $row['research_interests'] ?? '';
    $_SESSION['role'] = $row['role'] ?? '';
} 
$role = $_SESSION['role'];

// Get total users count from database
$sql_count = "SELECT COUNT(*) as total_users FROM users where role = 'user' ";
$result_count = $conn->query($sql_count);
$total_users = 0;
if ($result_count) {
    $row_count = $result_count->fetch_assoc();
    $total_users = $row_count['total_users'];
}
// Get total researchers count from database
$sql_count = "SELECT COUNT(*) as researchers FROM users where role = 'researcher' ";
$result_count = $conn->query($sql_count);
$researchers = 0;
if ($result_count) {
    $row_count = $result_count->fetch_assoc();
    $researchers = $row_count['researchers'];
}

// Get total papers count
$sql_papers = "SELECT COUNT(*) as total_papers FROM research_uploads";
$result_papers = $conn->query($sql_papers);
$total_papers = 0;
if ($result_papers) {
    $row_papers = $result_papers->fetch_assoc();
    $total_papers = $row_papers['total_papers'];
}

// Get active papers count
$sql_active = "SELECT COUNT(*) as active_papers FROM research_uploads WHERE status = 'active'";
$result_active = $conn->query($sql_active);
$active_papers = 0;
if ($result_active) {
    $row_active = $result_active->fetch_assoc();
    $active_papers = $row_active['active_papers'];
}

// Get pending papers count
$sql_pending = "SELECT COUNT(*) as pending_papers FROM research_uploads WHERE status = 'inactive'";
$result_pending = $conn->query($sql_pending);
$pending_papers = 0;
if ($result_pending) {
    $row_pending = $result_pending->fetch_assoc();
    $pending_papers = $row_pending['pending_papers'];
}

// Get monthly paper submission data for the last 6 months
$sql_monthly = "SELECT 
    DATE_FORMAT(uploaded_at, '%M') as month,
    COUNT(*) as paper_count,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count
    FROM research_uploads 
    WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(uploaded_at, '%M')
    ORDER BY uploaded_at DESC";
$result_monthly = $conn->query($sql_monthly);

$months = [];
$paperCounts = [];
$activePaperCounts = [];
$inactivePaperCounts = [];

while ($row = $result_monthly->fetch_assoc()) {
    $months[] = $row['month'];
    $paperCounts[] = $row['paper_count'];
    $activePaperCounts[] = $row['active_count'];
    $inactivePaperCounts[] = $row['inactive_count'];
}

// Get monthly user registration data
$sql_users = "SELECT 
    DATE_FORMAT(join_date, '%M') as month,
    COUNT(*) as user_count
    FROM users 
    WHERE join_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    AND role = 'user'
    GROUP BY DATE_FORMAT(join_date, '%M')
    ORDER BY join_date DESC";
$result_users = $conn->query($sql_users);

$userCounts = [];
while ($row = $result_users->fetch_assoc()) {
    $userCounts[] = $row['user_count'];
}

// Get monthly researcher data
$sql_researchers = "SELECT 
    DATE_FORMAT(join_date, '%M') as month,
    COUNT(*) as researcher_count,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_count
    FROM users 
    WHERE join_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    AND role = 'researcher'
    GROUP BY DATE_FORMAT(join_date, '%M')
    ORDER BY join_date DESC";
$result_researchers = $conn->query($sql_researchers);

$researcherCounts = [];
$activeResearcherCounts = [];
$inactiveResearcherCounts = [];
$researcher_months = [];

while ($row = $result_researchers->fetch_assoc()) {
    $researcher_months[] = $row['month'];
    $researcherCounts[] = $row['researcher_count'];
    $activeResearcherCounts[] = $row['active_count'];
    $inactiveResearcherCounts[] = $row['inactive_count'];
}

// Get acceptance rate data
$sql_acceptance = "SELECT 
    COUNT(CASE WHEN status = 'approved' THEN 1 END) * 100.0 / COUNT(*) as acceptance_rate
    FROM research_uploads";
$result_acceptance = $conn->query($sql_acceptance);
$acceptance_rate = 0;
if ($result_acceptance && $row = $result_acceptance->fetch_assoc()) {
    $acceptance_rate = round($row['acceptance_rate']);
}

// Get active and inactive user counts for the last 6 months
$activeUserCounts = array();
$inactiveUserCounts = array();

$sql_active_users = "SELECT 
    DATE_FORMAT(join_date, '%M') as month,
    COUNT(*) as user_count
    FROM users 
    WHERE join_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    AND role = 'user'
    AND is_active = 1
    GROUP BY DATE_FORMAT(join_date, '%M')
    ORDER BY join_date DESC";

$sql_inactive_users = "SELECT 
    DATE_FORMAT(join_date, '%M') as month,
    COUNT(*) as user_count
    FROM users 
    WHERE join_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    AND role = 'user'
    AND is_active = 0
    GROUP BY DATE_FORMAT(join_date, '%M')
    ORDER BY join_date DESC";

$result_active_users = $conn->query($sql_active_users);
$result_inactive_users = $conn->query($sql_inactive_users);

// Create associative arrays to store counts by month
$active_by_month = array();
$inactive_by_month = array();

while ($row = $result_active_users->fetch_assoc()) {
    $active_by_month[$row['month']] = $row['user_count'];
}

while ($row = $result_inactive_users->fetch_assoc()) {
    $inactive_by_month[$row['month']] = $row['user_count'];
}

// Fill the arrays in the same order as months array
foreach ($months as $month) {
    $activeUserCounts[] = isset($active_by_month[$month]) ? $active_by_month[$month] : 0;
    $inactiveUserCounts[] = isset($inactive_by_month[$month]) ? $inactive_by_month[$month] : 0;
}
?>

<!doctype html>
<html lang="en">
  <!--begin::Head-->
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Research Hub | Admin Dashboard</title>
    <!--begin::Primary Meta Tags-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="title" content="Research Hub | Admin Dashboard" />
    <meta name="author" content="Research Hub" />
    <meta
      name="description"
      content="Research Hub Admin Dashboard - Manage research papers, users, and analytics."
    />
    <meta
      name="keywords"
      content="research hub, research papers, academic research, research management, admin dashboard, analytics"
    />
    <!--end::Primary Meta Tags-->
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
    <style>
         .admin-message {
             font-weight: bold;
             color: #007bff; /* Bootstrap primary blue, adjust as needed */
             background-color: #f8f9fa; /* Light background to make it stand out */
          }
    </style>
  </head>
  <!--end::Head-->
  <!--begin::Body-->
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <!--begin::App Wrapper-->
    <div class="app-wrapper">

    <?php require_once "topnav.php";?>
    <?php require_once "sidenav.php";?>

      <!--begin::App Main-->
      <main class="app-main">
        <!--begin::App Content Header-->
        <div class="app-content-header">
          <!--begin::Container-->
          <div class="container-fluid">
            <!--begin::Row-->
            <div class="row">
              <div class="col-sm-6"><h3 class="mb-0">Research Hub Dashboard</h3></div>
              <div class="col-sm-6">
                <ol class="breadcrumb float-sm-end">
                  <li class="breadcrumb-item"><a href="#">Home</a></li>
                  <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                </ol>
              </div>
            </div>
            <!--end::Row-->
          </div>
          <!--end::Container-->
        </div>
        <!--end::App Content Header-->
        <!--begin::App Content-->
        <div class="app-content">
          <!--begin::Container-->
          <div class="container-fluid">
            <!--begin::Row-->
            <div class="row">
              <!--begin::Col-->
              <?php if ($role === 'admin'): ?>
              <div class="col-lg-3 col-6">
                <!--begin::Small Box Widget-->
                <div class="small-box text-bg-warning"><a href="total_users.php" class="text-white text-decoration-none">
                  <div class="inner" style="padding-left:20px;">
                    <h3 style=" padding-top:10px;"><?php echo $total_users; ?></h3>
                    <p>Total Users</p>
                  </div>
                  <svg
                    class="small-box-icon"
                    fill="currentColor" 
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                  >
                    <path
                      d="M4.5 6.375a4.125 4.125 0 118.25 0 4.125 4.125 0 01-8.25 0zM14.25 8.625a3.375 3.375 0 116.75 0 3.375 3.375 0 01-6.75 0zM1.5 19.125a7.125 7.125 0 0114.25 0v.003l-.001.119a.75.75 0 01-.363.63 13.067 13.067 0 01-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 01-.364-.63l-.001-.122zM17.25 19.128l-.001.144a2.25 2.25 0 01-.233.96 10.088 10.088 0 005.06-1.01.75.75 0 00.42-.643 4.875 4.875 0 00-6.957-4.611 8.586 8.586 0 011.71 5.157v.003z"
                    ></path>
                  </svg>
                  <a
                    href="total_users.php"
                    class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover"
                  >
                    More info <i class="bi bi-link-45deg"></i>
                  </a></a>
                </div>
                <!--end::Small Box Widget-->
              </div>
              <?php endif; ?>
              
              <?php if ($role === 'researcher'): ?>
              <div class="col-lg-3 col-6">
                <!--begin::Small Box Widget-->
                <div class="small-box text-bg-info"><a href="upload_paper.php" class="text-white text-decoration-none">
                  <div class="inner" style="padding-left:20px;">
                    <h3 style="padding-top:10px;">Upload</h3>
                    <p>New Research Paper</p>
                  </div>
                  <svg
                    class="small-box-icon"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                  >
                    <path
                      d="M11.47 1.72a.75.75 0 011.06 0l3 3a.75.75 0 01-1.06 1.06l-1.72-1.72V7.5h-1.5V4.06L9.53 5.78a.75.75 0 01-1.06-1.06l3-3zM11.25 7.5V15a.75.75 0 001.5 0V7.5h3.75a3 3 0 013 3v9a3 3 0 01-3 3h-9a3 3 0 01-3-3v-9a3 3 0 013-3h3.75z"
                    ></path>
                  </svg>
                  <a
                    href="upload_research.php"
                    class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover"
                  >
                    Upload Now <i class="bi bi-link-45deg"></i>
                  </a>
                </div>
                <!--end::Small Box Widget-->
              </div>
              <?php endif; ?>
              
              <div class="col-lg-3 col-6">
                <!--begin::Small Box Widget 1-->
                <div class="small-box text-bg-primary"><a href="total_papers.php" class="text-white text-decoration-none">
                  <div class="inner"style="padding-left:20px;">
                  <h3 style=" padding-top:10px;"><?php echo $total_papers; ?></h3>
                    <p>Total Papers</p>
                  </div>
                  <svg
                    class="small-box-icon"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                  >
                    <path
                      d="M2.25 2.25a.75.75 0 000 1.5h1.386c.17 0 .318.114.362.278l2.558 9.592a3.752 3.752 0 00-2.806 3.63c0 .414.336.75.75.75h15.75a.75.75 0 000-1.5H5.378A2.25 2.25 0 017.5 15h11.218a.75.75 0 001.5 0v2.25a.75.75 0 001.5 0v-2.25H22a.75.75 0 000-1.5h-2.25V7.5z"
                    ></path>
                  </svg>
                  <a
                    href="total_papers.php"
                    class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover"
                  >
                    More info <i class="bi bi-link-45deg"></i></a>
                  </a>
                </div>
                <!--end::Small Box Widget 1-->
              </div>
              <!--end::Col-->
              
              <?php if ($role === 'admin'): ?>
              <div class="col-lg-3 col-6">
                <!--begin::Small Box Widget 3-->
                <div class="small-box text-bg-warning"><a href="total_researchers.php" class="text-white text-decoration-none">
                  <div class="inner" style="padding-left:20px; color:black;">
                    <h3 style=" padding-top:10px;"><?php echo $researchers; ?></h3>
                    <p>New Researchers</p>
                  </div>
                  <svg
                    class="small-box-icon"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                  >
                    <path
                      d="M6.25 6.375a4.125 4.125 0 118.25 0 4.125 4.125 0 01-8.25 0zM3.25 19.125a7.125 7.125 0 0114.25 0v.003l-.001.119a.75.75 0 01-.363.63 13.067 13.067 0 01-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 01-.364-.63l-.001-.122zM17.25 19.128l-.001.144a2.25 2.25 0 01-.233.96 10.088 10.088 0 005.06-1.01.75.75 0 00.42-.643 4.875 4.875 0 00-6.957-4.611 8.586 8.586 0 011.71 5.157v.003z"
                    ></path>
                  </svg>
                  <a
                    href="total_researchers.php"
                    class="small-box-footer link-dark link-underline-opacity-0 link-underline-opacity-50-hover"
                  >
                    More info <i class="bi bi-link-45deg"></i>
                  </a>
                </div>
                <!--end::Small Box Widget 3-->
              </div>
              <?php endif; ?>
              <!--end::Col-->
              <?php if ($role === 'admin'): ?>
              <div class="col-lg-3 col-6">
                <!--begin::Small Box Widget 4-->
                <div class="small-box text-bg-danger"><a href="total_papers.php?status=inactive" class="text-white text-decoration-none">
                  <div class="inner" style="padding-left:20px;">
                    <h3 style="padding-top:10px;"><?php echo $pending_papers; ?></h3>
                    <p>Papers Pending Review</p>
                  </div>
                  <svg
                    class="small-box-icon"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                  >
                    <path
                      clip-rule="evenodd"
                      fill-rule="evenodd"
                      d="M2.25 13.5a8.25 8.25 0 018.25-8.25.75.75 0 01.75.75v6.75H18a.75.75 0 01.75.75 8.25 8.25 0 01-16.5 0z"
                    ></path>
                    <path
                      clip-rule="evenodd"
                      fill-rule="evenodd"
                      d="M12.75 3a.75.75 0 01.75-.75 8.25 8.25 0 018.25 8.25.75.75 0 01-.75.75h-7.5a3 3 0 013-3h3.75z"
                    ></path>
                  </svg>
                  <a
                    href="total_papers.php?status=inactive"
                    class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover"
                  >
                    More info <i class="bi bi-link-45deg"></i>
                  </a>
                </div>
                <!--end::Small Box Widget 4-->
              </div>
              <?php endif; ?>
              <div class="col-lg-3 col-6">
                <!--begin::Small Box Widget 5-->
                <div class="small-box text-bg-success"><a href="view_papers.php" class="text-white text-decoration-none">
                  <div class="inner" style="padding-left:20px;">
                    <h3 style="padding-top:15px;font-size:30px;">View</h3>
                    <p>Published Research Papers</p>
                  </div>
                  <svg
                    class="small-box-icon"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                  >
                    <path
                      d="M11.25 4.533A9.707 9.707 0 006 3a9.735 9.735 0 00-3.25.555.75.75 0 00-.5.707v14.25a.75.75 0 001 .707A8.237 8.237 0 016 18.75c1.995 0 3.823.707 5.25 1.886V4.533zM12.75 20.636A8.214 8.214 0 0118 18.75c.966 0 1.89.166 2.75.47a.75.75 0 001-.708V4.262a.75.75 0 00-.5-.707A9.735 9.735 0 0018 3a9.707 9.707 0 00-5.25 1.533v16.103z"
                    ></path>
                  </svg>
                  <a
                    href="view_papers.php"
                    class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover"
                  >
                    View Papers <i class="bi bi-link-45deg"></i>
                  </a>
                </div>
                <!--end::Small Box Widget 5-->
              </div>
              

              <?php if ($role === 'admin'): ?>
              <div class="col-lg-3 col-6">
                <!--begin::Small Box Widget 6-->
                <div class="small-box text-bg-primary"><a href="generate_report.php" class="text-white text-decoration-none">
                  <div class="inner" style="padding-left:20px;">
                    <h3 style="padding-top:15px;font-size:30px;">Reports</h3>
                    <p>Generate Advanced Reports</p>
                  </div>
                  <svg
                    class="small-box-icon"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                  >
                    <path
                      d="M7.5 3.375c0-1.036.84-1.875 1.875-1.875h.375a3.75 3.75 0 013.75 3.75v1.875C13.5 8.161 14.34 9 15.375 9h1.875A3.75 3.75 0 0121 12.75v3.375C21 17.16 20.16 18 19.125 18h-9.75A1.875 1.875 0 017.5 16.125V3.375z"
                    />
                    <path
                      d="M15 5.25a5.23 5.23 0 00-1.279-3.434 9.768 9.768 0 016.963 6.963A5.23 5.23 0 0017.25 7.5h-1.875A.375.375 0 0115 7.125V5.25zM4.875 6H6v10.125A3.375 3.375 0 009.375 19.5H16.5v1.125c0 1.035-.84 1.875-1.875 1.875h-9.75A1.875 1.875 0 013 20.625V7.875C3 6.839 3.84 6 4.875 6z"
                    />
                  </svg>
                  <a
                    href="generate_report.php"
                    class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover"
                  >
                    Generate Reports <i class="bi bi-link-45deg"></i>
                  </a>
                </div>
                <!--end::Small Box Widget 6-->
              </div>
              
            </div>
            <!--end::Row-->
            <!--begin::Row-->
            <div class="row">
              <!-- Start col -->
              <div class="col-lg-6 connectedSortable">
                <div class="card mb-4">
                  <div class="card-header"><h3 class="card-title">Paper Submissions Trend</h3></div>
                  <div class="card-body"><div id="revenue-chart"></div></div>
                </div>
                <!-- /.card -->
              
              </div>
                 <!-- Start col -->
    <div class="col-lg-6 connectedSortable">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Monthly User Growth</h3>
            </div>
            <div class="card-body">
                <div id="user-growth-chart"></div>
            </div>
        </div>
    </div>
    <!-- End col -->
    <!-- Start col -->
    <div class="col-lg-6 connectedSortable">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Monthly Researcher Growth</h3>
            </div>
            <div class="card-body">
                <div id="researcher-growth-chart"></div>
            </div>
        </div>
    </div>
    <!-- End col -->
    <?php endif; ?>
              <!-- /.Start col -->
               
              <?php if (in_array($role, ['admin', 'researcher'])): ?>
             <!-- DIRECT CHAT -->
             <div class="card direct-chat direct-chat-primary mb-4">
               <div class="card-header">
                   <h3 class="card-title">Research Discussions (Real-time)</h3>
               </div>
               <div class="card-body">
                   <div id="research-discussion-messages" class="direct-chat-messages" style="height: 300px; overflow-y: scroll;">
                       <!-- Messages will be dynamically loaded here -->
                   </div>
               </div>
               <div class="card-footer">
                   <div class="input-group">
                       <input type="text" id="research-message-input" placeholder="Type your message..." class="form-control">
                       <div class="input-group-append">
                           <button id="research-send-message" class="btn btn-primary">Send</button>
                       </div>
                   </div>
               </div>
             </div>
                <!-- /.direct-chat -->
                <?php endif; ?>
            </div>
            <!-- /.row (main row) -->
          </div>
          <!--end::Container-->
        </div>
        <!--end::App Content-->
      </main>
      <!--end::App Main-->
      <!--begin::Footer-->
     
      <!--end::Footer-->
    </div>
    <!--end::App Wrapper-->
    <!--begin::Script-->
    <script src="discussion.js"></script>
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
    <!-- ChartJS -->
    <script>
      // NOTICE!! DO NOT USE ANY OF THIS JAVASCRIPT
      // IT'S ALL JUST JUNK FOR DEMO
      // ++++++++++++++++++++++++++++++++++++++++++

      const sales_chart_options = {
        series: [{
          name: 'Total Papers',
          data: <?php echo json_encode($paperCounts); ?>
        }, {
          name: 'Active Papers',
          data: <?php echo json_encode($activePaperCounts); ?>
        }, {
          name: 'Inactive Papers',
          data: <?php echo json_encode($inactivePaperCounts); ?>
        }],
        chart: {
          height: 300,
          type: 'bar',
          toolbar: {
            show: false
          }
        },
        plotOptions: {
          bar: {
            horizontal: false,
            columnWidth: '55%',
            endingShape: 'rounded'
          },
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          show: true,
          width: 2,
          colors: ['transparent']
        },
        xaxis: {
          categories: <?php echo json_encode($months); ?>,
          title: {
            text: 'Last 6 Months'
          }
        },
        yaxis: {
          title: {
            text: 'Number of Papers'
          }
        },
        fill: {
          opacity: 1
        },
        tooltip: {
          y: {
            formatter: function (val) {
              return val + " papers"
            }
          }
        },
        colors: ['#0d6efd', '#198754', '#dc3545']
      };

      const userGrowthOptions = {
        series: [{
          name: 'Total Users',
          data: <?php echo json_encode($userCounts); ?>
        }, {
          name: 'Active Users',
          data: <?php echo json_encode($activeUserCounts); ?>
        }, {
          name: 'Inactive Users',
          data: <?php echo json_encode($inactiveUserCounts); ?>
        }],
        chart: {
          height: 300,
          type: 'bar',
          toolbar: {
            show: false
          }
        },
        plotOptions: {
          bar: {
            horizontal: false,
            columnWidth: '55%',
            endingShape: 'rounded'
          },
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          show: true,
          width: 2,
          colors: ['transparent']
        },
        xaxis: {
          categories: <?php echo json_encode($months); ?>,
          title: {
            text: 'Last 6 Months'
          }
        },
        yaxis: {
          title: {
            text: 'Number of Users'
          }
        },
        fill: {
          opacity: 1
        },
        tooltip: {
          y: {
            formatter: function (val) {
              return val + " users"
            }
          }
        },
        colors: ['#0d6efd', '#198754', '#dc3545']
      };

      const researcherGrowthOptions = {
        series: [{
          name: 'Total Researchers',
          data: <?php echo json_encode($researcherCounts); ?>
        }, {
          name: 'Active Researchers',
          data: <?php echo json_encode($activeResearcherCounts); ?>
        }, {
          name: 'Inactive Researchers',
          data: <?php echo json_encode($inactiveResearcherCounts); ?>
        }],
        chart: {
          height: 300,
          type: 'bar',
          toolbar: {
            show: false
          }
        },
        plotOptions: {
          bar: {
            horizontal: false,
            columnWidth: '55%',
            endingShape: 'rounded'
          },
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          show: true,
          width: 2,
          colors: ['transparent']
        },
        xaxis: {
          categories: <?php echo json_encode($researcher_months); ?>,
          title: {
            text: 'Last 6 Months'
          }
        },
        yaxis: {
          title: {
            text: 'Number of Researchers'
          }
        },
        fill: {
          opacity: 1
        },
        tooltip: {
          y: {
            formatter: function (val) {
              return val + " researchers"
            }
          }
        },
        colors: ['#0d6efd', '#198754', '#dc3545']
      };

      const sales_chart = new ApexCharts(
        document.querySelector('#revenue-chart'),
        sales_chart_options
      );
      sales_chart.render();

      const userGrowthChart = new ApexCharts(
        document.querySelector('#user-growth-chart'),
        userGrowthOptions
      );
      userGrowthChart.render();

      const researcherGrowthChart = new ApexCharts(
        document.querySelector('#researcher-growth-chart'),
        researcherGrowthOptions
      );
      researcherGrowthChart.render();
    </script>
    <!-- jsvectormap -->
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
    <!-- jsvectormap -->
    <script>
      const visitorsData = {
        US: 398, // USA
        SA: 400, // Saudi Arabia
        CA: 1000, // Canada
        DE: 500, // Germany
        FR: 760, // France
        CN: 300, // China
        AU: 700, // Australia
        BR: 600, // Brazil
        IN: 800, // India
        GB: 320, // Great Britain
        RU: 3000, // Russia
      };

      // World map by jsVectorMap
      const map = new jsVectorMap({
        selector: '#world-map',
        map: 'world',
      });

      // Sparkline charts
      const option_sparkline1 = {
        series: [
          {
            data: [1000, 1200, 920, 927, 931, 1027, 819, 930, 1021],
          },
        ],
        chart: {
          type: 'area',
          height: 50,
          sparkline: {
            enabled: true,
          },
        },
        stroke: {
          curve: 'straight',
        },
        fill: {
          opacity: 0.3,
        },
        yaxis: {
          min: 0,
        },
        colors: ['#DCE6EC'],
      };

      const sparkline1 = new ApexCharts(document.querySelector('#sparkline-1'), option_sparkline1);
      sparkline1.render();

      const option_sparkline2 = {
        series: [
          {
            data: [515, 519, 520, 522, 652, 810, 370, 627, 319, 630, 921],
          },
        ],
        chart: {
          type: 'area',
          height: 50,
          sparkline: {
            enabled: true,
          },
        },
        stroke: {
          curve: 'straight',
        },
        fill: {
          opacity: 0.3,
        },
        yaxis: {
          min: 0,
        },
        colors: ['#DCE6EC'],
      };

      const sparkline2 = new ApexCharts(document.querySelector('#sparkline-2'), option_sparkline2);
      sparkline2.render();

      const option_sparkline3 = {
        series: [
          {
            data: [15, 19, 20, 22, 33, 27, 31, 27, 19, 30, 21],
          },
        ],
        chart: {
          type: 'area',
          height: 50,
          sparkline: {
            enabled: true,
          },
        },
        stroke: {
          curve: 'straight',
        },
        fill: {
          opacity: 0.3,
        },
        yaxis: {
          min: 0,
        },
        colors: ['#DCE6EC'],
      };

      const sparkline3 = new ApexCharts(document.querySelector('#sparkline-3'), option_sparkline3);
      sparkline3.render();
    </script>
    <!--end::Script-->
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
  <!--end::Body-->
</html>
