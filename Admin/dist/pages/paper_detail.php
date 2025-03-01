<?php
session_start();
include 'config.php';

// Check if user is not logged in
if (!isset($_SESSION['username']) && !isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

// Get paper ID from URL
$paper_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch paper details
$query = "SELECT * FROM research_uploads 
         WHERE id = ? AND status = 'active'";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $paper_id);
$stmt->execute();
$result = $stmt->get_result();
$paper = $result->fetch_assoc();

// If paper not found or not active, redirect to view papers
if (!$paper) {
    header("Location: view_papers.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($paper['title']); ?> - Research Hub</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --background-color: #f8f9fa;
            --text-color: #2c3e50;
            --border-radius: 12px;
        }

        body {
            background-color: var(--background-color);
            color: var(--text-color);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .paper-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .paper-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .paper-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 3rem 2rem;
            position: relative;
        }

        .paper-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            line-height: 1.3;
        }

        .meta-info {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            margin-top: 2rem;
            font-size: 0.95rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .meta-item i {
            font-size: 1.2rem;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            padding: 1.5rem;
            background: white;
            margin-top: -2rem;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1;
        }

        .stat-card {
            padding: 1rem;
            text-align: center;
            border-radius: 8px;
            background: var(--background-color);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .paper-content {
            padding: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .paper-description {
            line-height: 1.8;
            color: #444;
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn-action {
            padding: 1rem 2rem;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s;
            text-decoration: none;
            color: white;
        }

        .btn-view {
            background: var(--secondary-color);
        }

        .btn-view:hover {
            background: #2980b9;
            color: white;
        }

        .btn-download {
            background: var(--accent-color);
        }

        .btn-download:hover {
            background: #c0392b;
            color: white;
        }

        .back-link {
            position: fixed;
            top: 2rem;
            left: 2rem;
            background: white;
            color: var(--primary-color);
            padding: 0.8rem;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            z-index: 10;
        }

        .back-link:hover {
            transform: translateX(-3px);
            color: var(--secondary-color);
        }

        @media (max-width: 768px) {
            .paper-title {
                font-size: 2rem;
            }

            .meta-info {
                gap: 1rem;
            }

            .paper-container {
                margin: 1rem;
            }

            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }

            .back-link {
                top: 1rem;
                left: 1rem;
            }
        }
    </style>
</head>
<body>
    <a href="view_papers.php" class="back-link">
        <i class="bi bi-arrow-left"></i>
    </a>

    <div class="paper-container">
        <div class="paper-card">
            <div class="paper-header">
                <h1 class="paper-title"><?php echo htmlspecialchars($paper['title']); ?></h1>
                <div class="meta-info">
                    <div class="meta-item">
                        <i class="bi bi-person-circle"></i>
                        <span><?php echo htmlspecialchars($paper['uploaded_by']); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="bi bi-calendar-event"></i>
                        <span><?php echo date('F j, Y', strtotime($paper['uploaded_at'])); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Research Paper</span>
                    </div>
                </div>
            </div>

            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-value">0</div>
                    <div class="stat-label">
                        <i class="bi bi-download"></i> Downloads
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">0</div>
                    <div class="stat-label">
                        <i class="bi bi-heart"></i> Likes
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo date('Y', strtotime($paper['uploaded_at'])); ?></div>
                    <div class="stat-label">
                        <i class="bi bi-calendar-check"></i> Published
                    </div>
                </div>
            </div>

            <div class="paper-content">
                <div class="section-title">Abstract</div>
                <div class="paper-description">
                    <?php echo nl2br(htmlspecialchars($paper['description'])); ?>
                </div>

                <div class="action-buttons">
                    <a href="view.php?id=<?php echo $paper['id']; ?>" class="btn-action btn-view">
                        <i class="bi bi-eye"></i>
                        View Document
                    </a>
                    <a href="download_paper.php?id=<?php echo $paper['id']; ?>" class="btn-action btn-download">
                        <i class="bi bi-download"></i>
                        Download Paper
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>