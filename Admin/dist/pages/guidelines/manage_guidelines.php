<?php
session_start();
include '../../../../config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../../login.php");
    exit();
}

// Fetch existing guidelines
$sql = "SELECT * FROM guidelines ORDER BY category, id";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Community Guidelines - Research Hub</title>
    <?php include '../includes/header.php'; ?>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
</head>
<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <h1>Community Guidelines Management</h1>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <!-- Guidelines List -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Guidelines</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addGuidelineModal">
                                    Add New Guideline
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="accordion" id="guidelinesAccordion">
                                <?php 
                                $current_category = '';
                                while ($row = $result->fetch_assoc()):
                                    if ($current_category != $row['category']):
                                        if ($current_category != '') echo '</div></div>';
                                        $current_category = $row['category'];
                                ?>
                                <div class="card">
                                    <div class="card-header">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link" type="button" data-toggle="collapse" 
                                                    data-target="#category<?php echo htmlspecialchars($row['id']); ?>">
                                                <?php echo htmlspecialchars($current_category); ?>
                                            </button>
                                        </h2>
                                    </div>
                                    <div id="category<?php echo htmlspecialchars($row['id']); ?>" class="collapse show">
                                        <div class="card-body">
                                <?php endif; ?>
                                
                                <div class="guideline-item mb-3">
                                    <h5><?php echo htmlspecialchars($row['title']); ?></h5>
                                    <p><?php echo htmlspecialchars($row['content']); ?></p>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-primary" onclick="editGuideline(<?php echo $row['id']; ?>)">
                                            Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteGuideline(<?php echo $row['id']; ?>)">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                                
                                <?php endwhile; 
                                if ($current_category != '') echo '</div></div>'; 
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Add Guideline Modal -->
        <div class="modal fade" id="addGuidelineModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Guideline</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <form id="addGuidelineForm">
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Category</label>
                                <input type="text" class="form-control" name="category" required>
                            </div>
                            <div class="form-group">
                                <label>Title</label>
                                <input type="text" class="form-control" name="title" required>
                            </div>
                            <div class="form-group">
                                <label>Content</label>
                                <div id="editor"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Guideline</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include '../includes/footer.php'; ?>
    </div>

    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
    var quill = new Quill('#editor', {
        theme: 'snow'
    });

    function editGuideline(id) {
        // Implementation for editing guideline
    }

    function deleteGuideline(id) {
        if (confirm('Are you sure you want to delete this guideline?')) {
            fetch('delete_guideline.php', {
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