<?php

session_start();
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

require_once 'config.php';

// Role check — only users can access this page
$role_check = $conn->query("SELECT role FROM users WHERE email = '" . $conn->real_escape_string($_SESSION['email']) . "'");
$role_data = $role_check->fetch_assoc();
if (!$role_data || $role_data['role'] !== 'user') {
    header("Location: admin_page.php");
    exit();
}

// Get user's reports
$user_email = $_SESSION['email'];
$user_id_result = $conn->query("SELECT id FROM users WHERE email = '" . $conn->real_escape_string($user_email) . "'");
$user_id_data = $user_id_result->fetch_assoc();
$user_id = $user_id_data['id'];

// Get filter options
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query with filters
$query = "SELECT * FROM reports WHERE user_email = '" . $conn->real_escape_string($user_email) . "'";
if ($status_filter !== 'all') {
    $query .= " AND status = '" . $conn->real_escape_string($status_filter) . "'";
}
$query .= " ORDER BY created_at DESC";

$result = $conn->query($query);

// Mark any reports with admin replies as seen (notification cleared)
$conn->query("UPDATE reports SET user_notif_seen = 1 WHERE user_email = '" . $conn->real_escape_string($user_email) . "' AND admin_reply IS NOT NULL AND admin_reply != ''");

// Also mark in user_notifications table
$conn->query("UPDATE user_notifications SET is_read = 1 WHERE user_id = $user_id");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .highlighted {
            border: 3px solid #27ae60 !important;
            box-shadow: 0 0 20px rgba(39, 174, 96, 0.5) !important;
            transition: border 0.3s ease;
        }
    </style>
</head>

<body>
<?php $active_page = 'viewreports'; require_once 'menu_user.php'; ?>

<div class="main-content">
    <div class="header">
        <h1>My Reports</h1>
        <p class="subtitle">View all your submitted reports and admin responses</p>
    </div>
    
    <!-- Filters -->
    <div class="filter-section">
        <form method="GET" class="filter-form" style="display:flex; flex-direction:row; align-items:center; gap:20px;">
            <div class="filter-group" style="display:flex; flex-direction:row; align-items:center; gap:8px;">
                <label style="margin:0; line-height:1; vertical-align:middle;">Status:</label>
                <select name="status" onchange="this.form.submit()" style="vertical-align:middle; margin:0;">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="in-progress" <?= $status_filter === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="resolved" <?= $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                </select>
            </div>
            <div class="search-group" style="display:flex; flex-direction:row; align-items:center; gap:8px;">
                <label style="margin:0; line-height:1; vertical-align:middle;">Search:</label>
                <input type="text" id="reportSearch" placeholder="Search reports by title or content..." style="vertical-align:middle; margin:0;">
            </div>
        </form>
    </div>
    
    <div class="reports-container">
        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="report-card" id="report-<?= $row['id']; ?>">
                    <div class="report-header">
                        <h3><?= htmlspecialchars($row['report_title']); ?></h3>
                        <span class="status-badge status-<?= $row['status']; ?>">
                            <?= ucfirst($row['status']); ?>
                        </span>
                    </div>
                    
                    <div class="report-content">
                        <h4 class="content-label"><i class="fa-solid fa-file-lines"></i> Your Report:</h4>
                        <p><?= nl2br(htmlspecialchars($row['report_content'])); ?></p>
                    </div>
                    
                    <?php if (!empty($row['admin_reply'])): ?>
                        <div class="admin-reply-box">
                            <div class="reply-header-user">
                                <i class="fa-solid fa-user-shield"></i>
                                <strong>Admin Response</strong>
                                <?php if (!empty($row['admin_name'])): ?>
                                    <span class="reply-from">from <?= htmlspecialchars($row['admin_name']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="reply-content">
                                <p><?= nl2br(htmlspecialchars($row['admin_reply'])); ?></p>
                            </div>
                            
                            <?php if (!empty($row['help_arrival'])): ?>
                                <div class="help-arrival-box">
                                    <i class="fa-solid fa-clock"></i>
                                    <div>
                                        <strong>Estimated Help Arrival:</strong>
                                        <p><?= htmlspecialchars($row['help_arrival']); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <p class="reply-timestamp">
                                <i class="fa-solid fa-calendar-check"></i>
                                Replied on: <?= date('M d, Y - h:i A', strtotime($row['replied_at'])); ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="no-reply-box">
                            <i class="fa-solid fa-hourglass-half"></i>
                            <p>Waiting for admin response...</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="report-footer">
                        <span class="report-date">
                            <i class="fa-solid fa-clock"></i>
                            Submitted: <?= date('M d, Y - h:i A', strtotime($row['created_at'])); ?>
                        </span>
                        <span class="report-id">ID: #<?= $row['id']; ?></span>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-inbox"></i>
                <h3><?php 
                    if ($status_filter !== 'all') {
                        echo "No Reports Found";
                    } else {
                        echo "No Reports Yet";
                    }
                ?></h3>
                <p><?php 
                    if ($status_filter !== 'all') {
                        echo "No matching reports found. Try adjusting your filter.";
                    } else {
                        echo "You haven't submitted any reports. Click the Report button to create one.";
                    }
                ?></p>
                <?php if ($status_filter === 'all'): ?>
                    <a href="submit_reports.php" class="btn-primary">
                        <i class="fa-solid fa-plus"></i> Create Report
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Handle scrolling/highlighting and search functionality
document.addEventListener('DOMContentLoaded', function() {
    // Existing scroll/highlight code
    var hash = window.location.hash;
    if (hash && hash.startsWith('#report-')) {
        var targetId = hash.substring(1);
        var targetElement = document.getElementById(targetId);
        if (targetElement) {
            targetElement.classList.add('highlighted');
            setTimeout(function() {
                targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 100);
        }
    }
    
    var urlParams = new URLSearchParams(window.location.search);
    var reportId = urlParams.get('seen');
    if (reportId) {
        var targetElement = document.getElementById('report-' + reportId);
        if (targetElement) {
            targetElement.classList.add('highlighted');
            setTimeout(function() {
                window.location.hash = 'report-' + reportId;
            }, 100);
        }
    }

    // Search functionality
    const searchInput = document.getElementById('reportSearch');
    const reportCards = document.querySelectorAll('.report-card');
    const reportsContainer = document.querySelector('.reports-container');
    const emptyState = document.querySelector('.empty-state');

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        
        let visibleCount = 0;
        
        reportCards.forEach(card => {
            const cardText = card.textContent.toLowerCase();
            const isVisible = cardText.includes(searchTerm);
            
            if (isVisible) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        // Show/hide empty state for search
        if (searchTerm && visibleCount === 0) {
            if (!document.querySelector('.no-reports-search')) {
                const noResults = document.createElement('div');
                noResults.className = 'no-reports-search empty-state';
                noResults.innerHTML = `
                    <i class="fa-solid fa-search"></i>
                    <h3>No Reports Found</h3>
                    <p>No reports match "${searchTerm}". Try different keywords.</p>
                `;
                reportsContainer.appendChild(noResults);
            }
        } else {
            const noResults = document.querySelector('.no-reports-search');
            if (noResults) {
                noResults.remove();
            }
        }
    });
});
</script>

</body>
</html>