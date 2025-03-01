<?php
session_start();
include 'config.php';

// Check if user is not logged in (check both username and email)
if (!isset($_SESSION['user_email']) && !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$showAlert = false;
$errors = [
    'name' => '',
    'username' => '',
    'email' => '', 
    'password' => '',
    'confirm_password' => '',
    'image' => '',
    'general' => ''
];

// Fetch current user details from database using either email or username
if (isset($_SESSION['user_email'])) {
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $_SESSION['user_email']);
} else {
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $_SESSION['username']);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $name = $user['Name'];
    $username = $user['Username']; 
    $email = $user['Email'];
    $currentImage = $user['Image'];
    
    // Set all session variables
    $_SESSION['username'] = $user['Username'];
    $_SESSION['user_email'] = $user['Email'];
    $_SESSION['profile_image'] = $user['Image'];
    $_SESSION['email'] = $user['Email'];
    $_SESSION['bio'] = $user['bio_description'] ?? '';
    $_SESSION['interests'] = $user['research_interests'] ?? '';
    $_SESSION['role'] = $user['role'] ?? 'Admin';
    $_SESSION['join_date'] = $user['join_date'] ?? 'Jan. 2024';
} else {
    // Handle the case where user is not found more gracefully
    $_SESSION = array(); // Clear all session variables
    session_destroy();
    header("Location: login.php?error=invalid_user");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate name
    if (empty($name)) {
        $errors['name'] = "Name is required.";
    } elseif (strlen($name) < 3 || strlen($name) > 50) {
        $errors['name'] = "Name must be between 3 and 50 characters.";
    } elseif (!preg_match("/^[a-zA-Z .]*$/", $name)) {
        $errors['name'] = "Only letters and white space allowed in Name.";
    }

    // Validate username
    if (empty($username)) {
        $errors['username'] = "Username is required.";
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $errors['username'] = "Username must be between 3 and 20 characters.";
    } elseif (!preg_match("/^[a-zA-Z0-9_]*$/", $username)) {
        $errors['username'] = "Only letters, digits, and underscores allowed in Username.";
    } elseif (!preg_match("/[a-zA-Z]/", $username) || !preg_match("/[0-9]/", $username)) {
        $errors['username'] = "Username must contain at least one letter and one digit.";
    } else {
        // Check if username exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND username != ?");
        $stmt->bind_param("ss", $username, $user['Username']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors['username'] = "Username is already taken.";
        }
    }

    // Validate email
    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND username != ?");
        $stmt->bind_param("ss", $email, $user['Username']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors['email'] = "Email is already taken.";
        }
    }

    // Validate password if provided
    if (!empty($new_password)) {
        if (strlen($new_password) < 8 || strlen($new_password) > 15) {
            $errors['password'] = "Password must be between 8 and 15 characters.";
        } elseif (!preg_match("/[A-Z]/", $new_password) || !preg_match("/[a-z]/", $new_password) || 
                  !preg_match("/[0-9]/", $new_password) || !preg_match("/[!@#$%^&*(),.?\":{}|<>]/", $new_password)) {
            $errors['password'] = "Password must include uppercase, lowercase, a digit, and a special character.";
        } elseif ($new_password !== $confirm_password) {
            $errors['confirm_password'] = "Passwords do not match.";
        }
    }

    // Handle image upload
    $imagePath = $currentImage;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $imageName = $_FILES['profile_image']['name'];
        $imageTmpName = $_FILES['profile_image']['tmp_name'];
        $uploadDir = 'uploads/';
        
        $fileExtension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
        $uniqueImageName = uniqid("user_", true) . '.' . $fileExtension;
        $imagePath = $uploadDir . $uniqueImageName;

        $allowedTypes = ['image/jpeg', 'image/png'];
        $fileType = mime_content_type($imageTmpName);

        if (!in_array($fileType, $allowedTypes) || !in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
            $errors['image'] = "Only JPG and PNG images are allowed.";
        } elseif ($_FILES['profile_image']['size'] > 500 * 1024) {
            $errors['image'] = "Image size should not exceed 500kb.";
        } elseif (!move_uploaded_file($imageTmpName, $imagePath)) {
            $errors['image'] = "Failed to upload the image.";
        }
    }

    // Update database if no errors
    if (array_filter($errors) === []) {
        $sql = "UPDATE users SET name=?, username=?, email=?, image=?";
        $params = [$name, $username, $email, $imagePath];
        $types = "ssss";
        
        if (!empty($new_password)) {
            $sql .= ", password=?";
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            $types .= "s";
        }
        
        $sql .= " WHERE username=?";
        $params[] = $user['Username']; // Correct variable
        $types .= "s";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $_SESSION['username'] = $username;
            $_SESSION['profile_image'] = $imagePath;
            $showAlert = true;
        } else {
            $errors['general'] = "Database error: " . $stmt->error;
        }
    }
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
                /* Main Content Styles */
                .main-content {
                    padding: 2rem;
                    background-color: #f8f9fa;
                    min-height: calc(100vh - 60px);
                }

                .container {
                    max-width: 800px;
                    margin: 0 auto;
                    background-color: #fff;
                    padding: 2rem;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }

                /* Heading Styles */
                h2 {
                    color: #2c3e50;
                    margin-bottom: 2rem;
                    font-weight: 600;
                    text-align: center;
                }

                /* Form Group Styles */
                .form-group {
                    margin-bottom: 1.5rem;
                }

                .form-group label {
                    display: block;
                    margin-bottom: 0.5rem;
                    color: #495057;
                    font-weight: 500;
                }

                /* Input Styles */
                .input-container input,
                .input-group input {
                    width: 100%;
                    padding: 0.75rem;
                    border: 1px solid #ced4da;
                    border-radius: 4px;
                    font-size: 1rem;
                    transition: border-color 0.15s ease-in-out;
                }

                .input-container input:focus,
                .input-group input:focus {
                    border-color: #80bdff;
                    outline: 0;
                    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
                }

                /* Profile Image Styles */
                .profile-pic {
                    text-align: center;
                    margin-bottom: 2rem;
                    position: relative;
                    width: 150px;
                    margin: 0 auto 2rem;
                }

                .profile-pic img {
                    width: 150px;
                    height: 150px;
                    border-radius: 50%;
                    object-fit: cover;
                    margin-bottom: 1rem;
                    border: 3px solid #fff;
                    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                }

                .profile-pic .image-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 150px;
                    height: 150px;
                    border-radius: 50%;
                    background: rgba(0, 0, 0, 0.5);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                    cursor: pointer;
                }

                .profile-pic:hover .image-overlay {
                    opacity: 1;
                }

                .profile-pic .image-overlay i {
                    color: white;
                    font-size: 24px;
                }

                .profile-pic input[type="file"] {
                    display: none;
                }

                .profile-pic .upload-hint {
                    font-size: 0.875rem;
                    color: #6c757d;
                    margin-top: 0.5rem;
                }

                /* Input Group Styles */
                .input-group {
                    position: relative;
                    display: flex;
                    align-items: center;
                }

                .input-group i {
                    position: absolute;
                    right: 1rem;
                    color: #6c757d;
                    cursor: pointer;
                }

                /* Button Styles */
                .buttons {
                    display: flex;
                    gap: 1rem;
                    justify-content: flex-end;
                    margin-top: 2rem;
                }

                .buttons button {
                    padding: 0.75rem 1.5rem;
                    font-size: 1rem;
                    font-weight: 500;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }

                .save-btn {
                    background-color: #0d6efd;
                    color: white;
                }

                .save-btn:hover {
                    background-color: #0b5ed7;
                }

                .cancel-btn {
                    background-color: #f8f9fa;
                    color: #495057;
                    border: 1px solid #ced4da;
                }

                .cancel-btn:hover {
                    background-color: #e9ecef;
                }

                /* Error Message Styles */
                p[style*="color: red"] {
                    font-size: 0.875rem;
                    margin-top: 0.25rem;
                    margin-bottom: 0;
                }

                /* Success Message Styles */
                p[style*="color: green"] {
                    background-color: #d4edda;
                    color: #155724;
                    padding: 1rem;
                    border-radius: 4px;
                    margin-bottom: 1.5rem;
                    text-align: center;
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
        <div class="main-content">
        

            <div class="container">
                <h2>Edit Profile</h2>
                
                <?php if ($showAlert): ?>
                    <p style="color: green; text-align: center;">Profile updated successfully!</p>
                <?php endif; ?>
                
                <form action="" method="POST" enctype="multipart/form-data">
                    <!-- Profile Image -->
                    <div class="profile-pic">
                        <img src="<?= htmlspecialchars($currentImage) ?>" alt="Profile Image" id="preview-image">
                        <label for="profile_image" class="image-overlay">
                            <i class="bi bi-camera-fill"></i>
                        </label>
                        <input type="file" name="profile_image" id="profile_image" accept="image/jpeg,image/png" onchange="previewImage(this)">
                        <div class="upload-hint">Click to upload new image (JPG/PNG, max 500KB)</div>
                        <p style="color: red;"><?= $errors['image'] ?></p>
                    </div>

                    <!-- Name -->
                    <div class="form-group">
                        <label for="name">Name</label>
                        <div class="input-container">
                            <input type="text" name="name" id="name" value="<?= htmlspecialchars($name) ?>" required>
                        </div>
                        <p style="color: red;"><?= $errors['name'] ?></p>
                    </div>

                    <!-- Username -->
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-container">
                            <input type="text" name="username" id="username" value="<?= htmlspecialchars($username) ?>" required>
                        </div>
                        <p style="color: red;"><?= $errors['username'] ?></p>
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label for="email">Email</label>
                        <div class="input-container">
                            <input type="email" name="email" id="email" value="<?= htmlspecialchars($email) ?>" required>
                        </div>
                        <p style="color: red;"><?= $errors['email'] ?></p>
                    </div>

                    <!-- New Password (optional) -->
                    <div class="form-group">
                        <label for="new_password">New Password (optional)</label>
                        <div class="input-group">
                            <input type="password" id="new_password" name="new_password" placeholder="New Password">
                            <i class="fa-regular fa-eye-slash" id="togglePassword"></i>
                        </div>
                        <p style="color: red;"><?= $errors['password'] ?></p>
                    </div>

                    <!-- Confirm Password -->
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password">
                            <i class="fa-regular fa-eye-slash" id="toggleConfirmPassword"></i>
                        </div>
                        <p style="color: red;"><?= $errors['confirm_password'] ?></p>
                    </div>

                    <!-- Buttons -->
                    <div class="buttons">
                        <button type="button" class="cancel-btn" onclick="window.location.href='index.php'">Go back</button>
                        <button type="submit" class="save-btn">Update</button>
                    </div>
                </form>
            </div>
        </div>

       
      </main>
      <!--end::App Main-->
      <!--begin::Footer-->
     
      <!--end::Footer-->
    </div>
    <!--end::App Wrapper-->
    <!--begin::Script-->
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
        series: [
          {
            name: 'Digital Goods',
            data: [28, 48, 40, 19, 86, 27, 90],
          },
          {
            name: 'Electronics',
            data: [65, 59, 80, 81, 56, 55, 40],
          },
        ],
        chart: {
          height: 300,
          type: 'area',
          toolbar: {
            show: false,
          },
        },
        legend: {
          show: false,
        },
        colors: ['#0d6efd', '#20c997'],
        dataLabels: {
          enabled: false,
        },
        stroke: {
          curve: 'smooth',
        },
        xaxis: {
          type: 'datetime',
          categories: [
            '2023-01-01',
            '2023-02-01',
            '2023-03-01',
            '2023-04-01',
            '2023-05-01',
            '2023-06-01',
            '2023-07-01',
          ],
        },
        tooltip: {
          x: {
            format: 'MMMM yyyy',
          },
        },
      };

      const sales_chart = new ApexCharts(
        document.querySelector('#revenue-chart'),
        sales_chart_options,
      );
      sales_chart.render();
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

    <script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                document.getElementById('preview-image').src = e.target.result;
            }
            
            reader.readAsDataURL(input.files[0]);
            
            // Check file size
            if (input.files[0].size > 500 * 1024) {
                alert('File size exceeds 500KB. Please choose a smaller file.');
                input.value = '';
            }
        }
    }
    </script>

  </body>
  <!--end::Body-->
</html>
