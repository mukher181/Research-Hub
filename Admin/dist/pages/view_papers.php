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
  </head>
  <!--end::Head-->
  <!--begin::Body-->
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <!--begin::App Wrapper-->
    <div class="app-wrapper">
        <?php require_once "topnav.php";?>
        <?php require_once "sidenav.php";?>
        
        <main class="app-main">
            <div class="app-content">
                <div class="container">
                    <div class="papers-container">
                        <h1 class="section-title">Research Papers</h1>
                        <style>
                            form {
                                display: flex;
                                justify-content: center;
                                margin-bottom: 20px;
                            }

                            input[type="text"] {
                                padding: 10px;
                                border: 1px solid #ccc;
                                border-radius: 4px;
                                width: 300px;
                            }

                            button {
                                padding: 10px 15px;
                                border: none;
                                border-radius: 4px;
                                background-color: #007BFF;
                                color: white;
                                cursor: pointer;
                                margin-left: 10px;
                            }

                            button:hover {
                                background-color: #0056b3;
                            }
                        </style>
                        <form method="GET" action="">
                            <input type="text" name="search" placeholder="Search by title" value="<?php echo $_GET['search'] ?? ''; ?>" />
                            <button type="submit">Search</button>
                        </form>
                        
                        <div class="papers-grid">
                            <?php
                            // Fetch active papers without stats
                            $query = "SELECT * FROM research_uploads 
                                     WHERE status = 'active' 
                                     ORDER BY uploaded_at DESC";
                            if (isset($_GET['search'])) {
                                $search = $_GET['search'];
                                $query = "SELECT * FROM research_uploads 
                                         WHERE status = 'active' AND title LIKE '%$search%' 
                                         ORDER BY uploaded_at DESC";
                            }
                            $result = $conn->query($query);

                            if ($result && $result->num_rows > 0) {
                                while ($paper = $result->fetch_assoc()) {
                                    ?>
                                    <div class="paper-card" onclick="window.location.href='paper_detail.php?id=<?php echo $paper['id']; ?>'">
                                        <div class="paper-icon">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div class="paper-content">
                                            <h3 class="paper-title"><?php echo htmlspecialchars($paper['title']); ?></h3>
                                            <div class="paper-stats">
                                                <span class="stat-item">
                                                <span>0 </span>
                                                    <i class="bi bi-download"></i>
                                                    
                                                </span>
                                                <span class="stat-item">
                                                <span>0 </span>
                                                    <i class="bi bi-heart"></i>
                                                    
                                                </span>
                                            </div>
                                        </div>
                                        <div class="paper-actions">
                                            <a href="#" class="action-btn download-btn" onclick="event.stopPropagation();">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <?php
                                }
                            } else {
                                echo '<div class="no-papers">No research papers available at the moment.</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
        .papers-container {
            padding: 2rem 0;
        }

        .section-title {
            font-size: 2rem;
            color: #1a237e;
            margin-bottom: 2rem;
            font-weight: 600;
        }

        .papers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            padding: 1rem 0;
        }

        .paper-card {
            background: #ffffff;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid #e0e0e0;
        }

        .paper-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(26, 35, 126, 0.12);
            border-color: #1a237e;
        }

        .paper-icon {
            font-size: 2rem;
            color: #1a237e;
            background: #e8eaf6;
            padding: 1rem;
            border-radius: 8px;
            transition: background-color 0.2s;
        }

        .paper-card:hover .paper-icon {
            background: #c5cae9;
        }

        .paper-content {
            flex: 1;
        }

        .paper-title {
            font-size: 1.1rem;
            color: #283593;
            margin-bottom: 0.5rem;
            font-weight: 500;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .paper-stats {
            display: flex;
            gap: 1rem;
            color: #5c6bc0;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.9rem;
        }

        .action-btn {
            color: #1a237e;
            background: #e8eaf6;
            padding: 0.5rem;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .action-btn:hover {
            background: #c5cae9;
            color: #283593;
        }

        .download-btn {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .no-papers {
            grid-column: 1 / -1;
            text-align: center;
            padding: 2rem;
            color: #5c6bc0;
            background: #e8eaf6;
            border-radius: 8px;
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            .papers-grid {
                grid-template-columns: 1fr;
            }

            .paper-card {
                padding: 1rem;
            }
        }
    </style>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
    <!-- Add this modal at the bottom of the body tag -->

  </body>
  <!--end::Body-->
</html>
