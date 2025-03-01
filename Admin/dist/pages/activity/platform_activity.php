<?php
session_start();
include '../../../../config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../../login.php");
    exit();
}

// Get activity statistics
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'active_users' => $conn->query("SELECT COUNT(*) as count FROM users WHERE last_login > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['count'],
    'total_papers' => $conn->query("SELECT COUNT(*) as count FROM papers")->fetch_assoc()['count'],
    'pending_reviews' => $conn->query("SELECT COUNT(*) as count FROM papers WHERE status = 'pending'")->fetch_assoc()['count']
];

// Get recent activity
$recent_activity = $conn->query("
    SELECT 'paper' as type, title, created_at, user_id 
    FROM papers 
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    UNION ALL
    SELECT 'comment' as type, content as title, created_at, user_id 
    FROM comments 
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC 
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Platform Activity - Research Hub</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css">
</head>
<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <h1>Platform Activity Monitor</h1>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <!-- Statistics Cards -->
                    <div class="row">
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3><?php echo $stats['total_users']; ?></h3>
                                    <p>Total Users</p>
                                </div>
                                <div class="icon">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3><?php echo $stats['active_users']; ?></h3>
                                    <p>Active Users (24h)</p>
                                </div>
                                <div class="icon">
                                    <i class="bi bi-person-check"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h3><?php echo $stats['total_papers']; ?></h3>
                                    <p>Total Papers</p>
                                </div>
                                <div class="icon">
                                    <i class="bi bi-file-text"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-danger">
                                <div class="inner">
                                    <h3><?php echo $stats['pending_reviews']; ?></h3>
                                    <p>Pending Reviews</p>
                                </div>
                                <div class="icon">
                                    <i class="bi bi-clock"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Charts -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">User Activity (Last 7 Days)</h3>
                                </div>
                                <div class="card-body">
                                    <div id="userActivityChart"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Content Submissions</h3>
                                </div>
                                <div class="card-body">
                                    <div id="contentSubmissionsChart"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Activity</h3>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                                <div class="time-label">
                                    <span class="bg-red">
                                        <?php echo date('d M Y', strtotime($activity['created_at'])); ?>
                                    </span>
                                </div>
                                <div>
                                    <i class="fas fa-<?php echo $activity['type'] == 'paper' ? 'file' : 'comment'; ?> bg-blue"></i>
                                    <div class="timeline-item">
                                        <span class="time">
                                            <i class="fas fa-clock"></i> 
                                            <?php echo date('H:i', strtotime($activity['created_at'])); ?>
                                        </span>
                                        <h3 class="timeline-header">
                                            <?php echo $activity['type'] == 'paper' ? 'New Paper Submitted' : 'New Comment'; ?>
                                        </h3>
                                        <div class="timeline-body">
                                            <?php echo htmlspecialchars($activity['title']); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <?php include '../includes/footer.php'; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js"></script>
    <script>
    // User Activity Chart
    var userActivityOptions = {
        series: [{
            name: 'Active Users',
            data: [30, 40, 35, 50, 49, 60, 70]
        }],
        chart: {
            type: 'line',
            height: 350
        },
        xaxis: {
            categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
        }
    };
    var userActivityChart = new ApexCharts(document.querySelector("#userActivityChart"), userActivityOptions);
    userActivityChart.render();

    // Content Submissions Chart
    var contentSubmissionsOptions = {
        series: [{
            name: 'Papers',
            data: [44, 55, 57, 56, 61, 58, 63]
        }],
        chart: {
            type: 'bar',
            height: 350
        },
        xaxis: {
            categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
        }
    };
    var contentSubmissionsChart = new ApexCharts(document.querySelector("#contentSubmissionsChart"), contentSubmissionsOptions);
    contentSubmissionsChart.render();
    </script>
</body>
</html> 