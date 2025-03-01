<?php
session_start();
include 'config.php';
require_once 'vendor/autoload.php'; // Assuming you'll use Composer for HTTP client
use GuzzleHttp\Client;

// Function to search research papers using multiple external APIs
function searchResearchPapers($query) {
    $results = [];
    
    // Semantic Scholar Search
    try {
        $semanticClient = new Client(['base_uri' => 'https://api.semanticscholar.org/']);
        $semanticResponse = $semanticClient->request('GET', 'graph/v1/paper/search', [
            'query' => [
                'query' => $query,
                'limit' => 5,
                'fields' => 'title,abstract,url,authors,year,venue'
            ]
        ]);
        
        $semanticPapers = json_decode($semanticResponse->getBody(), true);
        
        $semanticResults = array_map(function($paper) {
            return [
                'title' => $paper['title'] ?? 'Untitled',
                'abstract' => $paper['abstract'] ?? 'No abstract available',
                'year' => is_numeric($paper['year'] ?? null) ? $paper['year'] : 0,
                'url' => $paper['url'] ?? '#',
                'authors' => !empty($paper['authors']) 
                    ? array_map(function($author) {
                        return ['name' => $author['name'] ?? 'Unknown'];
                    }, $paper['authors']) 
                    : [['name' => 'Unknown']],
                'source' => 'Semantic Scholar',
                'source_type' => 'external'
            ];
        }, $semanticPapers['data'] ?? []);
        
        $results = array_merge($results, $semanticResults);
    } catch (Exception $e) {
        error_log('Semantic Scholar API Error: ' . $e->getMessage());
    }
    
    // Crossref API Search
    try {
        $crossrefClient = new Client(['base_uri' => 'https://api.crossref.org/']);
        $crossrefResponse = $crossrefClient->request('GET', 'works', [
            'query' => [
                'query' => $query,
                'rows' => 5
            ]
        ]);
        
        $crossrefData = json_decode($crossrefResponse->getBody(), true);
        
        $crossrefResults = array_map(function($work) {
            // Safely extract year
            $year = 0;
            if (isset($work['published']['date-parts'][0][0]) && is_numeric($work['published']['date-parts'][0][0])) {
                $year = intval($work['published']['date-parts'][0][0]);
            }
            
            // Safely extract authors
            $authors = [];
            if (!empty($work['author'])) {
                foreach ($work['author'] as $author) {
                    $authorName = trim(
                        ($author['given'] ?? '') . ' ' . 
                        ($author['family'] ?? '')
                    );
                    $authors[] = ['name' => $authorName ?: 'Unknown'];
                }
            }
            
            return [
                'title' => $work['title'][0] ?? 'Untitled',
                'abstract' => $work['abstract'] ?? 'No abstract available',
                'year' => $year,
                'url' => $work['URL'] ?? '#',
                'authors' => $authors ?: [['name' => 'Unknown']],
                'source' => 'Crossref',
                'source_type' => 'external'
            ];
        }, $crossrefData['message']['items'] ?? []);
        
        $results = array_merge($results, $crossrefResults);
    } catch (Exception $e) {
        error_log('Crossref API Error: ' . $e->getMessage());
    }
    
    // IEEE Xplore Digital Library Search
    try {
        // Note: IEEE Xplore requires a free API key
        $ieeeClient = new Client(['base_uri' => 'https://ieeexploreapi.ieee.org/api/v1/']);
        $ieeeResponse = $ieeeClient->request('GET', 'search', [
            'query' => [
                'querytext' => $query,
                'max_records' => 5,
                'apikey' => 'YOUR_IEEE_API_KEY' // Replace with actual API key
            ]
        ]);
        
        $ieeeData = json_decode($ieeeResponse->getBody(), true);
        
        $ieeeResults = array_map(function($paper) {
            return [
                'title' => $paper['title'] ?? 'Untitled',
                'abstract' => $paper['abstract'] ?? 'No abstract available',
                'year' => $paper['publication_year'] ?? 0,
                'url' => $paper['pdf_url'] ?? '#',
                'authors' => !empty($paper['authors']) 
                    ? array_map(function($author) {
                        return ['name' => $author['name'] ?? 'Unknown'];
                    }, $paper['authors']) 
                    : [['name' => 'Unknown']],
                'source' => 'IEEE Xplore',
                'source_type' => 'external'
            ];
        }, $ieeeData['articles'] ?? []);
        
        $results = array_merge($results, $ieeeResults);
    } catch (Exception $e) {
        error_log('IEEE Xplore API Error: ' . $e->getMessage());
    }
    
    // Directory of Open Access Journals (DOAJ) Search
    try {
        $doajClient = new Client(['base_uri' => 'https://doaj.org/api/']);
        $doajResponse = $doajClient->request('GET', 'search/articles/' . urlencode($query), [
            'query' => [
                'pageSize' => 5,
                'page' => 1
            ]
        ]);
        
        $doajData = json_decode($doajResponse->getBody(), true);
        
        $doajResults = array_map(function($article) {
            $authors = [];
            if (!empty($article['bibjson']['author'])) {
                foreach ($article['bibjson']['author'] as $author) {
                    $authors[] = ['name' => $author['name'] ?? 'Unknown'];
                }
            }
            
            return [
                'title' => $article['bibjson']['title'] ?? 'Untitled',
                'abstract' => $article['bibjson']['abstract'] ?? 'No abstract available',
                'year' => $article['bibjson']['year'] ?? 0,
                'url' => $article['bibjson']['link'][0]['url'] ?? '#',
                'authors' => $authors ?: [['name' => 'Unknown']],
                'source' => 'Directory of Open Access Journals',
                'source_type' => 'external'
            ];
        }, $doajData['results'] ?? []);
        
        $results = array_merge($results, $doajResults);
    } catch (Exception $e) {
        error_log('DOAJ API Error: ' . $e->getMessage());
    }
    
    // Open Access Repositories Search
    try {
        $oaClient = new Client(['base_uri' => 'https://api.oadoi.org/v2/']);
        $oaResponse = $oaClient->request('GET', 'works', [
            'query' => [
                'query' => $query,
                'limit' => 5
            ]
        ]);
        
        $oaData = json_decode($oaResponse->getBody(), true);
        
        $oaResults = array_map(function($work) {
            $authors = [];
            if (!empty($work['author'])) {
                foreach ($work['author'] as $author) {
                    $authors[] = ['name' => $author['family'] . ', ' . $author['given'] ?? 'Unknown'];
                }
            }
            
            return [
                'title' => $work['title'] ?? 'Untitled',
                'abstract' => 'No abstract available',
                'year' => $work['published-print']['date-parts'][0][0] ?? 0,
                'url' => $work['URL'] ?? '#',
                'authors' => $authors ?: [['name' => 'Unknown']],
                'source' => 'Open Access Repositories',
                'source_type' => 'external'
            ];
        }, $oaData['results'] ?? []);
        
        $results = array_merge($results, $oaResults);
    } catch (Exception $e) {
        error_log('Open Access Repositories API Error: ' . $e->getMessage());
    }
    
    // Optional: Sort combined results, with robust type checking
    usort($results, function($a, $b) {
        $yearA = is_numeric($a['year']) ? intval($a['year']) : 0;
        $yearB = is_numeric($b['year']) ? intval($b['year']) : 0;
        return $yearB - $yearA;
    });
    
    return $results;
}

function searchLocalPapers($query) {
    global $conn; // Assuming you have a database connection from config.php
    
    $query = mysqli_real_escape_string($conn, $query);
    
    $sql = "SELECT * FROM research_uploads WHERE 
            (title LIKE '%$query%' OR 
            description LIKE '%$query%') AND 
            status = 'active' 
            LIMIT 10";
    
    $result = mysqli_query($conn, $sql);
    
    $localResults = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $localResults[] = [
            'title' => $row['title'],
            'abstract' => $row['description'],
            'year' => date('Y', strtotime($row['uploaded_at'])),
            'url' => 'view.php?id=' . $row['id'], // Change URL to point to view.php with paper ID
            'authors' => [['name' => $row['uploaded_by']]], 
            'source' => 'Local Research Hub', // Explicitly mark as local source
            'source_type' => 'internal'
        ];
    }
    
    return $localResults;
}

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Optional: Add role-based access control if needed
$allowed_roles = ['admin', 'researcher', 'user'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: login.php');
    exit();
}
?>
<!doctype html>
<html lang="en">
  <!--begin::Head-->
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Research Hub | Intelligent Search</title>
    <!--begin::Primary Meta Tags-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="title" content="Research Hub | Intelligent Search" />
    <meta name="author" content="Research Hub" />
    <meta
      name="description"
      content="Research Hub Intelligence Search - Manage research papers, users, and analytics."
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

      <!--begin::App Main-->
      <main class="app-main">
        <?php
$searchResults = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_query'])) {
    $searchQuery = $_POST['search_query'];
    
    // Search local database
    $localResults = searchLocalPapers($searchQuery);
    
    // Search external APIs
    $externalResults = searchResearchPapers($searchQuery);
    
    // Combine results
    $searchResults = array_merge($localResults, $externalResults);
}
?>

<!-- Update search form -->
<div class="container-fluid">
    <div class="card card-outline card-primary shadow-sm">
        <div class="card-header bg-gradient-primary text-white">
            <h3 class="card-title d-flex align-items-center">
                <i class="bi bi-search me-2"></i> Intelligent Research Paper Search
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="position-relative">
                <div class="input-group input-group-lg mb-3">
                    <span class="input-group-text bg-light border-primary text-primary">
                        <i class="bi bi-book"></i>
                    </span>
                    <input type="text" name="search_query" class="form-control form-control-lg" 
                           placeholder="Enter research topic or keywords" 
                           aria-label="Research search" 
                           required>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-2"></i>Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($searchResults)): ?>
    <div class="card card-outline card-info shadow-sm">
        <div class="card-header bg-gradient-info text-white d-flex justify-content-between align-items-center">
            <h3 class="card-title d-flex align-items-center">
                <i class="bi bi-file-earmark-text me-2"></i> Search Results
            </h3>
            <div class="card-tools">
                <span class="badge bg-light text-primary me-2">
                    <i class="bi bi-database me-1"></i> Local: <?php echo count(array_filter($searchResults, function($result) { return $result['source_type'] === 'internal'; })); ?>
                </span>
                <span class="badge bg-light text-info">
                    <i class="bi bi-globe me-1"></i> External: <?php echo count(array_filter($searchResults, function($result) { return $result['source_type'] === 'external'; })); ?>
                </span>
            </div>
        </div>
        <div class="card-body">
            <div class="list-group list-group-flush">
                <?php foreach ($searchResults as $paper): ?>
                <div class="list-group-item list-group-item-action py-3 px-4 mb-2 rounded shadow-sm">
                    <div class="d-flex w-100 justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center">
                            <i class="<?php echo $paper['source_type'] === 'internal' ? 'bi bi-file-earmark-text text-success' : 'bi bi-globe text-info'; ?> me-2"></i>
                            <h5 class="mb-0">
                                <?php echo htmlspecialchars($paper['title']); ?>
                                <small class="ms-2 text-muted">
                                    [<?php echo htmlspecialchars($paper['source']); ?>]
                                </small>
                            </h5>
                        </div>
                        <small class="text-muted">
                            <i class="bi bi-calendar me-1"></i><?php echo htmlspecialchars($paper['year'] ?? 'N/A'); ?>
                        </small>
                    </div>
                    <p class="mb-2 text-muted">
                        <i class="bi bi-blockquote-left me-2"></i><?php echo htmlspecialchars(substr($paper['abstract'], 0, 250) . '...'); ?>
                    </p>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="bi bi-people me-1"></i>
                            <?php 
                            $authors = isset($paper['authors']) ? 
                                implode(', ', array_column($paper['authors'], 'name')) : 
                                'Unknown';
                            echo htmlspecialchars($authors); 
                            ?>
                        </small>
                        <?php if (isset($paper['url'])): ?>
                        <a href="<?php echo htmlspecialchars($paper['url']); ?>" 
                           target="_blank" 
                           class="btn btn-sm <?php echo $paper['source_type'] === 'internal' ? 'btn-outline-success' : 'btn-outline-info'; ?> d-flex align-items-center">
                            <i class="bi <?php echo $paper['source_type'] === 'internal' ? 'bi-file-earmark-text' : 'bi-globe'; ?> me-1"></i>
                            View <?php echo $paper['source_type'] === 'internal' ? 'Local' : 'External'; ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
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
