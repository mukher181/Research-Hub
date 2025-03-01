<?php
session_start();
include 'config.php';

// Check if user is not logged in
if (!isset($_SESSION['username'])) {
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
    echo "No user data found.";
}
$role = $_SESSION['role'];

// Get total users count from database
$sql_count = "SELECT COUNT(*) as total_users FROM users";
$result_count = $conn->query($sql_count);
$total_users = 0;
if ($result_count) {
    $row_count = $result_count->fetch_assoc();
    $total_users = $row_count['total_users'];
}

// Add this new code to get total papers count
$sql_papers = "SELECT COUNT(*) as total_papers FROM research_uploads";
$result_papers = $conn->query($sql_papers);
$total_papers = 0;
if ($result_papers) {
    $row_papers = $result_papers->fetch_assoc();
    $total_papers = $row_papers['total_papers'];
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
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
            min-height: 100vh;
            margin: 0;
            
        }

        .container {
            background-color: #ffffff;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 480px;
            transition: transform 0.2s ease;
        }

        .container:hover {
            transform: translateY(-2px);
        }

        h2 {
            color: #2d3748;
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        form label {
            display: block;
            margin: 1rem 0 0.5rem;
            font-weight: 500;
            color: #4a5568;
            font-size: 0.95rem;
        }

        form input,
        form textarea,
        form select {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border: 1.5px solid black;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            box-sizing: border-box;
        }

        form input:focus,
        form textarea:focus,
        form select:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15);
        }

        form button {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(45deg, #3182ce, #4299e1);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 1.5rem;
        }

        form button:hover {
            background: linear-gradient(45deg, #2c5282, #3182ce);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(66, 153, 225, 0.15);
        }

        .error {
            color: #e53e3e;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .success, .error-message {
            text-align: center;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .success {
            display: flex;
            align-items: start;
            background-color: #ebf7ed;
            color: #1f513d;
            border: 1px solid #84d3a1;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
            line-height: 1.5;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .success i {
            font-size: 1.5rem;
            color: #34a853;
            flex-shrink: 0;
        }

        .error-message {
            background-color: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }

        /* File input styling */
        input[type="file"] {
            padding: 0.5rem;
            background-color: #edf2f7;
            cursor: pointer;
        }

        input[type="file"]::-webkit-file-upload-button {
            padding: 0.5rem 1rem;
            background: #4299e1;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-right: 1rem;
            transition: background 0.2s ease;
        }

        input[type="file"]::-webkit-file-upload-button:hover {
            background: #3182ce;
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
            
          </div>
          <!--end::Container-->
        </div>
        <!--end::App Content Header-->
        <!--begin::App Content-->
        <div class="container">
        <h2>Upload New Research</h2>

        <?php
        // Initialize variables for error messages and form data
        $titleErr = $descriptionErr = $fileNameErr = $fileErr = "";
        $title = $description = $fileName = $fileType = "";

        // Database connection
        $host = "localhost";
        $username = "root";     // Change this to your database username
        $password = "";         // Change this to your database password
        $database = "research";  // Change this to your database name

        $conn = new mysqli($host, $username, $password, $database);

        // Check connection
        if ($conn->connect_error) {
            die("<div class='error-message'>Database connection failed: " . $conn->connect_error . "</div>");
        }

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $isValid = true;

            // Validate Title
            if (empty($_POST['title'])) {
                $titleErr = "Title is required.";
                $isValid = false;
            } else {
                $title = trim($_POST['title']);
            }

            // Validate Description
            if (empty($_POST['description'])) {
                $descriptionErr = "Description is required.";
                $isValid = false;
            } else {
                $description = trim($_POST['description']);
            }

            // Validate File Name
            if (empty($_POST['fileName'])) {
                $fileNameErr = "File name is required.";
                $isValid = false;
            } else {
                $fileName = trim($_POST['fileName']);
            }

            // Validate File
            if (!isset($_FILES['file']) || $_FILES['file']['error'] != 0) {
                $fileErr = "Please upload a valid file.";
                $isValid = false;
            }

            // If all fields are valid, process the file upload
            if ($isValid) {
                $file = $_FILES['file'];
                $allowedTypes = ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $uploadDir = 'uploads/research_p/';
                $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
                $savedFileName = $fileName . '.' . $fileExt;
                $uploadFile = $uploadDir . $savedFileName;

                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                if (in_array($file['type'], $allowedTypes)) {
                    if (move_uploaded_file($file['tmp_name'], $uploadFile)) {
                        // Added status field to the query
                        $stmt = $conn->prepare("INSERT INTO research_uploads (title, description, file_name, file_path, uploaded_by, status) VALUES (?, ?, ?, ?, ?, 'inactive')");
                        $stmt->bind_param("sssss", $title, $description, $savedFileName, $uploadFile, $user_name);

                        if ($stmt->execute()) {
                            echo "<div class='success'>
                                    <i class='bi bi-check-circle-fill' style='margin-right: 8px;'></i>
                                    <div>
                                        <h4 style='margin: 0 0 8px 0;'>Paper Submitted Successfully!</h4>
                                        <p style='margin: 0;'>Your research paper is currently under review. Our team will carefully evaluate your submission and update its status once the review process is complete. Thank you for your patience.</p>
                                    </div>
                                  </div>";
                            // Add JavaScript redirect
                            echo "<script>
                                setTimeout(function() {
                                    window.location.href = 'total_papers.php';
                                }, 3000); // Increased to 3 seconds to allow users to read the message
                            </script>";
                        } else {
                            echo "<div class='error-message'>Error: Unable to upload file.</div>";
                        }
                        $stmt->close();
                    } else {
                        echo "<div class='error-message'>Error: Unable to upload file.</div>";
                    }
                } else {
                    echo "<div class='error-message'>Error: Invalid file type. Only Word documents (.doc and .docx) are allowed.</div>";
                }
            }
        }

        $conn->close();
        ?>

        <form id="uploadForm" method="POST" enctype="multipart/form-data">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($title) ?>">
            <div class="error"><?= $titleErr ?></div>

            <label for="description">Description</label>
            <textarea id="description" name="description" rows="6"><?= htmlspecialchars($description) ?></textarea>
            <div class="error"><?= $descriptionErr ?></div>

            <label for="fileName">File Name</label>
            <input type="text" id="fileName" name="fileName" value="<?= htmlspecialchars($fileName) ?>">
            <div class="error"><?= $fileNameErr ?></div>

            <label for="file">Choose File</label>
            <input type="file" id="file" name="file" accept=".doc,.docx">
            <div class="error"><?= $fileErr ?></div>

            <button type="submit">Upload</button>
        </form>
    </div>

        <!--end::App Content-->
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

  </body>
  <!--end::Body-->
</html>
