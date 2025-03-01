<?php
session_start();
include 'config.php';

// Check if user is not logged in
if (!isset($_SESSION['username']) && !isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

// Get user data from session
$user_name = $_SESSION['username'] ?? '';
$user_role = $_SESSION['role'] ?? '';

// Initialize filters
$userType = $_GET['user_type'] ?? 'all_all';
$dateRange = $_GET['date_range'] ?? '30';
$searchTerm = $_GET['search'] ?? '';
$paperStatus = $_GET['paper_status'] ?? 'all';
$sortBy = $_GET['sort_by'] ?? 'latest';

// Get user statistics
$activeUsers = 0;
$inactiveUsers = 0;
$adminUsers = 0;
$researcherUsers = 0;
$regularUsers = 0;

$userQuery = "SELECT * FROM users WHERE 1=1";
$userResult = $conn->query($userQuery);
while($row = $userResult->fetch_assoc()) {
    if($row['is_active'] == 1) {
        $activeUsers++;
    } else {
        $inactiveUsers++;
    }
    
    // Count user types
    if($row['role'] == 'admin') {
        $adminUsers++;
    } else if($row['role'] == 'researcher') {
        $researcherUsers++;
    } else {
        $regularUsers++;
    }
}
$userResult->data_seek(0);

// Prepare date conditions
$dateCondition = "";
$paperDateCondition = "";
if ($dateRange != 'all') {
    if ($dateRange == 'custom' && isset($_GET['start_date']) && isset($_GET['end_date'])) {
        $start_date = $_GET['start_date'];
        $end_date = $_GET['end_date'];
        $dateCondition = " AND DATE(join_date) BETWEEN '$start_date' AND '$end_date'";
        $paperDateCondition = " AND DATE(uploaded_at) BETWEEN '$start_date' AND '$end_date'";
    } else {
        $dateCondition = " AND DATE(join_date) >= DATE_SUB(CURRENT_DATE, INTERVAL $dateRange DAY)";
        $paperDateCondition = " AND DATE(uploaded_at) >= DATE_SUB(CURRENT_DATE, INTERVAL $dateRange DAY)";
    }
}

// Prepare search conditions
$searchCondition = "";
$paperSearchCondition = "";
if (!empty($searchTerm)) {
    $searchTerm = "%$searchTerm%";
    $searchCondition = " AND (Username LIKE '$searchTerm' OR Email LIKE '$searchTerm' OR research_interests LIKE '$searchTerm')";
    $paperSearchCondition = " AND (title LIKE '$searchTerm' OR description LIKE '$searchTerm' OR uploaded_by LIKE '$searchTerm')";
}

// Prepare user type condition
$userTypeCondition = "";
if ($userType != 'all_all') {
    $userTypeParts = explode('_', $userType);
    $userTypeCondition = " AND role = '$userTypeParts[0]'";
    if ($userTypeParts[1] != 'all') {
        $userTypeCondition .= " AND is_active = " . ($userTypeParts[1] == 'active' ? 1 : 0);
    }
}

// Get users data
$userQuery = "SELECT * FROM users WHERE 1=1 $dateCondition $searchCondition $userTypeCondition";
$userResult = $conn->query($userQuery);

// Get papers data with status filter
$paperCondition = $paperStatus != 'all' ? " AND status = '$paperStatus'" : "";
$paperQuery = "SELECT * FROM research_uploads WHERE 1=1 $paperDateCondition $paperCondition $paperSearchCondition";
$paperResult = $conn->query($paperQuery);

// Get paper statistics
$activePapers = 0;
$inactivePapers = 0;
while($row = $paperResult->fetch_assoc()) {
    if($row['status'] == 'active') {
        $activePapers++;
    } else {
        $inactivePapers++;
    }
}
$paperResult->data_seek(0);

// Get sharing statistics
$sharingQuery = "SELECT p.*, COUNT(ps.id) as share_count 
                 FROM research_uploads p 
                 LEFT JOIN paper_shares ps ON p.id = ps.paper_id 
                 WHERE 1=1 $paperDateCondition
                 GROUP BY p.id";
$sharingResult = $conn->query($sharingQuery);

$totalShares = 0;
while($row = $sharingResult->fetch_assoc()) {
    $totalShares += $row['share_count'];
}
if ($paperResult->num_rows > 0) {
    $averageShares = $totalShares / $paperResult->num_rows;
} else {
    $averageShares = 0;
    echo '';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Hub | Report Generator</title>
    
    <!-- Include your existing CSS -->
    <link rel="stylesheet" href="../../dist/css/adminlte.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Add Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        body {
            background-color: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }


        .app-content {
            padding: 30px 0;
        }

        /* Filter Card Styles */
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .filter-card .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .filter-card .form-select,
        .filter-card .form-control {
            border: 1px solid #e0e6ed;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .filter-card .form-select:focus,
        .filter-card .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .filter-card .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .filter-card .btn-primary {
            background: #3498db;
            border: none;
        }

        .filter-card .btn-success {
            background: #2ecc71;
            border: none;
        }

        .filter-card .btn-danger {
            background: #e74c3c;
            border: none;
        }

        .filter-card .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .table-container h4 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .table-container h4 i {
            margin-right: 10px;
            color: #3498db;
        }

        .table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }

        .table th {
            background: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            padding: 15px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
        }

        .table td {
            padding: 15px;
            vertical-align: middle;
            border-top: 1px solid #f1f1f1;
            font-size: 0.9rem;
            color: #444;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
            transform: scale(1.01);
        }
/* Status Badges */
        .badge {
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.8rem;
        }

        .badge-active {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .badge-inactive {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        
        /* Responsive Design */
        @media (max-width: 768px) {
            .filter-card {
                padding: 15px;
            }
            
            .table-container {
                padding: 15px;
                overflow-x: auto;
            }

            .stat-card {
                margin-bottom: 15px;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: #3498db;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #2980b9;
        }

        /* Stats Row Styles */
        .stats-row {
            margin: 0 -10px 30px -10px;
            display: flex;
            flex-wrap: wrap;
        }

        .stats-row .col-md-3 {
            padding: 0 10px;
            margin-bottom: 20px;
            flex: 0 0 25%;
            max-width: 25%;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            height: 100%;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .stat-card .icon {
            font-size: 1.8rem;
            margin-bottom: 15px;
            background: linear-gradient(45deg, #3498db, #2980b9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            opacity: 0.9;
        }

        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            font-family: 'Poppins', sans-serif;
        }

        .stat-card .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Different colors for different cards */
        .stat-card.users-card .icon {
            background: linear-gradient(45deg, #3498db, #2980b9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-card.papers-card .icon {
            background: linear-gradient(45deg, #2ecc71, #27ae60);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-card.active-card .icon {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-card.shares-card .icon {
            background: linear-gradient(45deg, #9b59b6, #8e44ad);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        @media (max-width: 768px) {
            .stats-row {
                margin: 0 -5px 20px -5px;
            }
            .stats-row .col-md-4 {
                padding: 0 5px;
                margin-bottom: 10px;
            }
            .stat-card {
                padding: 15px;
            }
            .stat-card .stat-number {
                font-size: 2rem;
            }
        }

        .user-type-container {
            position: relative;
        }
        
        .user-type-main {
            width: 100%;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            cursor: pointer;
            background-color: white;
        }
        
        .user-type-options {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: white;
            border: 1px solid #ced4da;
            border-radius: 4px;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .user-type-item {
            padding: 8px 12px;
            cursor: pointer;
            position: relative;
        }
        
        .user-type-item:hover {
            background-color: #4ea8ed;
        }
        
        .user-type-suboptions {
            display: none;
            position: absolute;
            left: 100%;
            width: 150px;
            background: white;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        /* Position sub-options for each item */
        .user-type-item:nth-child(2) .user-type-suboptions { /* Admin */
            top: 0;
        }
        
        .user-type-item:nth-child(3) .user-type-suboptions { /* Researcher */
            top: -41px; /* Height of one item */
        }
        
        .user-type-item:nth-child(4) .user-type-suboptions { /* Regular User */
            top: -82px; /* Height of two items */
        }
        
        .user-type-item:hover .user-type-suboptions {
            display: block;
        }
        
        .user-type-container:hover .user-type-options {
            display: block;
        }
        
        .suboption-item {
            padding: 8px 12px;
            cursor: pointer;
        }
        
        .suboption-item:hover {
            background-color: #4ea8ed;
        }
    </style>

    <!-- Add Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
        <?php require_once "topnav.php"; ?>
        <?php require_once "sidenav.php"; ?>

        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-6">
                            <h3 class="mb-0">Report Generator</h3>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end">
                                <li class="breadcrumb-item"><a href="#">Home</a></li>
                                <li class="breadcrumb-item active">Report Generator</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="app-content">
                <div class="container-fluid">
                    <!-- Filter Form -->
                    <div class="filter-card">
                        <form method="get" class="row g-4">
                            <div class="col-md-3">
                                <label for="dateRange" class="form-label">
                                    <i class="fas fa-calendar-alt me-2"></i>Date Range
                                </label>
                                <select name="date_range" id="dateRange" class="form-select" onchange="toggleCustomDate(this.value)">
                                    <option value="all" <?php echo $dateRange == 'all' ? 'selected' : ''; ?>>All Time</option>
                                    <option value="7" <?php echo $dateRange == '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                                    <option value="30" <?php echo $dateRange == '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                                    <option value="90" <?php echo $dateRange == '90' ? 'selected' : ''; ?>>Last 90 Days</option>
                                    <option value="custom" <?php echo $dateRange == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 custom-date" style="display: none;">
                                <label for="start_date" class="form-label">
                                    <i class="fas fa-calendar-plus me-2"></i>Start Date
                                </label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $_GET['start_date'] ?? ''; ?>">
                            </div>
                            
                            <div class="col-md-3 custom-date" style="display: none;">
                                <label for="end_date" class="form-label">
                                    <i class="fas fa-calendar-minus me-2"></i>End Date
                                </label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $_GET['end_date'] ?? ''; ?>">
                            </div>

                            <div class="col-md-3">
                                <label for="userType" class="form-label">
                                    <i class="fas fa-users me-2"></i>User Type
                                </label>
                                <input type="hidden" name="user_type" id="userTypeInput" value="<?php echo htmlspecialchars($userType); ?>">
                                <div class="user-type-container">
                                    <div class="user-type-main" id="userTypeMain">
                                        <span id="userTypeDisplay">All Users</span>
                                        <i class="fas fa-chevron-down float-end mt-1"></i>
                                    </div>
                                    <div class="user-type-options">
                                        <div class="user-type-item" data-value="all_all">All Users</div>
                                        <div class="user-type-item" data-value="admin_all">
                                            Administrators
                                            <div class="user-type-suboptions">
                                                <div class="suboption-item" data-type="admin" data-status="all">All</div>
                                                <div class="suboption-item" data-type="admin" data-status="active">Active</div>
                                                <div class="suboption-item" data-type="admin" data-status="inactive">Inactive</div>
                                            </div>
                                        </div>
                                        <div class="user-type-item" data-value="researcher_all">
                                            Researchers
                                            <div class="user-type-suboptions">
                                                <div class="suboption-item" data-type="researcher" data-status="all">All</div>
                                                <div class="suboption-item" data-type="researcher" data-status="active">Active</div>
                                                <div class="suboption-item" data-type="researcher" data-status="inactive">Inactive</div>
                                            </div>
                                        </div>
                                        <div class="user-type-item" data-value="user_all">
                                            Regular Users
                                            <div class="user-type-suboptions">
                                                <div class="suboption-item" data-type="user" data-status="all">All</div>
                                                <div class="suboption-item" data-type="user" data-status="active">Active</div>
                                                <div class="suboption-item" data-type="user" data-status="inactive">Inactive</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label for="paperStatus" class="form-label">
                                    <i class="fas fa-file-alt me-2"></i>Paper Status
                                </label>
                                <select name="paper_status" id="paperStatus" class="form-select">
                                    <option value="all">All Papers</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Pending</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="search" class="form-label">
                                    <i class="fas fa-search me-2"></i>Search
                                </label>
                                <input type="text" name="search" id="search" class="form-control" placeholder="Search users or papers...">
                            </div>

                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-filter me-2"></i>Generate Report
                                </button>
                                <button type="button" class="btn btn-success me-2" onclick="exportToE()">
                                    <i class="fas fa-file-excel me-2"></i>Export to Excel
                                </button>
                                <button type="button" class="btn btn-danger" onclick="exportToP()">
                                    <i class="fas fa-file-pdf me-2"></i>Export to PDF
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="row stats-row">
                        <div class="col-md-3">
                            <div class="stat-card users-card">
                                <i class="fas fa-users icon"></i>
                                <div class="stat-number"><?php echo $userResult->num_rows; ?></div>
                                <div class="stat-label">Total Users</div>
                            </div>  
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card papers-card">
                                <i class="fas fa-file-alt icon"></i>
                                <div class="stat-number"><?php echo $paperResult->num_rows; ?></div>
                                <div class="stat-label">Total Papers</div>
                            </div>  
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card active-card">
                                <i class="fas fa-check-circle icon"></i>
                                <div class="stat-number"><?php echo $activePapers; ?></div>
                                <div class="stat-label">Active Papers</div>
                            </div>  
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card shares-card">
                                <i class="fas fa-share-alt icon"></i>
                                <div class="stat-number"><?php echo $totalShares; ?></div>
                                <div class="stat-label">Total Shares</div>
                            </div>  
                        </div>
                    </div>
                    <!-- User Report Table -->
                    <div class="table-container">
                        <h4><i class="fas fa-users"></i>User Report</h4>
                        <table class="table" id="userTable">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Join Date</th>
                                    <th>Research Interests</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $userResult->data_seek(0);
                                while($row = $userResult->fetch_assoc()) {
                                    $statusClass = $row['is_active'] ? 'badge-active' : 'badge-inactive';
                                    $statusText = $row['is_active'] ? 'Active' : 'Inactive';
                                    
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['Username']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['Email']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['role']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['join_date']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['research_interests']) . "</td>";
                                    echo "<td><span class='badge {$statusClass}'>{$statusText}</span></td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paper Report Table -->
                    <div class="table-container">
                        <h4><i class="fas fa-file-alt"></i>Paper Report</h4>
                        <table class="table" id="paperTable">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Upload Date</th>
                                    <th>Status</th>
                                    <th>Downloads</th>
                                    <th>Shares</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $paperResult->data_seek(0);
                                while($row = $paperResult->fetch_assoc()) {
                                    $statusClass = $row['status'] == 'active' ? 'badge-active' : 'badge-inactive';
                                    
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['title']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['uploaded_by']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['uploaded_at']) . "</td>";
                                    echo "<td><span class='badge {$statusClass}'>" . htmlspecialchars($row['status']) . "</span></td>";
                                    echo "<td>" . (isset($row['downloads']) ? htmlspecialchars($row['downloads']) : '0') . "</td>"; 
                                    
                                    // Get share count for this paper
                                    $shareQuery = "SELECT COUNT(*) as share_count FROM paper_shares WHERE paper_id = " . $row['id'];
                                    $shareResult = $conn->query($shareQuery);
                                    $shareCount = $shareResult->fetch_assoc()['share_count'];
                                    echo "<td>" . $shareCount . "</td>";
                                    
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <table id="combinedExportTable" style="display:none;"></table>

    <!-- Required Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <!-- DataTables Buttons -->
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTables for User Table
            $('#userTable').DataTable({
                "pageLength": 10,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "order": [[3, "desc"]], // Sort by Join Date by default
                dom: '<"row"<"col-sm-4"l><"col-sm-4"B><"col-sm-4"f>>rtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-success',
                        exportOptions: {
                            columns: ':visible'
                        },
                        customize: function(xlsx) {
                            var sheet = xlsx.xl.worksheets['sheet1.xml'];
                            
                            // Get filtered data
                            var table = $('#userTable').DataTable();
                            var filteredData = table.rows({search:'applied'}).data();
                            
                            // Calculate statistics from filtered data
                            var totalUsers = filteredData.length;
                            var activeUsers = 0;
                            var inactiveUsers = 0;
                            var adminUsers = 0;
                            var researcherUsers = 0;
                            var regularUsers = 0;
                            
                            filteredData.each(function(row) {
                                // Status is in column 5
                                if(row[5].includes('Active')) activeUsers++;
                                else inactiveUsers++;
                                
                                // Role is in column 2
                                if(row[2].toLowerCase().includes('admin')) adminUsers++;
                                else if(row[2].toLowerCase().includes('researcher')) researcherUsers++;
                                else regularUsers++;
                            });
                            
                            // Add summary statistics at the top
                            $('row:first', sheet).before(`
                                <row>
                                    <c t="inlineStr"><is><t>Total Users (Filtered): ${totalUsers}</t></is></c>
                                </row>
                                <row>
                                    <c t="inlineStr"><is><t>Active Users: ${activeUsers}</t></is></c>
                                </row>
                                <row>
                                    <c t="inlineStr"><is><t>Inactive Users: ${inactiveUsers}</t></is></c>
                                </row>
                                <row>
                                    <c t="inlineStr"><is><t>Admin Users: ${adminUsers}</t></is></c>
                                </row>
                                <row>
                                    <c t="inlineStr"><is><t>Researcher Users: ${researcherUsers}</t></is></c>
                                </row>
                                <row>
                                    <c t="inlineStr"><is><t>Regular Users: ${regularUsers}</t></is></c>
                                </row>
                                <row>
                                    <c t="inlineStr"><is><t></t></is></c>
                                </row>
                            `);
                        }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn btn-danger',
                        exportOptions: {
                            columns: ':visible'
                        },
                        customize: function(doc) {
                            // Get filtered data
                            var table = $('#userTable').DataTable();
                            var filteredData = table.rows({search:'applied'}).data();
                            
                            // Calculate statistics from filtered data
                            var totalUsers = filteredData.length;
                            var activeUsers = 0;
                            var inactiveUsers = 0;
                            var adminUsers = 0;
                            var researcherUsers = 0;
                            var regularUsers = 0;
                            
                            filteredData.each(function(row) {
                                // Status is in column 5
                                if(row[5].includes('Active')) activeUsers++;
                                else inactiveUsers++;
                                
                                // Role is in column 2
                                if(row[2].toLowerCase().includes('admin')) adminUsers++;
                                else if(row[2].toLowerCase().includes('researcher')) researcherUsers++;
                                else regularUsers++;
                            });
                            
                            // Add summary statistics at the top
                            doc.content.splice(1, 0, {
                                text: [
                                    'Summary Statistics (Filtered Data):\n',
                                    `Total Users: ${totalUsers}\n`,
                                    `Active Users: ${activeUsers}\n`,
                                    `Inactive Users: ${inactiveUsers}\n`,
                                    `Admin Users: ${adminUsers}\n`,
                                    `Researcher Users: ${researcherUsers}\n`,
                                    `Regular Users: ${regularUsers}\n`,
                                ],
                                margin: [0, 0, 0, 15]
                            });
                        }
                    }
                ],
                "language": {
                    "search": "Search Users:",
                    "lengthMenu": "Show _MENU_ entries"
                }
            });

            // Initialize DataTables for Paper Table
            $('#paperTable').DataTable({
                "pageLength": 10,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "order": [[2, "desc"]], // Sort by Upload Date by default
                dom: '<"row"<"col-sm-4"l><"col-sm-4"B><"col-sm-4"f>>rtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-success',
                        exportOptions: {
                            columns: ':visible'
                        },
                        customize: function(xlsx) {
                            var sheet = xlsx.xl.worksheets['sheet1.xml'];
                            
                            // Get filtered data
                            var table = $('#paperTable').DataTable();
                            var filteredData = table.rows({search:'applied'}).data();
                            
                            // Calculate statistics from filtered data
                            var totalPapers = filteredData.length;
                            var activePapers = 0;
                            var inactivePapers = 0;
                            var totalShares = 0;
                            
                            filteredData.each(function(row) {
                                // Status is in column 3 (0-based index)
                                var tempDiv = document.createElement('div');
                                tempDiv.innerHTML = row[3];
                                var status = tempDiv.textContent.trim().toLowerCase();
                                
                                if(status === 'active') {
                                    activePapers++;
                                } else if(status === 'inactive') {
                                    inactivePapers++;
                                }
                                
                                // Shares are in column 5
                                totalShares += parseInt(row[5]) || 0;
                            });
                            
                            var averageShares = totalPapers > 0 ? (totalShares / totalPapers).toFixed(2) : 0;
                            
                            // Add summary statistics at the top
                            $('row:first', sheet).before(`
                                <row>
                                    <c t="inlineStr"><is><t>Total Papers (Filtered): ${totalPapers}</t></is></c>
                                </row>
                                <row>
                                    <c t="inlineStr"><is><t>Active Papers: ${activePapers}</t></is></c>
                                </row>
                                <row>
                                    <c t="inlineStr"><is><t>Inactive Papers: ${inactivePapers}</t></is></c>
                                </row>
                                <row>
                                    <c t="inlineStr"><is><t>Total Shares: ${totalShares}</t></is></c>
                                </row>
                                <row>
                                    <c t="inlineStr"><is><t>Average Shares per Paper: ${averageShares}</t></is></c>
                                </row>
                                <row>
                                    <c t="inlineStr"><is><t></t></is></c>
                                </row>
                            `);
                        }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn btn-danger',
                        exportOptions: {
                            columns: ':visible'
                        },
                        customize: function(doc) {
                            // Get filtered data
                            var table = $('#paperTable').DataTable();
                            var filteredData = table.rows({search:'applied'}).data();
                            
                            // Calculate statistics from filtered data
                            var totalPapers = filteredData.length;
                            var activePapers = 0;
                            var inactivePapers = 0;
                            var totalShares = 0;
                            
                            filteredData.each(function(row) {
                                // Status is in column 3 (0-based index)
                                var tempDiv = document.createElement('div');
                                tempDiv.innerHTML = row[3];
                                var status = tempDiv.textContent.trim().toLowerCase();
                                
                                if(status === 'active') {
                                    activePapers++;
                                } else if(status === 'inactive') {
                                    inactivePapers++;
                                }
                                
                                // Shares are in column 5
                                totalShares += parseInt(row[5]) || 0;
                            });
                            
                            var averageShares = totalPapers > 0 ? (totalShares / totalPapers).toFixed(2) : 0;
                            
                            // Add summary statistics at the top
                            doc.content.splice(1, 0, {
                                text: [
                                    'Summary Statistics (Filtered Data):\n',
                                    `Total Papers: ${totalPapers}\n`,
                                    `Active Papers: ${activePapers}\n`,
                                    `Inactive Papers: ${inactivePapers}\n`,
                                    `Total Shares: ${totalShares}\n`,
                                    `Average Shares per Paper: ${averageShares}\n`,
                                ],
                                margin: [0, 0, 0, 15]
                            });
                        }
                    }
                ],
                "language": {
                    "search": "Search Papers:",
                    "lengthMenu": "Show _MENU_ entries"
                }
            });
        });

        // Export functions
        function exportToExcel() {
            var activeTab = $('.nav-link.active').attr('href');
            var tableId = activeTab === '#users' ? 'userTable' : 'paperTable';
            var table = $('#' + tableId).DataTable();
            table.button('.buttons-excel').trigger();
        }

        function exportToPDF() {
            var activeTab = $('.nav-link.active').attr('href');
            var tableId = activeTab === '#users' ? 'userTable' : 'paperTable';
            var table = $('#' + tableId).DataTable();
            table.button('.buttons-pdf').trigger();
        }

        // User Type Selection Handling
        $('.user-type-item').click(function(e) {
            if (!$(e.target).hasClass('suboption-item')) {
                const value = $(this).data('value');
                if (value === 'all_all') {
                    updateUserTypeSelection('all', 'all');
                }
            }
        });

        $('.suboption-item').click(function(e) {
            e.stopPropagation();
            const type = $(this).data('type');
            const status = $(this).data('status');
            updateUserTypeSelection(type, status);
        });

        function updateUserTypeSelection(type, status) {
            const displayText = type === 'all' ? 'All Users' : 
                `${type.charAt(0).toUpperCase() + type.slice(1)}s - ${status.charAt(0).toUpperCase() + status.slice(1)}`;
            $('#userTypeDisplay').text(displayText);
            $('#userTypeInput').val(`${type}_${status}`);

            // Filter the table
            var table = $('#userTable').DataTable();
            
            // Reset filters
            table.columns(2).search(''); // Role column
            table.columns(5).search(''); // Status column
            
            // Apply new filters
            if (type !== 'all') {
                table.columns(2).search(type);
            }
            
            if (status !== 'all') {
                const statusText = status === 'active' ? 'Active' : 'Inactive';
                table.columns(5).search(statusText);
            }
            
            table.draw();

            // Hide options after selection
            $('.user-type-options').hide();
        }

        // Hide options when clicking outside
        $(document).click(function(e) {
            if (!$(e.target).closest('.user-type-container').length) {
                $('.user-type-options').hide();
            }
        });

        // Show options when clicking the main container
        $('#userTypeMain').click(function(e) {
            e.stopPropagation();
            $('.user-type-options').toggle();
        });

        function toggleCustomDate(value) {
            const customDateFields = document.querySelectorAll('.custom-date');
            customDateFields.forEach(field => {
                field.style.display = value === 'custom' ? 'block' : 'none';
            });
        }

        // Initialize custom date fields visibility
        document.addEventListener('DOMContentLoaded', function() {
            toggleCustomDate(document.getElementById('dateRange').value);
        });
        function exportToE() {
    // Get both tables
    var userTable = $('#userTable').DataTable();
    var paperTable = $('#paperTable').DataTable();
    
    // Get data from both tables
    var userTableData = userTable.data().toArray();
    var paperTableData = paperTable.data().toArray();
    
    // Create a new DataTable with combined data
    var combinedTable = $('#combinedExportTable').DataTable({
        data: userTableData,
        columns: userTable.settings()[0].aoColumns.map(function(col) { 
            return { title: col.sTitle }; 
        }),
        dom: 'Bfrtip',
        buttons: [{
            extend: 'excel',
            text: 'Export to Excel',
            title: 'Research Report',
            sheetName: 'Users',
            exportOptions: {
                columns: ':visible'
            }
        }]
    });
    
    // Add a second sheet for Papers
    combinedTable.button().remove();
    combinedTable.button([{
        extend: 'excel',
        text: 'Export to Excel',
        title: 'Research Report',
        sheetName: 'Papers',
        exportOptions: {
            columns: ':visible'
        },
        customize: function(xlsx) {
            var sheet = xlsx.xl.worksheets['sheet1.xml'];
            var userSheet = sheet;
            
            // Add Papers sheet
            var paperSheet = $.parseXML(userSheet);
            var $paperSheet = $(paperSheet);
            
            // Modify sheet data with Paper table data
            $paperSheet.find('sheetData').empty();
            $paperSheet.find('sheetData').append(
                paperTableData.map(function(row) {
                    return '<row>' + 
                        row.map(function(cell) {
                            return '<c t="inlineStr"><is><t>' + cell + '</t></is></c>';
                        }).join('') + 
                    '</row>';
                }).join('')
            );
            
            // Update sheet name
            $paperSheet.find('sheetName').attr('val', 'Papers');
            
            // Add the new sheet to the workbook
            xlsx.xl.worksheets['sheet2.xml'] = $paperSheet[0];
            xlsx.xl['workbook.xml'].getElementsByTagName('sheets')[0].innerHTML += 
                '<sheet name="Papers" sheetId="2" r:id="rId2"/>';
        }
    }]);
    
    // Trigger export
    combinedTable.button('.buttons-excel').trigger();
    
    // Destroy the temporary combined table
    combinedTable.destroy();
}

function exportToP() {
    // Get both tables
    var userTable = $('#userTable').DataTable();
    var paperTable = $('#paperTable').DataTable();
    
    // Get data from both tables
    var userTableData = userTable.data().toArray();
    var paperTableData = paperTable.data().toArray();
    
    // Create a new DataTable with combined data
    var combinedTable = $('#combinedExportTable').DataTable({
        data: userTableData,
        columns: userTable.settings()[0].aoColumns.map(function(col) { 
            return { title: col.sTitle }; 
        }),
        dom: 'Bfrtip',
        buttons: [{
            extend: 'pdf',
            text: 'Export to PDF',
            title: 'Research Report - Users',
            exportOptions: {
                columns: ':visible'
            }
        }]
    });
    
    // Add a second sheet for Papers
    combinedTable.button().remove();
    combinedTable.button([{
        extend: 'pdf',
        text: 'Export to PDF',
        title: 'Research Report - Papers',
        exportOptions: {
            columns: ':visible'
        }
    }]);
    
    // Trigger export
    combinedTable.button('.buttons-pdf').trigger();
    
    // Destroy the temporary combined table
    combinedTable.destroy();
}
    </script>
    
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
