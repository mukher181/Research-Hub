<?php
session_start();
include 'config.php';

// Check if user is logged in and is a researcher
if (!isset($_SESSION['username']) || $_SESSION['role'] === 'user') {
    header("Location: login.php");
    exit();
}

$current_user = $_SESSION['username'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (isset($data['action'])) {
        header('Content-Type: application/json');
        
        switch($data['action']) {
            case 'get_researchers':
                // Get all researchers except current user
                $query = "SELECT username, Image FROM users WHERE role = 'researcher' AND username != ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("s", $current_user);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $researchers = [];
                while ($row = $result->fetch_assoc()) {
                    $researchers[] = $row;
                }
                
                echo json_encode(['researchers' => $researchers]);
                break;
        }
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
  <!--begin::Head-->
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Research Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .researcher-card {
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .researcher-card img {
            width: 50px;
            height: 50px;
            object-fit: cover;
        }
        .researcher-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .btn-primary {
            background-color: #c43b68;
            border-color: #c43b68;
            transition: background-color 0.2s ease;
        }
        .btn-primary:hover {
            background-color: #a62b50;
            border-color: #a62b50;
        }
        #searchResearchers {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        .researcher-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s;
        }
        .researcher-item:hover {
            background-color: #f5f5f5;
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
    <div class="container mt-4">
        <h2>Research Chat</h2>
        <p>Select a researcher to chat with</p>
        <input type="text" id="searchResearchers" placeholder="Search Researchers..." />
        <div id="researcherList" class="mt-4">
            <!-- Researchers will be loaded here -->
        </div>
    </div>
    </main>
      <!--end::App Main-->
      <!--begin::Footer-->
     
      <!--end::Footer-->
    </div>
    <!--end::App Wrapper-->
    <!--begin::Script-->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function loadResearchers() {
            $.ajax({
                url: 'manage_chat_access.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    action: 'get_researchers'
                }),
                success: function(response) {
                    const researcherList = $('#researcherList');
                    researcherList.empty();
                    
                    response.researchers.forEach(researcher => {
                        researcherList.append(`
                            <div class="researcher-card" data-username="${researcher.username}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="researcher-info">
                                        <img src="${researcher.Image || 'default_avatar.png'}" class="rounded-circle">
                                        <span>${researcher.username}</span>
                                    </div>
                                    <button class="btn btn-primary" onclick="openChat('${researcher.username}')">
                                        Chat
                                    </button>
                                </div>
                            </div>
                        `);
                    });
                }
            });
        }

        function openChat(researcher) {
            // Clear any existing last chatted user to ensure the new user is selected
            localStorage.removeItem('lastChattedUser');
            
            // Redirect to research_chat.php with the selected researcher
            window.location.href = `research_chat.php?chat_with=${researcher}`;
        }

        $(document).ready(function() {
            loadResearchers();
            $('#searchResearchers').on('input', function() {
                const query = $(this).val().toLowerCase();
                $('.researcher-card').each(function() {
                    const username = $(this).data('username').toLowerCase();
                    $(this).toggle(username.includes(query));
                });
            });
        });
    </script>

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
      integrity="sha256-+vh8GkaU7C9/wbSLIcwq82tTf44aOHA8HlBMwRI8="
      crossorigin="anonymous"
    ></script>
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
</html>