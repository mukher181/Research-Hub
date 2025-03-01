<?php
session_start();
include '../../../../config.php';

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../../login.php");
    exit();
}

// Fetch pending content
$sql = "SELECT p.*, u.username 
        FROM papers p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.status = 'pending' 
        ORDER BY p.submission_date DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Content Moderation - Research Hub</title>
    <?php include '../includes/header.php'; ?>
</head>
<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <h1>Content Moderation</h1>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <!-- Pending Content Card -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Pending Research Papers</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>Submission Date</th>
                                        <th>Category</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($row['submission_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['category']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="reviewContent(<?php echo $row['id']; ?>)">
                                                Review
                                            </button>
                                            <button class="btn btn-sm btn-success" onclick="approveContent(<?php echo $row['id']; ?>)">
                                                Approve
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="rejectContent(<?php echo $row['id']; ?>)">
                                                Reject
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Reported Content Card -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title">Reported Content</h3>
                        </div>
                        <div class="card-body">
                            <!-- Add reported content table here -->
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Content Review Modal -->
        <div class="modal fade" id="reviewModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Review Content</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <!-- Content details will be loaded here -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-success" id="approveBtn">Approve</button>
                        <button type="button" class="btn btn-danger" id="rejectBtn">Reject</button>
                    </div>
                </div>
            </div>
        </div>

        <?php include '../includes/footer.php'; ?>
    </div>

    <script>
    function reviewContent(id) {
        // Load content details into modal
        $('#reviewModal').modal('show');
    }

    function approveContent(id) {
        if (confirm('Are you sure you want to approve this content?')) {
            fetch('approve_content.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    }

    function rejectContent(id) {
        if (confirm('Are you sure you want to reject this content?')) {
            fetch('reject_content.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    }
    </script>
</body>
</html> 