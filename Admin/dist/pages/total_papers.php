<?php
session_start();
include 'config.php';

// Check if user is not logged in
if (!isset($_SESSION['username']) && !isset($_SESSION['user_email'])) {
    header("Location: login.php"); // Redirect to login page
    exit();
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
} else {
    echo "";
}
$role = $_SESSION['role'];



// Add this new code to get total papers count
$sql_papers = "SELECT COUNT(*) as total_papers FROM research_uploads";
$result_papers = $conn->query($sql_papers);
$total_papers = 0;
if ($result_papers) {
    $row_papers = $result_papers->fetch_assoc();
    $total_papers = $row_papers['total_papers'];
}

// Handle AJAX requests
if (isset($_GET['action']) && $_GET['action'] === 'get_existing_shares') {
    header('Content-Type: application/json');
    
    $paper_id = intval($_GET['paper_id']);
    
    $share_query = "SELECT shared_with as username FROM paper_shares WHERE paper_id = ?";
    
    $stmt = $conn->prepare($share_query);
    $stmt->bind_param("i", $paper_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $shares = [];
    while ($row = $result->fetch_assoc()) {
        $shares[] = [
            'username' => $row['username']
        ];
    }
    
    echo json_encode($shares);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (isset($data['action']) && $data['action'] === 'update_shares') {
        header('Content-Type: application/json');
        
        $paper_id = intval($data['paper_id']);
        $researchers = $data['researchers'];
        
        try {
            $conn->begin_transaction();
            
            // Remove existing shares
            $delete_query = "DELETE FROM paper_shares WHERE paper_id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $paper_id);
            $stmt->execute();
            
            // Add new shares
            if (!empty($researchers)) {
                $insert_query = "INSERT INTO paper_shares (paper_id, shared_with) 
                               VALUES " . str_repeat("(?, ?), ", count($researchers) - 1) . "(?, ?)";
                
                $params = [];
                $types = "";
                foreach ($researchers as $researcher) {
                    $params[] = $paper_id;
                    $params[] = $researcher;
                    $types .= "is"; // i for paper_id, s for shared_with
                }
                
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
            }
            
            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Fix the query execution for different roles
if ($role === 'user') {
    // Simplified query for users
    $query = "SELECT id, title, description, file_name, uploaded_by, file_path FROM research_uploads WHERE status = 'active' ORDER BY uploaded_at DESC";
    $result = $conn->query($query);  // Execute the query for users
} elseif ($role === 'researcher') {
    // Updated query for researchers - show their own papers, active papers, and shared papers
    $query = "SELECT DISTINCT ru.* 
              FROM research_uploads ru 
              LEFT JOIN paper_shares ps ON ru.id = ps.paper_id 
              WHERE ru.uploaded_by = ? 
              OR ru.status = 'active' 
              OR ps.shared_with = ?
              ORDER BY ru.uploaded_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $user_name, $user_name);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Query for admin with optional status filter
    if (isset($_GET['status']) && $_GET['status'] === 'inactive') {
        $query = "SELECT * FROM research_uploads WHERE status = 'inactive' ORDER BY uploaded_at DESC";
    } else {
        $query = "SELECT * FROM research_uploads ORDER BY uploaded_at DESC";
    }
    $result = $conn->query($query);
}

// Add this code to show/hide edit buttons based on share access
$showEditButtons = function($row) use ($role, $user_name) {
    // Check if user is admin, owner, or has shared access
    $hasAccess = $role === 'admin' || 
                 $row['uploaded_by'] === $user_name || 
                 isSharedWithUser($row['id'], $user_name);
    return $hasAccess;
};

// Add this helper function to check for shared access
function isSharedWithUser($paperId, $username) {
    global $conn;
    $query = "SELECT 1 FROM paper_shares WHERE paper_id = ? AND shared_with = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $paperId, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
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
      integrity="sha256-+uGLJmmTKOqBr+2E6KDYs/NRsHxSkONXFHUL0fy2O/4="
      crossorigin="anonymous"
    />
    <!-- jsvectormap -->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/css/jsvectormap.min.css"
      integrity="sha256-+uGLJmmTKOqBr+2E6KDYs/NRsHxSkONXFHUL0fy2O/4="
      crossorigin="anonymous"
    />
     <!-- Add DataTables CSS -->
     <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    <!-- Add Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Add these in your head section if not already present -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- Add these in your head section -->
    <link href="path/to/jquery.dropdown.css" rel="stylesheet" />
    <script src="path/to/jquery.dropdown.js"></script>
    
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            margin: 0;
            padding: 0;
            color: #1a202c;
            min-height: 100vh;
        }

       
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header h2 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #2d3748;
            margin: 0;
        }

        .upload-btn {
            background: linear-gradient(45deg, #4f46e5, #6366f1);
            color: white;
            padding: 12px 28px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
        }

        .upload-btn:hover {
            background: linear-gradient(45deg, #4338ca, #4f46e5);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.2);
        }

        /* DataTable Styling */
        .dataTables_wrapper {
            margin-top: 2rem;
            background: #ffffff;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            
        }

        table.dataTable {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }

        table.dataTable thead th {
            background: #2c3e50;
            padding: 16px 12px;
            font-weight: 600;
            color: #ffffff;
            border-bottom: 2px solid #34495e;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        table.dataTable tbody td {
            padding: 16px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
        }

        /* Action Buttons */
        .icon-btn {
            padding: 8px;
            border-radius: 8px;
            transition: all 0.2s ease;
            margin: 0 4px;
        }

        .icon-btn:hover {
            background-color: #f1f5f9;
        }

        .fa-eye { color: #059669; }
        .fa-edit { color: #3b82f6; }
        .fa-trash-alt { color: #ef4444; }
        .fa-download { color: #8b5cf6; }
        .fa-user-plus { color: #10b981; }

        .icon-btn:hover i {
            transform: scale(1.15);
        }

        /* Status Button */
        .status-btn {
            padding: 8px 16px;
            border-radius: 24px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .status-btn.active {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-btn.inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-btn:hover {
            filter: brightness(0.95);
        }

        /* Bulk Actions Section */
        .bulk-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 12px;
            flex-wrap: wrap;
        }
        .bulk-action-container {
            display: none;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }

        .bulk-select {
            padding: 10px 20px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background-color: white;
            min-width: 220px;
            height: 44px;
            font-size: 0.95rem;
            color: #475569;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .bulk-select:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .apply-btn {
            background: linear-gradient(45deg, #4f46e5, #6366f1);
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            height: 44px;
            font-weight: 500;
            min-width: 120px;
        }

        .apply-btn:hover {
            background: linear-gradient(45deg, #4338ca, #4f46e5);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 90%;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: relative;
        }

        /* Close Button */
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover,
        .close:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }

        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 1rem;
            line-height: 1.5;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group input[type="file"] {
            display: block;
            margin-top: 0.5rem;
        }

        /* Edit Modal Specific Styles */
        #editModal .form-group {
            margin-bottom: 1.5rem;
        }

        #editModal .text-muted {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .button-container {
    display: flex;
    justify-content: flex-end; /* Aligns button to the right */
    margin-top: 15px;
}

.submit-btn {
    background: #4f46e5;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    transition: background 0.3s ease;
}

.submit-btn:hover {
    background: #3730a3;
}

        /* Share Modal Specific Styles */
        #researchers {
            width: 100%;
            min-height: 200px;
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background-color: #fff;
        }

        #researchers option {
            padding: 8px;
            margin: 2px 0;
            border-radius: 4px;
        }

        #researchers option:checked {
            background-color: #4f46e5;
            color: white;
        }

        /* Responsive Adjustments */
        @media (max-width: 640px) {
            .modal-content {
                margin: 10% auto;
                width: 95%;
                padding: 15px;
            }

            .form-group input[type="text"],
            .form-group textarea {
                font-size: 16px; /* Prevents zoom on mobile */
            }
        }

        /* Animation */
        @keyframes modalFadeIn {
            from {opacity: 0}
            to {opacity: 1}
        }

        .modal.show {
            animation: modalFadeIn 0.3s;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }

            .header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
                gap: 1.5rem;
            }

            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter {
                width: 100%;
                margin-bottom: 1rem;
            }

            .dataTables_wrapper .dataTables_filter {
                text-align: left;
            }

            .dataTables_wrapper .dataTables_filter input {
                width: calc(100% - 70px); /* Accounting for "Search:" label */
                margin-left: 8px;
                margin-top: 8px;
            }

            .dataTables_wrapper .dataTables_length select {
                width: 100px;
                margin: 8px 8px;
            }

            /* Adjust label display */
            .dataTables_wrapper .dataTables_length label,
            .dataTables_wrapper .dataTables_filter label {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 8px;
            }
        }

        /* Add these responsive table styles */
        @media screen and (max-width: 768px) {
            .dataTables_wrapper {
                padding: 0.5rem;
            }

            table.dataTable {
                display: block;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            table.dataTable thead th {
                white-space: nowrap;
                min-width: 120px;
            }

            table.dataTable tbody td {
                white-space: nowrap;
                min-width: 120px;
            }

            /* Adjust specific column widths */
            table.dataTable th:nth-child(1),
            table.dataTable td:nth-child(1) {
                min-width: 50px;  /* checkbox column */
            }

            table.dataTable th:nth-child(8),
            table.dataTable td:nth-child(8) {
                min-width: 150px;  /* actions column */
            }

            /* Stack the DataTables controls */
            .dataTables_length,
            .dataTables_filter {
                float: none !important;
                text-align: left !important;
                margin-bottom: 1rem;
            }

            /* Improve text handling in cells */
            table.dataTable tbody td {
                max-width: 200px; /* Limit maximum width */
                overflow: hidden;
                text-overflow: ellipsis; /* Show ... for overflow text */
                white-space: normal; /* Allow text to wrap */
                word-wrap: break-word;
                min-height: 50px; /* Ensure minimum height for wrapped content */
                vertical-align: middle;
            }

            /* Specific column handling */
            table.dataTable td:nth-child(2), /* Title */
            table.dataTable td:nth-child(3) { /* Description */
                max-width: 100px;
                white-space: normal;
                font-size: 0.9rem;
            }

            table.dataTable td:nth-child(4) { /* File Name */
                max-width: 120px;
                white-space: normal;
            }

            /* Add tooltip for truncated text */
            table.dataTable td {
                position: relative;
            }

            table.dataTable td:hover::after {
                content: attr(data-full-text);
                position: absolute;
                left: 0;
                top: 100%;
                z-index: 1000;
                background: #fff;
                padding: 5px;
                border: 1px solid #ddd;
                border-radius: 4px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                white-space: normal;
                max-width: 300px;
                word-wrap: break-word;
            }

            /* Update column widths to be more equal */
            table.dataTable thead th,
            table.dataTable tbody td {
                min-width: 100px;  /* Base width for all columns */
                max-width: 100px;
                padding: 8px;
                font-size: 0.9rem;
            }

            /* Specific adjustments for certain columns */
            table.dataTable th:nth-child(1),
            table.dataTable td:nth-child(1) {
                min-width: 30px;  /* checkbox column */
                max-width: 30px;
            }

            table.dataTable th:nth-child(7),
            table.dataTable td:nth-child(7) {
                min-width: 80px;  /* status column */
                max-width: 80px;
            }

            table.dataTable th:nth-child(8),
            table.dataTable td:nth-child(8) {
                min-width: 120px;  /* actions column */
                max-width: 120px;
            }

            /* Ensure text handling is consistent */
            table.dataTable tbody td {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: normal;
                word-wrap: break-word;
                vertical-align: middle;
            }

            /* Make status button more compact */
            .status-btn {
                padding: 4px 8px;
                font-size: 0.85rem;
            }

            /* Make action buttons more compact */
            .icon-btn {
                padding: 4px;
                margin: 0 2px;
            }

            /* Update tooltip/popup styling */
            .text-popup {
                position: fixed;
                left: 50%;
                top: 50%;
                transform: translate(-50%, -50%);
                background: white;
                padding: 15px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                z-index: 1000;
                max-width: 90%;
                width: 300px;
                word-wrap: break-word;
                display: none;
            }

            .text-popup-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
                display: none;
            }
        }

        /* Add these new styles */
        .cell-content {
            position: relative;
        }

        .truncated-text {
            cursor: pointer;
        }

        .text-popup {
            position: absolute;
            left: 0;
            top: 100%;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1000;
            min-width: 200px;
            max-width: 300px;
            word-wrap: break-word;
            display: none;
        }

        /* Remove the old popup styles */
        .text-popup-backdrop {
            display: none;
        }

        /* DataTables search and length styling */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 1.5rem;
        }

        .dataTables_wrapper .dataTables_length select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background-color: white;
            min-width: 80px;
            cursor: pointer;
        }

        .dataTables_wrapper .dataTables_filter input {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background-color: white;
            min-width: 200px;
            margin-left: 8px;
        }

        .dataTables_wrapper .dataTables_filter input:focus,
        .dataTables_wrapper .dataTables_length select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
                gap: 1.5rem;
            }

            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter {
                width: 100%;
                margin-bottom: 1rem;
            }

            .dataTables_wrapper .dataTables_filter {
                text-align: left;
            }

            .dataTables_wrapper .dataTables_filter input {
                width: calc(100% - 70px); /* Accounting for "Search:" label */
                margin-left: 8px;
                margin-top: 8px;
            }

            .dataTables_wrapper .dataTables_length select {
                width: 100px;
                margin: 8px 8px;
            }

            /* Adjust label display */
            .dataTables_wrapper .dataTables_length label,
            .dataTables_wrapper .dataTables_filter label {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 8px;
            }
        }

        /* DataTables info and pagination styling */
        .dataTables_info {
            color: #64748b;
            padding-top: 1rem;
        }

        .dataTables_paginate {
            padding-top: 1rem;
        }

        .dataTables_paginate .paginate_button {
            padding: 8px 12px;
            margin: 0 4px;
            border-radius: 6px;
            cursor: pointer;
            color: #475569 !important;
            border: 1px solid #e2e8f0;
            background: white !important;
        }

        .dataTables_paginate .paginate_button.current {
            background: #4f46e5 !important;
            color: white !important;
            border-color: #4f46e5;
        }

        .dataTables_paginate .paginate_button:hover {
            background: #f1f5f9 !important;
            color: #1e293b !important;
        }

        /* Add media query for collapsed sidenav */
        @media (max-width: 992px) {
            .container {
                margin-left: 20px; /* Reset margin when sidenav is collapsed */
                max-width: calc(100% - 40px); /* Adjust max-width when sidenav is collapsed */
            }
        }

        /* Add sidenav positioning */
        .main-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 250px;
            z-index: 1000;
        }

        /* Add topnav positioning */
        .main-header {
            position: fixed;
            top: 0;
            right: 0;
            left: 250px; /* Match sidenav width */
            height: 60px;
            z-index: 999;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .container {
                margin-left: 20px;
                max-width: calc(100% - 40px);
            }

            .main-header {
                left: 0;
            }
        }

        .action-buttons {
            display: inline-flex;
            gap: 8px;
            justify-content: flex-start;
            align-items: center;
            flex-wrap: wrap; /* Allow buttons to wrap if necessary */
        }

        .icon-btn {
            padding: 6px;
            border-radius: 6px;
            transition: all 0.2s ease;
            background: transparent;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon-btn:hover {
            background-color: #f1f5f9;
            transform: translateY(-1px);
        }

        .fa-download { 
            color: #8b5cf6; /* Purple color for download icon */
        }

        /* Additional styles for multiple selection and existing shares */
        .existing-shares {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }

        .existing-shares h3 {
            margin-top: 0;
            font-size: 16px;
            color: #333;
        }

        #existingSharesList {
            max-height: 150px;
            overflow-y: auto;
        }

        .share-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        .share-item:last-child {
            border-bottom: none;
        }

        .share-item .remove-share {
            color: #dc3545;
            cursor: pointer;
        }

        select[multiple] {
            height: 150px;
        }

        .form-text {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }

        .share-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: #4f46e5;
            color: white;
            padding: 6px 12px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 6px;
            font-size: 0.875rem;
            color: #4b5563;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .share-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        .share-btn i {
            font-size: 0.9rem;
        }

        /* Adjust the action buttons container */
        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: nowrap;
        }

        /* Make sure icons stay white in the share button */
        .share-btn i {
            color: green !important;
        }

        /* Select2 Custom Styles */
        .select2-container--classic .select2-selection--multiple {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 4px;
            min-height: 100px;
            background: #fff;
        }

        .select2-container--classic .select2-selection--multiple:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }

        .select2-container--classic .select2-selection--multiple .select2-selection__choice {
            background-color: #4f46e5;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 4px 8px;
            margin: 4px;
        }

        .select2-container--classic .select2-selection__choice__remove {
            color: white !important;
            margin-right: 5px;
            border: none;
            background: transparent;
        }

        .select2-container--classic .select2-search--inline .select2-search__field {
            margin-top: 0;
            padding: 8px;
            width: 100% !important;
        }

        .select2-container--classic .select2-results__option {
            padding: 8px;
        }

        .select2-container--classic .select2-results__option--highlighted[aria-selected] {
            background-color: #4f46e5;
            color: white;
        }

        .select2-container--classic .select2-dropdown {
            border-color: #d1d5db;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .select2-container--classic .select2-results__option[aria-selected=true] {
            background-color: #e5e7eb;
        }

        .researcher-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            margin-top: 5px;
            background: #f8f9fa;
        }

        .researcher-item {
            padding: 5px 0;
        }

        .researcher-item label {
            display: flex;
            align-items: center;
            margin: 0;
            cursor: pointer;
        }

        .researcher-item input[type="checkbox"] {
            margin-right: 10px;
        }

        .researcher-item:hover {
            background-color: #e9ecef;
        }

        .select2-container {
            width: 100% !important;
        }

        .mt-3 {
            margin-top: 1rem;
        }

        .mt-4 {
            margin-top: 1.5rem;
        }

        /* Add these new styles */
        .researcher-option {
            padding: 4px 0;
        }

        .shared-badge {
            background-color: #10b981;
            color: white;
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 12px;
            margin-left: 8px;
            display: inline-block;
        }

        .shared-badge-mini {
            background-color: #10b981;
            color: white;
            font-size: 0.65rem;
            padding: 1px 4px;
            border-radius: 8px;
            margin-left: 4px;
            display: inline-block;
        }

        .select2-container--classic .select2-results__option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
        }

        .select2-container--classic .select2-results__option--highlighted[aria-selected] .shared-badge,
        .select2-container--classic .select2-results__option--highlighted[aria-selected] .shared-badge-mini {
            background-color: white;
            color: #4f46e5;
        }

        .researcher-selection {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Update existing Select2 styles */
        .select2-container--classic .select2-selection--multiple .select2-selection__choice {
            background: linear-gradient(45deg, #4f46e5, #6366f1);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 4px 8px;
            margin: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .select2-container--classic .select2-selection__choice__remove {
            color: white !important;
            margin-right: 5px;
            border: none;
            background: transparent;
            font-size: 1.1em;
            padding: 0 4px;
            border-radius: 3px;
        }

        .select2-container--classic .select2-selection__choice__remove:hover {
            background-color: rgba(255, 255, 255, 0.2);
            color: white !important;
        }

        /* Add these new styles */
        .bulk-actions-container {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .bulk-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 6px;
            font-size: 0.875rem;
            color: #4b5563;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .bulk-action-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        .bulk-action-btn i {
            font-size: 0.875rem;
        }

        .bulk-action-btn:active {
            transform: translateY(0);
        }

        /* Update modal content max-height for better mobile experience */
        .modal-content {
            max-height: 90vh;
            overflow-y: auto;
        }

        @media (max-width: 640px) {
            .bulk-actions-container {
                flex-direction: column;
            }
            
            .bulk-action-btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Add styles for the share access button */
        .icon-btn.share-btn {
            background: linear-gradient(45deg, #059669, #10b981);  
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .icon-btn.share-btn i {
            color: white;
        }

        .icon-btn.share-btn:hover {
            background: white;  
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }
        
        /* Bulk action container styles */
        .bulk-action-container {
            display: none;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
            padding: 10px;
        }
        
        .bulk-actions {
            margin: 15px 0;
        }
        
        .bulk-select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background-color: white;
            min-width: 150px;
        }

        /* Table responsive styles */
        #researchTable {
            width: 100% !important;
            border-collapse: collapse;
        }

        #researchTable_wrapper {
            width: 100% !important;
        }

        #researchTable_wrapper .dataTables_scroll {
            width: 100% !important;
        }

        #researchTable_wrapper .dataTables_scrollHead {
            width: 100% !important;
            overflow: hidden;
        }

        #researchTable_wrapper .dataTables_scrollBody {
            width: 100% !important;
        }

        #researchTable thead th {
            position: relative;
            padding: 8px;
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }

        #researchTable td {
            max-width: 200px;
            position: relative;
            padding: 8px;
            vertical-align: top;
        }

        #researchTable td .cell-content {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }

        #researchTable td:hover .cell-content {
            position: absolute;
            background: white;
            z-index: 1000;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            min-width: 200px;
            max-width: 300px;
            white-space: normal;
            word-wrap: break-word;
            word-break: break-word;
            hyphens: auto;
            line-height: 1.4;
        }

        @media screen and (max-width: 768px) {
            #researchTable,
            #researchTable_wrapper,
            #researchTable_wrapper .dataTables_scroll,
            #researchTable_wrapper .dataTables_scrollHead,
            #researchTable_wrapper .dataTables_scrollBody {
                width: 100% !important;
                max-width: 100% !important;
            }

            #researchTable {
                display: block;
                overflow-x: auto;
            }

            #researchTable td:hover .cell-content {
                left: 0;
                min-width: 200px;
                max-width: 250px;
            }
        }

  
            .border-left-primary {
                border-left: 4px solid #4e73df !important;
            }
            .border-left-success {
                border-left: 4px solid #1cc88a !important;
            }
            .border-left-warning {
                border-left: 4px solid #f6c23e !important;
            }
            .border-left-info {
                border-left: 4px solid #36b9cc !important;
            }
            .card {
                position: relative;
                display: flex;
                flex-direction: column;
                min-width: 0;
                word-wrap: break-word;
                background-color: #fff;
                background-clip: border-box;
                border: 1px solid #e3e6f0;
                border-radius: 0.35rem;
            }
            .card-body {
                flex: 1 1 auto;
                min-height: 1px;
                padding: 1.25rem;
            }
            .text-xs {
                font-size: .7rem;
            }
            .text-gray-300 {
                color: #dddfeb !important;
            }
            .text-gray-800 {
                color: #5a5c69 !important;
            }

            .three-dot-menu {
        position: relative;
        cursor: pointer;
        padding: 5px;
        display: inline-block;
        border-radius: 4px;
        transition: background-color 0.2s ease;
    }

    .three-dot-menu:hover {
        background-color: rgba(0,0,0,0.1);
    }

    .dropdown-actions {
        display: none;
        position: absolute;
        right: 0;
        top: 100%;
        background-color: white;
        border: 1px solid #ddd;
        border-radius: 6px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        z-index: 1000;
        min-width: 180px;
        padding: 8px 0;
        overflow: hidden;
    }

    .dropdown-actions .icon-btn {
        display: flex;
        align-items: center;
        width: 100%;
        padding: 10px 16px;
        background: none;
        border: none;
        text-align: left;
        transition: background-color 0.2s ease;
        color: inherit;
    }

    .dropdown-actions .icon-btn:hover {
        background-color: #9bfabe;
    }

    .dropdown-actions .icon-btn i {
        margin-right: 10px;
        transition: color 0.2s ease;
    }

    /* Preserve original specific button styles */
    .dropdown-actions .group-chat-btn.has-new-message i {
        color: #dc3545; /* Bootstrap danger color */
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .dropdown-actions {
            min-width: 160px;
        }
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
          
          </div>
          <!--end::Container-->
        </div>
        <!--end::App Content Header-->
       <!-- Begin::App Content -->
<div class="container">
    <?php if ($role !== 'user'): ?>
        <!-- Admin-specific header and bulk actions -->
        <div class="header">
            <h2><?php echo isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'Papers Pending Review' : 'Research Papers Management'; ?></h2>
            <a href="upload_paper.php" class="upload-btn">Upload New Research</a>
        </div>
    <?php endif; ?>
    <?php if ($role === 'admin'): ?>
        <div class="bulk-action-container">
                <select class="bulk-select" id="bulkAction">
                    <option value="">Select Action</option>
                    <option value="delete">Delete Selected</option>
                    <option value="activate">Activate Selected</option>
                    <option value="deactivate">Deactivate Selected</option>
                </select>
                <button class="apply-btn" onclick="applyBulkAction()">Apply</button>
        </div>
    <?php endif; ?>
    <?php if ($role === 'user'): ?>
        <!-- User-specific header -->
        <div class="header">
            <h2>Available Research Papers</h2>
        </div>
    <?php endif; ?>

    
</div>
<!-- End::App Content -->

        <?php
        // Database connection
        $host = "localhost";
        $username = "root";
        $password = "";
        $database = "research";

        $conn = new mysqli($host, $username, $password, $database);

        if ($conn->connect_error) {
            die("<div class='error-message'>Database connection failed: " . $conn->connect_error . "</div>");
        }

        // Handle Delete Request
        if (isset($_POST['delete_id'])) {
            $id = $_POST['delete_id'];
            $stmt = $conn->prepare("SELECT file_path FROM research_uploads WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $file = $result->fetch_assoc();
            
            if ($file && file_exists($file['file_path'])) {
                unlink($file['file_path']); // Delete the file
            }

            $stmt = $conn->prepare("DELETE FROM research_uploads WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                echo "<div class='success'>Record deleted successfully!</div>";
            }
        }

        // Fetch Records
        if ($role === 'user') {
            // Simplified query for users
            $query = "SELECT id, title, description, file_name, uploaded_by, file_path FROM research_uploads WHERE status = 'active' ORDER BY uploaded_at DESC";
            $result = $conn->query($query);  // Execute the query for users
        } elseif ($role === 'researcher') {
            // Updated query for researchers - show their own papers, active papers, and shared papers
            $query = "SELECT DISTINCT ru.* 
                      FROM research_uploads ru 
                      LEFT JOIN paper_shares ps ON ru.id = ps.paper_id 
                      WHERE ru.uploaded_by = ? 
                      OR ru.status = 'active' 
                      OR ps.shared_with = ?
                      ORDER BY ru.uploaded_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $user_name, $user_name);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            // Query for admin with optional status filter
            if (isset($_GET['status']) && $_GET['status'] === 'inactive') {
                $query = "SELECT * FROM research_uploads WHERE status = 'inactive' ORDER BY uploaded_at DESC";
            } else {
                $query = "SELECT * FROM research_uploads ORDER BY uploaded_at DESC";
            }
            $result = $conn->query($query);
        }
        ?>
        <?php
        // Get total papers
        $total_papers_query = "SELECT COUNT(*) as total FROM research_uploads";
        $total_papers_result = mysqli_query($conn, $total_papers_query);
        $total_papers = mysqli_fetch_assoc($total_papers_result)['total'];

        // Get active papers
        $active_papers_query = "SELECT COUNT(*) as active FROM research_uploads WHERE status = 'active'";
        $active_papers_result = mysqli_query($conn, $active_papers_query);
        $active_papers = mysqli_fetch_assoc($active_papers_result)['active'];

        // Get inactive papers
        $inactive_papers_query = "SELECT COUNT(*) as inactive FROM research_uploads WHERE status = 'inactive'";
        $inactive_papers_result = mysqli_query($conn, $inactive_papers_query);
        $inactive_papers = mysqli_fetch_assoc($inactive_papers_result)['inactive'];

        // Get new papers (last 7 days)
        $new_papers_query = "SELECT COUNT(*) as new_papers FROM research_uploads WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $new_papers_result = mysqli_query($conn, $new_papers_query);
        $new_papers = mysqli_fetch_assoc($new_papers_result)['new_papers'];
        ?>
        <?php if ($role === 'admin'): ?>
        <!-- Statistics Boxes -->
        <div class="container-fluid mt-4 mb-4">
            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Papers</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_papers; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Active Papers</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_papers; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Inactive Papers</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $inactive_papers; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        New Papers (Last 7 Days)</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $new_papers; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-plus-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
  
        <?php if ($role === 'admin'): ?>
        <table id="researchTable" class="display">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>File Name</th>
                    <th>Uploaded By</th>
                    <th>Upload Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><input type="checkbox" class="row-checkbox" value="<?= $row['id'] ?>"></td>
                    <td><div class="cell-content"><?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?></div></td>
                    <td><div class="cell-content"><?= htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8') ?></div></td>
                    <td><div class="cell-content"><?= htmlspecialchars($row['file_name'], ENT_QUOTES, 'UTF-8') ?></div></td>
                    <td><div class="cell-content"><?= htmlspecialchars($row['uploaded_by'], ENT_QUOTES, 'UTF-8') ?></div></td>
                    <td><div class="cell-content"><?= date('Y-m-d H:i:s', strtotime($row['uploaded_at'])) ?></div></td>
                    <td>
                        <button onclick="toggleStatus(<?= $row['id'] ?>, '<?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?>')" 
                                class="status-btn <?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fas fa-circle"></i> <?= ucfirst(htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8')) ?>
                        </button>
                    </td>
                    <td class="actions">
                        <div class="action-buttons">
                        <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">View</a>
                            <a href="download_paper.php?id=<?= $row['id'] ?>" class="icon-btn" title="Download">
                                <i class="fas fa-download"></i>
                            </a>
                            <?php if ($showEditButtons($row)): ?>
                                <div class="three-dot-menu" onclick="toggleDropdown(this)">
                                <i class="fas fa-ellipsis-v"></i>
                                <div class="dropdown-actions">
                                <button class="icon-btn" onclick='openEditModal(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)' title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="icon-btn group-chat-btn" onclick="openPaperGroupChat(<?= $row['id'] ?>)" title="Paper Group Chat">
                                        <i class="fas fa-comments"></i>
                                    </button>
                                <?php if ($role === 'admin' || $row['uploaded_by'] === $user_name): ?>
                                    <button class="icon-btn" onclick="confirmDelete(<?= $row['id'] ?>)" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    <button class="icon-btn share-btn" onclick="openShareModal(<?= $row['id'] ?>)" title="Share Access">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                <?php endif; ?>
                                </div>
                                </div>
                            <?php endif; ?>
                        </div>
                     </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php if ($role === 'researcher'): ?>
            <table id="researchTable" class="display">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Description</th>
                    <th>File Name</th>
                    <th>Uploaded By</th>
                    <th>Upload Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><div class="cell-content"><?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?></div></td>
                    <td><div class="cell-content"><?= htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8') ?></div></td>
                    <td><div class="cell-content"><?= htmlspecialchars($row['file_name'], ENT_QUOTES, 'UTF-8') ?></div></td>
                    <td><div class="cell-content"><?= htmlspecialchars($row['uploaded_by'], ENT_QUOTES, 'UTF-8') ?></div></td>
                    <td><div class="cell-content"><?= date('Y-m-d H:i:s', strtotime($row['uploaded_at'])) ?></div></td>
                    <td class="actions">
                            <div class="action-buttons">
                            <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">View</a>
                                <a href="download_paper.php?id=<?= $row['id'] ?>" class="icon-btn" title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                                <?php if ($showEditButtons($row)): ?>
                                    <div class="three-dot-menu" onclick="toggleDropdown(this)">
                                <i class="fas fa-ellipsis-v"></i>
                                <div class="dropdown-actions">
                                    <button class="icon-btn" onclick='openEditModal(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)' title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="icon-btn group-chat-btn" onclick="openPaperGroupChat(<?= $row['id'] ?>)" title="Paper Group Chat">
                                        <i class="fas fa-comments"></i>
                                    </button>
                                    <?php if ($role === 'admin' || $row['uploaded_by'] === $user_name): ?>
                                        <button class="icon-btn" onclick="confirmDelete(<?= $row['id'] ?>)" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                        <button class="icon-btn share-btn" onclick="openShareModal(<?= $row['id'] ?>)" title="Share Access">
                                            <i class="fas fa-user-plus"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                </div>
                                <?php endif; ?>
                            </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php if ($role === 'user'): ?>
            <table id="researchTable" class="display">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Description</th>
                    <th>File Name</th>
                    <th>Uploaded By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><div class="cell-content"><?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?></div></td>
                    <td><div class="cell-content"><?= htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8') ?></div></td>
                    <td><div class="cell-content"><?= htmlspecialchars($row['file_name'], ENT_QUOTES, 'UTF-8') ?></div></td>
                    <td><div class="cell-content"><?= htmlspecialchars($row['uploaded_by'], ENT_QUOTES, 'UTF-8') ?></div></td>
                    <td class="actions">
                            <div class="action-buttons">
                                <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">View</a>
                                <a href="download_paper.php?id=<?= $row['id'] ?>" class="icon-btn" title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- Edit Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content" style="width: 90%; max-width: 1200px;">
                <span class="close">&times;</span>
                <h2>Edit Research Paper</h2>
                <form id="editForm" action="update_paper.php" method="POST" enctype="multipart/form-data">
                <div class="button-container">
                <button type="submit" class="submit-btn">Update Paper Details</button>
            </div>
                    <input type="hidden" id="editId" name="id">
                    <div class="form-group">
                        <label for="editTitle">Title</label>
                        <input type="text" id="editTitle" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="editDescription">Description</label>
                        <textarea id="editDescription" name="description" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="editFile">Replace File (optional)</label>
                        <input type="file" id="editFile" name="file" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="documentEditor">Document Content</label>
                        <textarea id="documentEditor" name="content" style="width: 100%; min-height: 500px;"></textarea>
                        <button type="button" id="saveDocumentBtn" class="btn btn-primary mt-2" style="background: #4f46e5; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; margin-top: 10px;">
                            <i class="fas fa-save"></i> Save Document Content
                        </button>
                        <div id="saveStatus" style="margin-top: 5px; display: none;"></div>
                    </div>
                    
                    
                </form>
            </div>
        </div>

        <!-- Share Access Modal -->
        <div id="shareModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeShareModal()">&times;</span>
                <h2>Manage Access</h2>
                <form id="shareForm" onsubmit="handleShareSubmit(event)">
                    <input type="hidden" id="paper_id" name="paper_id">
                    <div class="form-group">
                        <div class="bulk-actions-container mb-2">
                            <button type="button" class="bulk-action-btn" onclick="selectAllResearchers()">
                                <i class="fas fa-users"></i> Select All
                            </button>
                            <button type="button" class="bulk-action-btn" onclick="deselectAllResearchers()">
                                <i class="fas fa-user-slash"></i> Deselect All
                            </button>
                            <button type="button" class="bulk-action-btn" onclick="toggleCurrentAccess()">
                                <i class="fas fa-exchange-alt"></i> Toggle Current Access
                            </button>
                        </div>
                        <label for="researchers">Current & New Researchers Access:</label>
                        <select name="researchers[]" id="researchers" class="form-control select2-multiple" multiple>
                            <?php
                            // Fetch all researchers except the current user
                            $researcher_query = "SELECT id, username, email FROM users WHERE role = 'researcher' AND username != ?";
                            $stmt = $conn->prepare($researcher_query);
                            $stmt->bind_param("s", $user_name);
                            $stmt->execute();
                            $researcher_result = $stmt->get_result();

                            if ($researcher_result && $researcher_result->num_rows > 0) {
                                while ($researcher = $researcher_result->fetch_assoc()) {
                                    $value = htmlspecialchars($researcher['username']);
                                    $label = htmlspecialchars($researcher['username']) . ' (' . htmlspecialchars($researcher['email']) . ')';
                                    echo '<option value="' . $value . '" data-email="' . htmlspecialchars($researcher['email']) . '">' . $label . '</option>';
                                }
                            }
                            ?>
                        </select>
                        <small class="form-text">
                            <i class="fas fa-info-circle"></i> Use bulk actions above or individually select/deselect researchers to manage access.
                        </small>
                    </div>
                    <button type="submit" class="submit-btn mt-3">Update Access</button>
                </form>
            </div>
        </div>

        <!-- Add Select2 CSS and JS -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    </div>

      </main>
      <!--end::App Main-->
      <!--begin::Footer-->
      
      <!--end::Footer-->
    </div>
    <!--end::App Wrapper-->
    <!--begin::Script-->
     <!-- Add jQuery and DataTables JS -->
     <script type="text/javascript" src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <!-- Add TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/jhquqn8e9578mwz3ln4gduhu9zymt069tdlbxkqomy7z8m7k/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        $(document).ready(function() {
            // Add popup elements to body
            $('body').append(`
                <div class="text-popup-backdrop"></div>
                <div class="text-popup"></div>
            `);

            const popup = $('.text-popup');
            const backdrop = $('.text-popup-backdrop');

            $('#researchTable').DataTable({
                responsive: true,
                scrollX: true,
                autoWidth: false,
                dom: '<"top"fl>rt<"bottom"ip>',
                pageLength: 10,
                order: [[<?= ($role === 'user' ? 0 : 5) ?>, 'desc']],
                columnDefs: [
                    <?php if ($role !== 'user'): ?>
                    {
                        targets: [0],  // Checkbox column
                        width: '30px',
                        orderable: false,
                        searchable: false
                    },
                    <?php endif; ?>
                    {
                        targets: [1, 2, 3, 4, 5],  // Regular columns
                        width: '100px',
                        render: function(data, type, row) {
                            if (type === 'display') {
                                let shortenedText = data.length > 25 ? data.substr(0, 22) + '...' : data;
                                return `<div class="cell-content">
                                    <span class="truncated-text" data-full-text="${data}">${shortenedText}</span>
                                    <div class="text-popup"></div>
                                </div>`;
                            }
                            return data;
                        }
                    },
                    {
                        targets: [6],  // Status column
                        width: '80px'
                    },
                    {
                        targets: [7],  // Actions column
                        width: '120px',
                        orderable: false,
                        searchable: false
                    }
                ],
                drawCallback: function() {
                    // Update click handler for truncated text
                    $('.truncated-text').click(function(e) {
                        e.stopPropagation();
                        const fullText = $(this).data('full-text');
                        const popup = $(this).siblings('.text-popup');
                        
                        // Hide all other popups
                        $('.text-popup').not(popup).hide();
                        
                        // Toggle current popup
                        popup.html(fullText).toggle();
                    });

                    // Close popup when clicking anywhere else
                    $(document).click(function() {
                        $('.text-popup').hide();
                    });

                    // Close popup when pressing ESC
                    $(document).keydown(function(e) {
                        if (e.key === "Escape") {
                            $('.text-popup').hide();
                        }
                    });
                },
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                },
                columnDefs: [
                    {
                        targets: [1],  // Title column
                        searchable: true
                    }
                ],
                // Improve search functionality
                search: {
                    return: true, // Enable search on Enter key
                    smart: true, // Enable smart search
                    regex: false, // Disable regex search
                    caseInsensitive: true
                },
                // Add search delay to improve performance
                searchDelay: 350
            });

            // Add search input placeholder
            $('.dataTables_filter input').attr('placeholder', 'Search...');

            // Add horizontal scroll hint for mobile
            if (window.innerWidth <= 768) {
                const table = $('#researchTable').closest('.dataTables_wrapper');
                const scrollHint = $('<div style="text-align: center; color: #666; padding: 0.5rem; font-size: 0.9rem;"><i class="fas fa-arrows-left-right"></i> Scroll horizontally to view more</div>');
                table.prepend(scrollHint);
            }

            // Select All functionality
            $('#selectAll').change(function() {
                $('.row-checkbox').prop('checked', this.checked);
            });

            // Update select all checkbox when individual checkboxes change
            $(document).on('change', '.row-checkbox', function() {
                const totalCheckboxes = $('.row-checkbox').length;
                const checkedCheckboxes = $('.row-checkbox:checked').length;
                $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
                
                // Show/hide bulk action container
                if (checkedCheckboxes > 0) {
                    $('.bulk-action-container').css('display', 'flex');
                } else {
                    $('.bulk-action-container').hide();
                }
            });

            // Handle select all functionality
            $('#selectAll').change(function() {
                const isChecked = $(this).prop('checked');
                $('.row-checkbox').prop('checked', isChecked);
                
                // Show/hide bulk action container
                if (isChecked) {
                    $('.bulk-action-container').css('display', 'flex');
                } else {
                    $('.bulk-action-container').hide();
                }
            });
        });

        function applyBulkAction() {
            const action = document.getElementById('bulkAction').value;
            const selectedIds = Array.from(document.getElementsByClassName('row-checkbox'))
                .filter(cb => cb.checked)
                .map(cb => cb.value);

            if (!action) {
                alert('Please select an action');
                return;
            }

            if (selectedIds.length === 0) {
                alert('Please select at least one item');
                return;
            }

            const confirmMessage = {
                'delete': 'Are you sure you want to delete the selected items?',
                'activate': 'Are you sure you want to activate the selected items?',
                'deactivate': 'Are you sure you want to deactivate the selected items?'
            };

            if (confirm(confirmMessage[action])) {
                fetch('bulk_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: action,
                        ids: selectedIds
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Something went wrong'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing your request');
                });
            }
        }

        // Modal functionality
        const modal = document.getElementById('editModal');
        const span = document.getElementsByClassName('close')[0];

        function openEditModal(data) {
            document.getElementById('editId').value = data.id;
            document.getElementById('editTitle').value = decodeHTMLEntities(data.title);
            document.getElementById('editDescription').value = decodeHTMLEntities(data.description);
            
            // Load document content
            loadDocumentContent(data.id);
            
            modal.style.display = 'block';
        }

        span.onclick = function() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Handle status toggle
        function toggleStatus(id, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            if (confirm('Are you sure you want to change the status?')) {
                fetch('update_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${id}&status=${newStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        }

        // Handle delete confirmation
        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this research paper?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="delete_id" value="${id}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Add this helper function to decode HTML entities
        function decodeHTMLEntities(text) {
            const textarea = document.createElement('textarea');
            textarea.innerHTML = text;
            return textarea.value;
        }

        // Share Modal functionality
        const shareModal = document.getElementById('shareModal');
        const shareSpan = shareModal.getElementsByClassName('close')[0];

        function openShareModal(paperId) {
            // Clear previous selections
            $('.select2-multiple').val(null).trigger('change');
            
            // Reset data-shared attributes
            $('.select2-multiple option').attr('data-shared', 'false');
            
            // Set paper ID
            document.getElementById('paper_id').value = paperId;
            
            // Show modal
            document.getElementById('shareModal').style.display = 'block';
            
            // Fetch current shares
            fetch(`${window.location.pathname}?action=get_existing_shares&paper_id=${paperId}`)
                .then(response => response.json())
                .then(data => {
                    if (Array.isArray(data)) {
                        // Set selected options based on current shares
                        $('.select2-multiple').val(data.map(share => share.username)).trigger('change');
                        
                        // Add visual indicator for existing shares
                        data.forEach(share => {
                            const option = $(`.select2-multiple option[value="${share.username}"]`);
                            option.attr('data-shared', 'true');
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading shares:', error);
                });
        }

        function handleShareSubmit(event) {
            event.preventDefault();
            
            const paperId = document.getElementById('paper_id').value;
            const selectedResearchers = $('.select2-multiple').val() || [];
            const currentlyShared = $('.select2-multiple option[data-shared="true"]').map(function() {
                return $(this).val();
            }).get();
            
            const removedAccess = currentlyShared.filter(user => !selectedResearchers.includes(user));
            const newAccess = selectedResearchers.filter(user => !currentlyShared.includes(user));
            
            let confirmMessage = '';
            if (removedAccess.length > 0) {
                confirmMessage += `Remove access for ${removedAccess.length} researcher(s)\n`;
            }
            if (newAccess.length > 0) {
                confirmMessage += `Grant access to ${newAccess.length} new researcher(s)\n`;
            }
            
            if (!confirmMessage) {
                confirmMessage = 'No changes to access permissions';
            }
            
            if (confirm(`Please confirm the following changes:\n\n${confirmMessage}`)) {
                fetch(window.location.pathname, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'update_shares',
                        paper_id: paperId,
                        researchers: selectedResearchers
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Access permissions updated successfully');
                        closeShareModal();
                    } else {
                        alert('Error updating access: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating access');
                });
            }
        }

        function closeShareModal() {
            document.getElementById('shareModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('shareModal')) {
                closeShareModal();
            }
        }

        // Add keyboard support for closing modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeShareModal();
            }
        });

        // Initialize TinyMCE
        tinymce.init({
            selector: '#documentEditor',
            height: 600,
            plugins: [
                'advlist autolink lists link image charmap print preview anchor',
                'searchreplace visualblocks code fullscreen',
                'insertdatetime media table paste imagetools wordcount',
                'pagebreak nonbreaking save contextmenu directionality',
                'emoticons template paste textcolor colorpicker textpattern'
            ],
            toolbar: 'insertfile undo redo | styleselect | fontselect fontsizeselect | ' +
                    'bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | ' +
                    'bullist numlist outdent indent | link image media | ' +
                    'forecolor backcolor emoticons | pagebreak | removeformat',
            content_style: `
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
                    font-size: 14px; 
                    line-height: 1.6;
                    margin: 20px;
                }
                table { border-collapse: collapse; width: 100%; margin: 10px 0; }
                td, th { border: 1px solid #ddd; padding: 8px; }
                img { 
                    max-width: 100%; 
                    height: auto; 
                    display: block; 
                    margin: 10px auto; 
                }
                .list-paragraph { margin-left: 20px; }
                .center { text-align: center; }
                .right { text-align: right; }
                .justify { text-align: justify; }
                .highlight { background-color: yellow; }
            `,
            images_upload_url: 'upload_image.php',
            automatic_uploads: true,
            images_reuse_filename: true,
            convert_urls: false,
            relative_urls: false,
            remove_script_host: false,
            paste_data_images: true,
            image_advtab: true,
            file_picker_types: 'image',
            image_title: true,
            image_dimensions: true,
            image_class_list: [
                {title: 'Responsive', value: 'img-fluid'},
                {title: 'Left', value: 'float-left'},
                {title: 'Right', value: 'float-right'},
                {title: 'Center', value: 'mx-auto d-block'}
            ],
            style_formats: [
                {title: 'Headers', items: [
                    {title: 'Header 1', format: 'h1'},
                    {title: 'Header 2', format: 'h2'},
                    {title: 'Header 3', format: 'h3'}
                ]},
                {title: 'Inline', items: [
                    {title: 'Bold', format: 'bold'},
                    {title: 'Italic', format: 'italic'},
                    {title: 'Underline', format: 'underline'},
                    {title: 'Strikethrough', format: 'strikethrough'},
                    {title: 'Superscript', format: 'superscript'},
                    {title: 'Subscript', format: 'subscript'},
                    {title: 'Code', format: 'code'}
                ]},
                {title: 'Blocks', items: [
                    {title: 'Paragraph', format: 'p'},
                    {title: 'Blockquote', format: 'blockquote'},
                    {title: 'Div', format: 'div'},
                    {title: 'Pre', format: 'pre'}
                ]},
                {title: 'Alignment', items: [
                    {title: 'Left', format: 'alignleft'},
                    {title: 'Center', format: 'aligncenter'},
                    {title: 'Right', format: 'alignright'},
                    {title: 'Justify', format: 'alignjustify'}
                ]}
            ],
            setup: function(editor) {
                editor.on('init', function() {
                    editor.setContent(content);
                    // Add custom CSS class to editor body
                    editor.dom.addClass(editor.getBody(), 'document-content');
                });
            }
        });

        // Function to load document content
        function loadDocumentContent(id) {
            $.get('get_document_content.php', { id: id }, function(content) {
                // Initialize TinyMCE with advanced configuration
                if (tinymce.get('documentEditor')) {
                    tinymce.get('documentEditor').remove();
                }
                tinymce.init({
                    selector: '#documentEditor',
                    height: 600,
                    plugins: [
                        'advlist autolink lists link image charmap print preview anchor',
                        'searchreplace visualblocks code fullscreen',
                        'insertdatetime media table paste imagetools wordcount',
                        'pagebreak nonbreaking save contextmenu directionality',
                        'emoticons template paste textcolor colorpicker textpattern'
                    ],
                    toolbar: 'insertfile undo redo | styleselect | fontselect fontsizeselect | ' +
                            'bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | ' +
                            'bullist numlist outdent indent | link image media | ' +
                            'forecolor backcolor emoticons | pagebreak | removeformat',
                    content_style: `
                        body { 
                            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
                            font-size: 14px; 
                            line-height: 1.6;
                            margin: 20px;
                        }
                        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
                        td, th { border: 1px solid #ddd; padding: 8px; }
                        img { 
                            max-width: 100%; 
                            height: auto; 
                            display: block; 
                            margin: 10px auto; 
                        }
                        .list-paragraph { margin-left: 20px; }
                        .center { text-align: center; }
                        .right { text-align: right; }
                        .justify { text-align: justify; }
                        .highlight { background-color: yellow; }
                    `,
                    images_upload_url: 'upload_image.php',
                    automatic_uploads: true,
                    images_reuse_filename: true,
                    convert_urls: false,
                    relative_urls: false,
                    remove_script_host: false,
                    paste_data_images: true,
                    image_advtab: true,
                    file_picker_types: 'image',
                    image_title: true,
                    image_dimensions: true,
                    image_class_list: [
                        {title: 'Responsive', value: 'img-fluid'},
                        {title: 'Left', value: 'float-left'},
                        {title: 'Right', value: 'float-right'},
                        {title: 'Center', value: 'mx-auto d-block'}
                    ],
                    style_formats: [
                        {title: 'Headers', items: [
                            {title: 'Header 1', format: 'h1'},
                            {title: 'Header 2', format: 'h2'},
                            {title: 'Header 3', format: 'h3'}
                        ]},
                        {title: 'Inline', items: [
                            {title: 'Bold', format: 'bold'},
                            {title: 'Italic', format: 'italic'},
                            {title: 'Underline', format: 'underline'},
                            {title: 'Strikethrough', format: 'strikethrough'},
                            {title: 'Superscript', format: 'superscript'},
                            {title: 'Subscript', format: 'subscript'},
                            {title: 'Code', format: 'code'}
                        ]},
                        {title: 'Blocks', items: [
                            {title: 'Paragraph', format: 'p'},
                            {title: 'Blockquote', format: 'blockquote'},
                            {title: 'Div', format: 'div'},
                            {title: 'Pre', format: 'pre'}
                        ]},
                        {title: 'Alignment', items: [
                            {title: 'Left', format: 'alignleft'},
                            {title: 'Center', format: 'aligncenter'},
                            {title: 'Right', format: 'alignright'},
                            {title: 'Justify', format: 'alignjustify'}
                        ]}
                    ],
                    setup: function(editor) {
                        editor.on('init', function() {
                            editor.setContent(content);
                            // Add custom CSS class to editor body
                            editor.dom.addClass(editor.getBody(), 'document-content');
                        });
                    }
                });
            });
        }

        // Modify the existing edit button click handler
        $(document).on('click', '.edit-btn', function() {
            const id = $(this).data('id');
            const title = $(this).data('title');
            const description = $(this).data('description');
            
            $('#editId').val(id);
            $('#editTitle').val(title);
            $('#editDescription').val(description);
            
            // Load document content
            loadDocumentContent(id);
            
            $('#editModal').show();
        });

        // Handle save content button click
        $('#saveDocumentBtn').click(function() {
            const id = $('#editId').val();
            const content = tinymce.get('documentEditor').getContent();
            
            // Show loading state
            const $btn = $(this);
            const originalText = $btn.html();
            $btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
            
            $.ajax({
                url: 'update_document_content.php',
                type: 'POST',
                data: {
                    id: id,
                    content: content
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        alert('Document content saved successfully!');
                    } else {
                        // Show error message
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error saving document content. Please try again.');
                },
                complete: function() {
                    // Restore button state
                    $btn.html(originalText).prop('disabled', false);
                }
            });
        });

        // Initialize Select2
        $(document).ready(function() {
            $('.select2-multiple').select2({
                theme: 'classic',
                width: '100%',
                placeholder: 'Search researchers...',
                allowClear: true,
                closeOnSelect: false,
                templateResult: formatResearcherOption,
                templateSelection: formatResearcherSelection
            });
        });

        // Format the option in dropdown
        function formatResearcherOption(researcher) {
            if (!researcher.id) return researcher.text;
            
            const isShared = $(researcher.element).attr('data-shared') === 'true';
            const email = $(researcher.element).data('email');
            
            return $(`
                <div class="researcher-option">
                    <div class="researcher-name">
                        ${researcher.text}
                        ${isShared ? '<span class="shared-badge">Current Access</span>' : ''}
                    </div>
                </div>
            `);
        }

        // Format the selected option
        function formatResearcherSelection(researcher) {
            if (!researcher.id) return researcher.text;
            
            const isShared = $(researcher.element).attr('data-shared') === 'true';
            return $(`
                <div class="researcher-selection">
                    ${researcher.text}
                    ${isShared ? '<span class="shared-badge-mini">Current</span>' : ''}
                </div>
            `);
        }

        // Add these new functions
        function selectAllResearchers() {
            const allOptions = $('.select2-multiple option').map(function() {
                return $(this).val();
            }).get();
            
            $('.select2-multiple').val(allOptions).trigger('change');
        }

        function deselectAllResearchers() {
            $('.select2-multiple').val(null).trigger('change');
        }

        function toggleCurrentAccess() {
            const currentlyShared = $('.select2-multiple option[data-shared="true"]').map(function() {
                return $(this).val();
            }).get();
            
            const currentSelection = $('.select2-multiple').val() || [];
            
            // If all current shares are selected, deselect them
            if (currentlyShared.every(share => currentSelection.includes(share))) {
                const newSelection = currentSelection.filter(item => !currentlyShared.includes(item));
                $('.select2-multiple').val(newSelection).trigger('change');
            } else {
                // Otherwise, select all current shares
                const newSelection = [...new Set([...currentSelection, ...currentlyShared])];
                $('.select2-multiple').val(newSelection).trigger('change');
            }
        }
       // Add this function to your existing JavaScript section
       function openPaperGroupChat(paperId) {
            window.open(`paper_group_chat.php?paper_id=${paperId}`, 'Paper Group Chat', 'width=1000,height=800');
        }
        
// Add these JavaScript functions to the existing  section
function toggleDropdown(element) {
    // Close any other open dropdowns
    const allDropdowns = document.querySelectorAll('.dropdown-actions');
    allDropdowns.forEach(dropdown => {
        if (dropdown !== element.querySelector('.dropdown-actions')) {
            dropdown.style.display = 'none';
        }
    });

    // Toggle the clicked dropdown
    const dropdown = element.querySelector('.dropdown-actions');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    const dropdowns = document.querySelectorAll('.dropdown-actions');
    dropdowns.forEach(dropdown => {
        if (!dropdown.parentElement.contains(event.target)) {
            dropdown.style.display = 'none';
        }
    });
});

    </script>
   
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
      integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy"
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
        $(document).ready(function() {
            // Add checkbox change handlers
            $('#selectAll, .row-checkbox').change(function() {
                const checkedCount = $('.row-checkbox:checked').length;
                if (checkedCount > 0) {
                    $('.bulk-action-container').css('display', 'flex');
                } else {
                    $('.bulk-action-container').hide();
                }
            });

            // Update select all checkbox when individual checkboxes change
            $(document).on('change', '.row-checkbox', function() {
                const totalCheckboxes = $('.row-checkbox').length;
                const checkedCheckboxes = $('.row-checkbox:checked').length;
                $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
                
                // Show/hide bulk action container
                if (checkedCheckboxes > 0) {
                    $('.bulk-action-container').css('display', 'flex');
                } else {
                    $('.bulk-action-container').hide();
                }
            });

            // Handle select all functionality
            $('#selectAll').change(function() {
                const isChecked = $(this).prop('checked');
                $('.row-checkbox').prop('checked', isChecked);
                
                // Show/hide bulk action container
                if (isChecked) {
                    $('.bulk-action-container').css('display', 'flex');
                } else {
                    $('.bulk-action-container').hide();
                }
            });
        });
    </script>
    <!--end::Script-->
    <!-- Add this modal at the bottom of the body tag -->

  </body>
  <!--end::Body-->
</html>
