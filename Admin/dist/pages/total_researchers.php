<?php
session_start();
include 'config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header("location: admin_login.php");
    exit();
}

$showAlert = false;
$showModal = false; // Control whether the modal is shown
$errors = [
    'name' => '',
    'username' => '',
    'email' => '',
    'password' => '',
    'confirmpassword' => '',
    'image' => ''
];

$name = $username = $email = ''; // Initialize fields as empty

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $showModal = true; // Show the modal again if there are errors
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmpassword = $_POST['confirmpassword'];

    // Validation for each field
    if (empty($name)) {
        $errors['name'] = "Name is required.";
    } elseif (strlen($name) < 3 || strlen($name) > 50) {
        $errors['name'] = "Name must be between 3 and 50 characters.";
    } elseif (!preg_match("/^[a-zA-Z .]*$/", $name)) {
        $errors['name'] = "Only letters and white space allowed in Name.";
    }

    if (empty($username)) {
        $errors['username'] = "Username is required.";
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $errors['username'] = "Username must be between 3 and 20 characters.";
    } elseif (!preg_match("/^[a-zA-Z0-9_]*$/", $username)) {
        $errors['username'] = "Only letters, digits, and underscores allowed in Username.";
    } elseif (!preg_match("/[a-zA-Z]/", $username) || !preg_match("/[0-9]/", $username)) {
        $errors['username'] = "Username must contain at least one letter and one digit.";
    } else {
        $usernameQuery = "SELECT * FROM users WHERE Username = '$username'";
        $usernameResult = mysqli_query($conn, $usernameQuery);
        if (mysqli_num_rows($usernameResult) > 0) {
            $errors['username'] = "Username already exists.";
        }
    }

    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    } else {
        $emailQuery = "SELECT * FROM users WHERE Email = '$email'";
        $emailResult = mysqli_query($conn, $emailQuery);
        if (mysqli_num_rows($emailResult) > 0) {
            $errors['email'] = "Email already exists.";
        }
    }

    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 8 || strlen($password) > 15) {
        $errors['password'] = "Password must be between 8 and 15 characters.";
    } elseif (!preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/[0-9]/", $password) || !preg_match("/[!@#$%^&*(),.?\":{}|<>]/", $password)) {
        $errors['password'] = "Password must include uppercase, lowercase, a digit, and a special character.";
    } elseif ($password !== $confirmpassword) {
        $errors['confirmpassword'] = "Passwords do not match.";
    }

    // Image validation
    $imagePath = "";
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $imageName = $_FILES['image']['name'];
        $imageTmpName = $_FILES['image']['tmp_name'];
        $uploadDir = 'uploads/';
        $fileExtension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
        $uniqueImageName = uniqid("user_", true) . '.' . $fileExtension;
        $imagePath = $uploadDir . $uniqueImageName;

        $allowedTypes = ['image/jpeg', 'image/png'];
        $fileType = mime_content_type($imageTmpName);

        if (!in_array($fileType, $allowedTypes) || !in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
            $errors['image'] = "Only JPG and PNG images are allowed.";
        } elseif ($_FILES['image']['size'] > 500 * 1024) {
            $errors['image'] = "Image size should not exceed 500kb.";
        } elseif (!move_uploaded_file($imageTmpName, $imagePath)) {
            $errors['image'] = "Failed to upload the image.";
        }
    } else {
        $errors['image'] = "Image is required.";
    }

    // Insert into database if no errors
    if (array_filter($errors) === []) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = $_POST['role']; // Get the selected role
        $sql = "INSERT INTO users (Name, Username, Email, Password, Image, is_active, role) VALUES ('$name', '$username', '$email', '$hashed_password', '$imagePath', 0, '$role');";
        $result = mysqli_query($conn, $sql);

        if ($result) {
            $showAlert = true;
            // Clear inputs and errors after success
            $name = $username = $email = '';
            $password = $confirmpassword = '';
            $imagePath = '';
            $errors = []; // Clear errors
            
            // Redirect based on role
            if ($role === 'researcher') {
                header("Location: total_researchers.php");
            } else {
                header("Location: total_users.php");
            }
            exit();
        } else {
            $errors['general'] = "Database error: " . mysqli_error($conn);
        }
    }
}
else{
    $showModal = false; 
}

// Fetch Total Records
$recordsPerPage = 10; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Current page
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $recordsPerPage;

// Count total records
$totalRecordsQuery = "SELECT COUNT(*) AS total FROM users WHERE role = 'researcher'";
$totalRecordsResult = $conn->query($totalRecordsQuery);
$totalRecords = $totalRecordsResult->fetch_assoc()['total'];

// Calculate total pages
$totalPages = ceil($totalRecords / $recordsPerPage);

// Fetch records for the current page
$sql = "SELECT id, name, username, email, image, is_active FROM users WHERE role = 'researcher' LIMIT $recordsPerPage OFFSET $offset";
$result = $conn->query($sql);
$admin_name = $_SESSION['username'];

?>

<?php
if (isset($_GET['fetch_all'])) {
    $query = "SELECT id, name, username, email, image, is_active FROM users WHERE role = 'researcher'";
    $result = $conn->query($query);
    $users = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($users);
    exit;
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
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('img/research-hub-watermark.png') center/300px no-repeat fixed,
                        linear-gradient(rgba(255, 255, 255, 0.97), rgba(255, 255, 255, 0.97));
            z-index: -1;
            opacity: 0.1;
        }

        .page-header {
            background: #1a237e;
            color: white;
            padding: 20px 30px;
            margin-bottom: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            margin: 0;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
        }

        .admin-info i {
            font-size: 20px;
        }

        .breadcrumb {
            background: white;
            padding: 10px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .breadcrumb a {
            color: #1a237e;
            text-decoration: none;
        }

        .breadcrumb i {
            margin: 0 8px;
            color: #666;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .main-content {
            padding: 30px;
            max-width: 100%;
            margin: 0;
        }

        .add-user-btn {
            margin-bottom: 20px;
            padding: 12px 24px;
            background-color: #2c3e50;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .add-user-btn:hover {
            background-color: #34495e;
            transform: translateY(-1px);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
            z-index: 1000;
            overflow-y: auto;
            padding: 20px;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        th, td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background-color: #1a237e;
            color: white;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: 0.5px;
        }

        th:first-child {
            border-top-left-radius: 8px;
        }

        th:last-child {
            border-top-right-radius: 8px;
        }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: flex-start;
            align-items: center;
        }

        .actions button {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 12px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .actions button i {
            font-size: 18px;
        }

        .actions .edit {
            color: #2196f3;
        }

        .actions .delete {
            color: #f44336;
        }

        .actions .status-toggle.on {
            color: #4caf50;
        }

        .actions .status-toggle.off {
            color: #9e9e9e;
        }

        .actions button:hover {
            transform: scale(1.1);
            background-color: rgba(0,0,0,0.05);
        }

        .user-image {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #e0e0e0;
        }

        .modal-content {
            width: 400px;
            background-color: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            margin: 20px auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }

        .form-group input:focus {
            border-color: #2196f3;
            outline: none;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #757575;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .input-group i:hover {
            color: #2196f3;
        }

        .error-message {
            color: #f44336;
            font-size: 13px;
            margin-top: 5px;
        }

        .success {
            color: #4caf50;
            text-align: center;
            padding: 10px;
            margin-bottom: 15px;
            background-color: #e8f5e9;
            border-radius: 4px;
        }

        .modal-content button {
            width: 100%;
            padding: 12px;
            background-color: #1a237e;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-top: 10px;
        }

        .modal-content button:hover {
            background-color: #283593;
        }

        .home-option {
            text-align: center;
            margin-top: 15px;
        }

        .k {
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
            text-align: center;
        }

        .k a {
            color: #1a237e;
            text-decoration: none;
        }

        .k a:hover {
            text-decoration: underline;
        }

        #editUserModal .modal-content {
            width: 90%;
            max-width: 500px;
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            position: relative;
            animation: modalFadeIn 0.3s ease-out;
            margin: 20px auto;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #editUserModal h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 24px;
            font-weight: 600;
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            position: sticky;
            top: 0;
            background: white;
            z-index: 1;
        }

        #editUserModal form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        #editUserModal .edit-img {
            text-align: center;
            margin-bottom: 15px;
        }

        #editUserModal .user-image-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 0 0 2px #1a237e;
            margin: 0 auto;
            display: block;
        }

        #editUserModal .form-group {
            position: relative;
            margin-bottom: 3px;
        }

        #editUserModal label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
        }

        #editUserModal input:not([type="file"]) {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        #editUserModal input:focus {
            border-color: #1a237e;
            box-shadow: 0 0 0 2px rgba(26, 35, 126, 0.1);
            outline: none;
        }

        #editUserModal input[type="file"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 2px dashed #e0e0e0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        #editUserModal input[type="file"]:hover {
            border-color: #1a237e;
            background-color: rgba(26, 35, 126, 0.05);
        }

        #editUserModal .error-message {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 3px;
            display: block;
        }

        #editUserModal .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            position: sticky;
            bottom: 0;
            background: white;
            padding: 12px 0;
            z-index: 1;
        }

        #editUserModal button {
            flex: 1;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        #editUserModal button[type="submit"] {
            background-color: #1a237e;
            color: white;
        }

        #editUserModal button[type="submit"]:hover {
            background-color: #283593;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(26, 35, 126, 0.2);
        }

        #editUserModal button[type="button"] {
            background-color: white;
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }

        #editUserModal button[type="button"]:hover {
            background-color: #fef2f2;
            color: #dc2626;
        }

        /* Loading state for buttons */
        #editUserModal button.loading {
            opacity: 0.7;
            cursor: not-allowed;
            position: relative;
        }

        #editUserModal button.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin: -10px 0 0 -10px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Media Queries for Responsiveness */
        @media screen and (max-width: 768px) {
            #editUserModal .modal-content {
                width: 95%;
                padding: 15px;
            }

            #editUserModal .button-group {
                flex-direction: column;
            }

            #editUserModal button {
                width: 100%;
            }
        }
        .role-select-container {
        margin-bottom: 20px;
    }

    .role-select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 3px;
        background-color: white;
        font-size: 16px;
        color: #333;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .role-select:focus {
        border-color: #c43b68;
        outline: none;
        box-shadow: 0 0 5px rgba(196, 59, 104, 0.2);
    }

    .role-select option {
        padding: 10px;
    }

    .selected-row {
        background-color: #f0f9ff !important;
    }

    mark {
        background-color: #fff3cd;
        padding: 0.2em;
        border-radius: 2px;
    }

    #selectedCounter {
        margin-left: 10px;
        font-size: 0.9em;
        color: #666;
    }

    .text-center {
        text-align: center;
    }

    .p-3 {
        padding: 1rem;
    }

    .mb-2 {
        margin-bottom: 0.5rem;
    }

    .mb-0 {
        margin-bottom: 0;
    }

    .text-muted {
        color: #6c757d;
    }

    .text-danger {
        color: #dc3545;
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
    <div class="main-content">
        

        <div class="breadcrumb">
            <a href="../../index.html">Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <span>Total Researchers</span>
        </div>

        <?php
    // Get total researchers
    $total_researchers_query = "SELECT COUNT(*) as total FROM users WHERE role = 'researcher'";
    $total_researchers_result = mysqli_query($conn, $total_researchers_query);
    $total_researchers = mysqli_fetch_assoc($total_researchers_result)['total'];

    // Get active researchers
    $active_researchers_query = "SELECT COUNT(*) as active FROM users WHERE role = 'researcher' AND is_active = '1'";
    $active_researchers_result = mysqli_query($conn, $active_researchers_query);
    $active_researchers = mysqli_fetch_assoc($active_researchers_result)['active'];

    // Get inactive researchers
    $inactive_researchers_query = "SELECT COUNT(*) as inactive FROM users WHERE role = 'researcher' AND is_active = '0'";
    $inactive_researchers_result = mysqli_query($conn, $inactive_researchers_query);
    $inactive_researchers = mysqli_fetch_assoc($inactive_researchers_result)['inactive'];

    // Get new researchers (last 7 days)
    $new_researchers_query = "SELECT COUNT(*) as new_researchers FROM users WHERE role = 'researcher' AND join_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $new_researchers_result = mysqli_query($conn, $new_researchers_query);
    $new_researchers = mysqli_fetch_assoc($new_researchers_result)['new_researchers'];
    ?>

    <!-- Statistics Boxes -->
    <div class="container-fluid mt-4 mb-4">
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Researchers</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_researchers; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
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
                                    Active Researchers</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_researchers; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-check fa-2x text-gray-300"></i>
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
                                    Inactive Researchers</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $inactive_researchers; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-times fa-2x text-gray-300"></i>
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
                                    New Researchers (Last 7 Days)</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $new_researchers; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


        <button class="add-user-btn" onclick="showModal()">
            <i class="fa-solid fa-plus"></i> New User
        </button>

        <!-- Add User Modal Form -->
      
            
        <div id="addUserModal" class="modal" style="display: <?= $showModal ? 'flex' : 'none' ?>;">

    <div class="modal-content">
        <h3 class="k">Add New User</h3>
        <?php if ($showAlert): ?>
            <div class="success">User successfully registered!</div>
        <?php endif; ?>
        <?php if (!empty($errors['general'])): ?>
            <div class="error"><?= htmlspecialchars($errors['general']) ?></div>
        <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                <input type="text" id="name" name="name" placeholder="Name" class="custom-field" value="<?= htmlspecialchars($name) ?>">
                <?php if (!empty($errors['name'])): ?><div class="error-message"><?= $errors['name'] ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <input type="text" id="username" name="username" placeholder="Username" class="custom-field" value="<?= htmlspecialchars($username) ?>">
                <?php if (!empty($errors['username'])): ?><div class="error-message"><?= $errors['username'] ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <input type="email" id="email" name="email" placeholder="Email" class="custom-field" value="<?= htmlspecialchars($email) ?>">
                <?php if (!empty($errors['email'])): ?><div class="error-message"><?= $errors['email'] ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <div class="input-group">
                   <input type="password" id="password" name="password" placeholder="Password">
                   <i class="fa-regular fa-eye-slash" id="togglePassword"></i>
                </div>
                <?php if (!empty($errors['password'])): ?> <div class="error-message"><?= $errors['password'] ?></div> <?php endif; ?>
            </div>

            <div class="form-group">
                <div class="input-group">
                   <input type="password" id="confirmpassword" name="confirmpassword" placeholder="Confirm Password">
                   <i class="fa-regular fa-eye-slash" id="toggleConfirmPassword"></i>
                </div>
            <?php if (!empty($errors['confirmpassword'])): ?> <div class="error-message"><?= $errors['confirmpassword'] ?></div> <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="image">Photo</label>
                <input type="file" id="image" name="image">
                <?php if (!empty($errors['image'])): ?><div class="error-message"><?= $errors['image'] ?></div><?php endif; ?>
            </div>
            <div class="form-group role-select-container">
                <select id="role" name="role" class="role-select">
                    <option value="" disabled selected>Select Your Role</option>
                    <option value="researcher">Researcher</option>
                    <option value="user">User</option>
                </select>
                <?php if (!empty($errors['role'])): ?><div class="error-message"><?= $errors['role'] ?></div><?php endif; ?>
            </div>
            <button type="submit">Register New User</button>
                    <button type="button"  style=" background-color:white;  color: #e74c3c;   border :1px solid  #e74c3c;" onclick="hideModal()">Cancel</button>
                </form>
            </div>
        </div>

        <div id="editUserModal" class="modal">
            <div class="modal-content">
                <h2>Edit User</h2>
                <form onsubmit="event.preventDefault(); saveUserChanges();">
                    <input type="hidden" id="editUserId">
                    
                    <div class="edit-img">
                        <img id="editImage" src="" alt="Current Profile Image" class="user-image-preview">
                    </div>
                    
                    <div class="form-group">
                        <label for="editImageFile">Update Profile Image</label>
                        <input type="file" id="editImageFile" accept="image/*">
                        <span id="imageError" class="error-message"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="editName">Name</label>
                        <input type="text" id="editName" required>
                        <span id="nameError" class="error-message"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="editUsername">Username</label>
                        <input type="text" id="editUsername" required>
                        <span id="usernameError" class="error-message"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="editEmail">Email</label>
                        <input type="email" id="editEmail" required>
                        <span id="emailError" class="error-message"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="editPassword">Password (leave blank to keep current)</label>
                        <input type="password" id="editPassword">
                        <span id="passwordError" class="error-message"></span>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit">Save Changes</button>
                        <button type="button" onclick="document.getElementById('editUserModal').style.display='none';">Cancel</button>
                    </div>
                </form>
            </div>
        </div>


        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
    <h2>All Researchers</h2>
    <div style="display: flex; align-items: center; gap: 5px;">
        <!-- Add Bulk Action Controls -->
        <div id="bulkActionControls" style="display: none; margin-right: 15px;">
            <button id="bulkDeleteBtn" class="bulk-action-btn" onclick="bulkDelete()" style="
                background-color: #e74c3c;
                color: white;
                padding: 8px 15px;
                border: none;
                border-radius: 3px;
                cursor: pointer;
                margin-right: 5px;
            ">
                <i class="fa-solid fa-trash"></i>
            </button>
            <button id="bulkToggleBtn" class="bulk-action-btn" onclick="bulkToggleStatus()" style="
                background-color: #2ecc71;
                color: white;
                padding: 8px 15px;
                border: none;
                border-radius: 3px;
                cursor: pointer;
            ">
                <i class="fa-solid fa-toggle-on"></i> 
            </button>
        </div>
        <input type="text" 
               id="searchInput" 
               placeholder="Search users..." 
               onkeypress="handleSearchKeyPress(event)"
               style="
                   padding: 10px; 
                   border: 1px solid #ccc; 
                   border-radius: 3px;
                   width: 250px;
               ">

        <button id="searchButton" style="
            background-color: #c43b68; 
            color: white; 
            padding: 10px; 
            border: none; 
            cursor: pointer; 
            border-radius: 3px;
        " onclick="filterUsers()">
            <i class="fa-solid fa-search"></i>
        </button>
        <div style="display: flex; align-items: center; gap: 10px;">
    <select id="filterOption" onchange="filterUsersByStatus()" style="padding: 10px; border: 1px solid #ccc; border-radius: 3px;">
        <option value="all">All Researchers</option>
        <option value="active">All Active Researchers</option>
        <option value="inactive">All Inactive Researchers</option>
    </select>
</div>

          <!-- Download Button -->
          <button style="
            background-color: #c43b68; 
            color: white; 
            padding: 10px; 
            border: none; 
            cursor: pointer; 
            border-radius: 3px;
        " onclick="downloadPDF()">
            <i class="fa-solid fa-download"></i> Export
        </button>
    </div>
</div>


        <table>
            <thead>
            <tr>
                <th>
                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                </th>
                <th>#</th>
                <th>ID</th>
                <th>Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Image</th>
                <th>Actions</th>
            </tr>
                </thead>
                <tbody id="userTableBody">
                <?php
$index = $offset + 1;
while ($row = $result->fetch_assoc()) {
    // Determine the toggle button classes and icons based on the user's active status
    $statusClass = $row['is_active'] ? 'on' : 'off';
    $statusIcon = $row['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off';

    echo "<tr>
        <td>
            <input type='checkbox' class='user-checkbox' data-userid='{$row['id']}' onclick='updateBulkActionControls()'>
        </td>
        <td>{$index}</td>
        <td>{$row['id']}</td>
        <td>{$row['name']}</td>
        <td>{$row['username']}</td>
        <td>{$row['email']}</td>
        <td><img src='{$row['image']}' class='user-image'></td>
        <td class='actions'>
             <!-- Edit Button -->
              <button class='edit' onclick='editUser({$row['id']}, \"{$row['name']}\", \"{$row['username']}\", \"{$row['email']}\", \"{$row['image']}\")'>
        <i class='fa-solid fa-pen-to-square'></i>
    </button>
             
             <!-- Delete Button with Confirmation -->
             <button class='delete' onclick='confirmDeleteUser({$row['id']})'>
                 <i class='fa-solid fa-trash'></i>
             </button>
             
             <!-- Status Toggle Button -->
             <button class='status-toggle {$statusClass}' onclick='toggleUserStatus({$row['id']}, \"{$row['username']}\", {$row['is_active']})'>
                 <i class='fa-solid {$statusIcon}'></i>
             </button>
        </td>
    </tr>";
    $index++;
}
?>

            </tbody>
            </table>

        <div id="pagination" style="margin-top: 20px; text-align: center;">
    <?php if ($totalPages > 1): ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="total_users.php?page=<?= $i ?>" 
               style="display: inline-block; padding: 10px; margin: 5px; background-color: <?= ($i == $page) ? '#8903dc' : '#f0f0f0' ?>; color: <?= ($i == $page) ? 'white' : 'black' ?>; text-decoration: none; border-radius: 5px;">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    <?php endif; ?>
</div>  


    </div>

    
        <!--end::App Content-->
      </main>
      <!--end::App Main-->
      <!--begin::Footer-->
      
      <!--end::Footer-->
    </div>
    <!--end::App Wrapper-->
    <!--begin::Script-->
    <script>
     function showModal() {
    // Reset the form fields
    document.querySelector('#addUserModal form').reset();

    // Clear any error messages
    const errorMessages = document.querySelectorAll('.error-message');
    errorMessages.forEach(error => {
        error.textContent = '';
    });

    // Optionally reset other fields or custom UI elements here
    const customFields = document.querySelectorAll('.custom-field');
    customFields.forEach(field => {
        if (field.tagName === 'INPUT' || field.tagName === 'TEXTAREA') {
            field.value = '';
        }
        if (field.tagName === 'SELECT') {
            field.selectedIndex = 0;
        }
    });

    // Show the modal
    document.getElementById('addUserModal').style.display = 'flex';
}


function hideModal() {
    // Hide the modal
    document.getElementById('addUserModal').style.display = 'none';

    // Reset the form
    const form = document.querySelector('#addUserModal form');
    form.reset();

    // Clear any error messages
    const errorMessages = document.querySelectorAll('.error-message');
    errorMessages.forEach(error => {
        error.textContent = '';
    });

    // Reset input field styles (if any style was applied for errors)
    const inputs = form.querySelectorAll('input');
    inputs.forEach(input => {
        input.value = ''; // Clear inputs
    });
}
        // Function to toggle the status of the user (on/off)
        function toggleStatus(button) {
            // Toggle between 'on' and 'off' classes
            if (button.classList.contains('off')) {
                button.classList.remove('off');
                button.classList.add('on');
                button.innerHTML = '<i class="fa-solid fa-toggle-on"></i>';
            } else {
                button.classList.remove('on');
                button.classList.add('off');
                button.innerHTML = '<i class="fa-solid fa-toggle-off"></i>';
            }
        }

       
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const confirmPasswordInput = document.getElementById('confirmpassword');

        // Toggle Password Visibility
        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            togglePassword.classList.toggle('fa-eye');
            togglePassword.classList.toggle('fa-eye-slash');
        });

        // Toggle Confirm Password Visibility
        toggleConfirmPassword.addEventListener('click', () => {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            toggleConfirmPassword.classList.toggle('fa-eye');
            toggleConfirmPassword.classList.toggle('fa-eye-slash');
        });


        let allUsers = []; // Store all user records

        async function filterUsers() {
    const query = document.getElementById("searchInput").value.toLowerCase();
    const tableBody = document.querySelector("table tbody");
    const pagination = document.getElementById("pagination");

    try {
        // Add loading state
        tableBody.innerHTML = '<tr><td colspan="8" class="text-center">Loading...</td></tr>';
        
        const response = await fetch(`fetch_all_users.php?search=${encodeURIComponent(query)}`);
        const users = await response.json();

        tableBody.innerHTML = "";
        pagination.style.display = users.length > 0 ? "none" : "block";

        if (users.length > 0) {
            users.forEach((user, index) => {
                const isExactMatch = 
                    user.name.toLowerCase() === query ||
                    user.username.toLowerCase() === query ||
                    user.email.toLowerCase() === query;

                const row = document.createElement("tr");
                if (isExactMatch) {
                    row.style.backgroundColor = "#f0f9ff"; // Light blue highlight
                }

                row.innerHTML = `
                    <td>
                        <input type='checkbox' class='user-checkbox' 
                            data-userid='${user.id}' 
                            data-status='${user.is_active}'
                            onclick='updateBulkActionControls()'>
                    </td>
                    <td>${index + 1}</td>
                    <td>${user.id}</td>
                    <td>${highlightText(user.name, query)}</td>
                    <td>${highlightText(user.username, query)}</td>
                    <td>${highlightText(user.email, query)}</td>
                    <td><img src="${user.image}" class="user-image" alt="${user.name}'s profile"></td>
                    <td class="actions">
                        <button class='edit' title="Edit user" onclick='editUser(${JSON.stringify(user)})'>
                            <i class='fa-solid fa-pen-to-square'></i>
                        </button>
                        <button class='delete' title="Delete user" onclick='confirmDeleteUser(${user.id})'>
                            <i class='fa-solid fa-trash'></i>
                        </button>
                        <button class='status-toggle ${user.is_active ? "on" : "off"}' 
                            title="${user.is_active ? 'Deactivate' : 'Activate'} user"
                            onclick='toggleUserStatus(${user.id}, "${user.username}", ${user.is_active})'>
                            <i class='fa-solid fa-toggle-${user.is_active ? "on" : "off"}'></i>
                        </button>
                    </td>
                `;
                tableBody.appendChild(row);
            });
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center">
                        <div class="p-3">
                            <i class="fa-solid fa-search fa-2x mb-2 text-muted"></i>
                            <p class="mb-0">No matches found for "${query}"</p>
                        </div>
                    </td>
                </tr>`;
        }
    } catch (error) {
        console.error("Error fetching users:", error);
        tableBody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-danger">
                    <i class="fa-solid fa-exclamation-triangle"></i> 
                    Error loading users. Please try again.
                </td>
            </tr>`;
    }
}

// Helper function to highlight matching text
function highlightText(text, query) {
    if (!query) return text;
    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
}

function downloadPDF() {
    const filterOption = document.getElementById("filterOption").value;

    // Send the filter and user data to the server to generate PDF
    fetch('generate_pdf.php?filter=' + filterOption + '&page=researchers')
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'researchers_list.pdf';
            document.body.appendChild(a);
            a.click();
            a.remove();
        })
        .catch(error => {
            console.error('Error generating PDF:', error);
            alert('Failed to generate PDF. Please try again.');
        });
}
function editUser(userId, name, username, email, image) {
    // Populate a form or modal with user data
    const modal = document.getElementById('editUserModal'); // Assuming you have a modal for editing
    modal.querySelector('#editUserId').value = userId;
    modal.querySelector('#editName').value = name;
    modal.querySelector('#editUsername').value = username;
    modal.querySelector('#editEmail').value = email;
    modal.querySelector('#editImage').src = image;

    // Show the modal
    modal.style.display = 'block';
}

// Function to handle form submission
async function saveUserChanges() {
        const userId = document.getElementById('editUserId').value;
        const name = document.getElementById('editName').value;
        const username = document.getElementById('editUsername').value;
        const email = document.getElementById('editEmail').value;
        const password = document.getElementById('editPassword').value;
        const imageFile = document.getElementById('editImageFile').files[0];

        // Clear previous error messages
        clearErrors();

        // Prepare FormData for sending data
        const formData = new FormData();
        formData.append('id', userId);
        formData.append('name', name);
        formData.append('username', username);
        formData.append('email', email);
        formData.append('password', password);
        if (imageFile) {
            formData.append('image', imageFile);
        }

        try {
            const response = await fetch('edit_user.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                alert(result.message);
                location.reload(); // Reload the page to reflect changes
            } else {
                displayErrors(result.errors); // Show error messages
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while saving changes.');
        }
    }
    

    // Function to display error messages
    function displayErrors(errors) {
        if (errors.name) {
            document.getElementById('nameError').textContent = errors.name;
        }
        if (errors.username) {
            document.getElementById('usernameError').textContent = errors.username;
        }
        if (errors.email) {
            document.getElementById('emailError').textContent = errors.email;
        }
        if (errors.password) {
            document.getElementById('passwordError').textContent = errors.password;
        }
        if (errors.image) {
            document.getElementById('imageError').textContent = errors.image;
        }
    }

    // Function to clear error messages
    function clearErrors() {
        document.getElementById('nameError').textContent = '';
        document.getElementById('usernameError').textContent = '';
        document.getElementById('emailError').textContent = '';
        document.getElementById('passwordError').textContent = '';
        document.getElementById('imageError').textContent = '';
    }

async function confirmDeleteUser(userId) {
    // Confirm deletion
    if (confirm("Are you sure you want to delete this user? This action cannot be undone.")) {
        try {
            const response = await fetch('delete_user.php', {
                method: 'POST',
                body: JSON.stringify({ id: userId }),
                headers: { 'Content-Type': 'application/json' }
            });

            const data = await response.json();
            if (data.success) {
                alert("User deleted successfully.");
                location.reload(); // Reload the page to update the table
            } else {
                alert("Failed to delete user.");
            }
        } catch (error) {
            console.error("Error deleting user:", error);
            alert("An error occurred while deleting the user.");
        }
    }
}

async function toggleUserStatus(userId, username, isActive) {
    const newStatus = isActive ? 'inactive' : 'active';
    const action = isActive ? 'deactivate' : 'activate';

    if (confirm(`Are you sure you want to ${action} ${username}?`)) {
        try {
            const payload = { id: userId, status: newStatus };
            console.log("Request payload:", payload); // Debugging

            const response = await fetch('toggle_status.php', {
                method: 'POST',
                body: JSON.stringify(payload),
                headers: { 'Content-Type': 'application/json' }
            });

            const data = await response.json();
            console.log("Server response:", data); // Debugging

            if (data.success) {
                alert(`User ${newStatus}d successfully`);
                location.reload();
            } else {
                alert(data.message || "Failed to update user status. Check logs for details.");
            }
        } catch (error) {
            console.error("Error toggling status:", error);
            alert("An error occurred while updating user status.");
        }
    }
}

//For  all users, active users, inactive users



async function filterUsersByStatus() {
    const filterOption = document.getElementById("filterOption").value;
    const tableBody = document.getElementById("userTableBody");

    // Fetch all users
    const response = await fetch("fetch_all_users.php");
    const users = await response.json();

    // Filter users based on the selected option
    let filteredUsers;
    if (filterOption === "active") {
        filteredUsers = users.filter(user => user.is_active === 1);
    } else if (filterOption === "inactive") {
        filteredUsers = users.filter(user => user.is_active === 0);
    } else {
        filteredUsers = users;
    }

    // Clear the table
    tableBody.innerHTML = "";
    pagination.style.display = filteredUsers.length > 0 ? "none" : "block";

    // Populate the table with filtered users
    if (filteredUsers.length > 0) {
        filteredUsers.forEach((user, index) => {
            const row = document.createElement("tr");
            row.innerHTML = `
            <td>
                <input type='checkbox' class='user-checkbox' data-userid='${user.id}' onclick='updateBulkActionControls()'>
            </td>
                 <td>${index + 1}</td>
                <td>${user.id}</td>
                <td>${user.name}</td>
                <td>${user.username}</td>
                <td>${user.email}</td>
                <td><img src="${user.image}" class="user-image"></td>
                <td class="actions">
                    <button class='edit' onclick='editUser(${user.id}, "${user.name}", "${user.username}", "${user.email}", "${user.image}")'>
                        <i class='fa-solid fa-pen-to-square'></i>
                    </button>
                    <button class='delete' onclick='confirmDeleteUser(${user.id})'>
                        <i class='fa-solid fa-trash'></i>
                    </button>
                    <button class='status-toggle ${user.is_active ? "on" : "off"}' onclick='toggleUserStatus(${user.id}, "${user.username}", ${user.is_active})'>
                        <i class='fa-solid ${user.is_active ? "fa-toggle-on" : "fa-toggle-off"}'></i>
                    </button>
                </td>
            `;
            tableBody.appendChild(row);
        });
    } else {
        const noDataRow = document.createElement("tr");
        noDataRow.innerHTML = `<td colspan="7" style="text-align: center;">No users found</td>`;
        tableBody.appendChild(noDataRow);
    }
}

function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.user-checkbox');
    const isChecked = selectAllCheckbox.checked;

    checkboxes.forEach(checkbox => {
        checkbox.checked = isChecked;
        const row = checkbox.closest('tr');
        if (row) {
            row.classList.toggle('selected-row', isChecked);
        }
    });

    updateBulkActionControls();
}

function updateBulkActionControls() {
    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    const bulkActionControls = document.getElementById('bulkActionControls');
    const bulkToggleBtn = document.getElementById('bulkToggleBtn');
    const selectedCount = checkboxes.length;
    
    if (selectedCount > 0) {
        bulkActionControls.style.display = 'block';
        
        // Check if all selected users have the same status
        const allActive = Array.from(checkboxes)
            .every(checkbox => checkbox.dataset.status === '1');
        const allInactive = Array.from(checkboxes)
            .every(checkbox => checkbox.dataset.status === '0');
        
        // Update toggle button text and icon based on selection
        if (allActive) {
            bulkToggleBtn.innerHTML = '<i class="fa-solid fa-toggle-off"></i> Deactivate Selected';
            bulkToggleBtn.classList.remove('btn-success');
            bulkToggleBtn.classList.add('btn-secondary');
        } else if (allInactive) {
            bulkToggleBtn.innerHTML = '<i class="fa-solid fa-toggle-on"></i> Activate Selected';
            bulkToggleBtn.classList.remove('btn-secondary');
            bulkToggleBtn.classList.add('btn-success');
        } else {
            bulkToggleBtn.innerHTML = '<i class="fa-solid fa-toggle-on"></i> Toggle Status';
            bulkToggleBtn.classList.remove('btn-success', 'btn-secondary');
            bulkToggleBtn.classList.add('btn-primary');
        }

        // Update selection counter if it exists
        const counter = document.getElementById('selectedCounter');
        if (counter) {
            counter.textContent = `${selectedCount} user${selectedCount > 1 ? 's' : ''} selected`;
        }
    } else {
        bulkActionControls.style.display = 'none';
    }
}

async function bulkDelete() {
    const selectedUsers = getSelectedUserIds();
    if (!selectedUsers.length) return;

    if (confirm(`Are you sure you want to delete ${selectedUsers.length} selected users?`)) {
        try {
            const response = await fetch('bulk_delete_users.php', {
                method: 'POST',
                body: JSON.stringify({ userIds: selectedUsers }),
                headers: { 'Content-Type': 'application/json' }
            });

            const result = await response.json();
            if (result.success) {
                alert('Selected users deleted successfully');
                location.reload();
            } else {
                alert(result.message || 'Failed to delete users');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while deleting users');
        }
    }
}

async function bulkToggleStatus() {
    const selectedUsers = getSelectedUserIds();
    if (!selectedUsers.length) return;

    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    const allActive = Array.from(checkboxes).every(checkbox => {
        const row = checkbox.closest('tr');
        const statusBtn = row.querySelector('.status-toggle');
        return statusBtn.classList.contains('on');
    });

    const newStatus = allActive ? 'inactive' : 'active';
    
    if (confirm(`Are you sure you want to ${newStatus} ${selectedUsers.length} selected users?`)) {
        try {
            const response = await fetch('bulk_toggle_status.php', {
                method: 'POST',
                body: JSON.stringify({
                    userIds: selectedUsers,
                    status: newStatus
                }),
                headers: { 'Content-Type': 'application/json' }
            });

            const result = await response.json();
            if (result.success) {
                alert(`Selected users ${newStatus}d successfully`);
                location.reload();
            } else {
                alert(result.message || 'Failed to update user status');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while updating user status');
        }
    }
}

function getSelectedUserIds() {
    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    return Array.from(checkboxes).map(checkbox => checkbox.dataset.userid);
}

// Add this new function to handle the Enter key press
function handleSearchKeyPress(event) {
    if (event.key === 'Enter') {
        event.preventDefault(); // Prevent form submission if within a form
        filterUsers();
    }
}




 
    </script>

    <!--begin::Third Party Plugin(OverlayScrollbars)-->
    <script
      src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/browser/overlayscrollbars.browser.es6.min.js"
      integrity="sha256-/t1nN2956BT869E6H4V1dnt0X5pAQHPytli+1nTZm2Y="
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
          name: 'Research Papers',
          data: <?php echo json_encode($paperCounts); ?>
        }],
        chart: {
          height: 300,
          type: 'area',
          toolbar: {
            show: false
          }
        },
        colors: ['#0d6efd'],
        dataLabels: {
          enabled: true,
          formatter: function (val) {
            return Math.round(val);
          }
        },
        stroke: {
          curve: 'smooth'
        },
        xaxis: {
          categories: <?php echo json_encode(array_reverse($months)); ?>,
          title: {
            text: 'Last 6 Months'
          }
        },
        yaxis: {
          title: {
            text: 'Number of Papers'
          }
        },
        tooltip: {
          y: {
            formatter: function (val) {
              return Math.round(val) + " papers"
            }
          }
        }
      };

      const userGrowthOptions = {
        series: [{
          name: 'New Users',
          data: <?php echo json_encode($userCounts); ?>
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
            borderRadius: 4,
            horizontal: false,
          }
        },
        dataLabels: {
          enabled: true
        },
        colors: ['#20c997'],
        xaxis: {
          categories: <?php echo json_encode(array_reverse($months)); ?>,
          title: {
            text: 'Last 6 Months'
          }
        },
        yaxis: {
          title: {
            text: 'Number of Users'
          }
        },
        tooltip: {
          y: {
            formatter: function (val) {
              return Math.round(val) + " users"
            }
          }
        }
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

      // Update the acceptance rate display
      document.querySelector('.text-bg-success .inner h3').innerHTML = 
          '<?php echo $acceptance_rate; ?><sup class="fs-5">%</sup>';
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

   </body>
</html>
