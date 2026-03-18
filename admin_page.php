<?php

session_start();
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

require_once 'config.php';

// Role check — only admins can access this page
$role_check = $conn->query("SELECT role FROM users WHERE email = '" . $conn->real_escape_string($_SESSION['email']) . "'");
$role_data = $role_check->fetch_assoc();
if (!$role_data || $role_data['role'] !== 'admin') {
    header("Location: user_page.php");
    exit();
}

// Total registered users (excluding admins)
$users_result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$total_users = $users_result->fetch_assoc()['total'];

// Total reports submitted by all users
$reports_result = $conn->query("SELECT COUNT(*) as total FROM reports");
$total_reports = $reports_result->fetch_assoc()['total'];

// Notifications = reports with NO admin reply yet (new/unanswered)
$notif_result = $conn->query("SELECT COUNT(*) as total FROM reports WHERE admin_reply IS NULL OR admin_reply = ''");
$notifications = $notif_result->fetch_assoc()['total'];

// Resolved = reports that have an admin reply
$resolved_result = $conn->query("SELECT COUNT(*) as total FROM reports WHERE admin_reply IS NOT NULL AND admin_reply != ''");
$resolved = $resolved_result->fetch_assoc()['total'];

// Pending = reports with NO admin reply yet (same as notifications)
$pending_result = $conn->query("SELECT COUNT(*) as total FROM reports WHERE admin_reply IS NULL OR admin_reply = ''");
$pending = $pending_result->fetch_assoc()['total'];

// Get critical and high priority reports (top 5 most recent)
$priority_reports = $conn->query("
    SELECT 
        r.id,
        r.report_title,
        r.priority,
        r.created_at,
        u.name as user_name,
        LEFT(r.report_content, 100) as summary
    FROM reports r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.priority IN ('critical', 'high') 
    AND (r.admin_reply IS NULL OR r.admin_reply = '')
    ORDER BY 
        FIELD(r.priority, 'critical', 'high') ASC,
        r.created_at DESC
    LIMIT 5
");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
<?php $active_page = 'dashboard'; require_once 'menu_admin.php'; ?>

<div class="main-content">
    <div class="header">
        <h1>Welcome, <span class="user-name"><?= htmlspecialchars($_SESSION['name']); ?></span>!</h1>
        <p class="subtitle">Admin Dashboard - Manage your system</p>
    </div>
    
    <div class="dashboard-cards">
        <div class="card">
            <div class="card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="card-info">
                <h3>Total Users</h3>
                <p class="card-number"><?= $total_users; ?></p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <i class="fa-solid fa-file-lines"></i>
            </div>
            <div class="card-info">
                <h3>Total Reports</h3>
                <p class="card-number"><?= $total_reports; ?></p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="fa-solid fa-bell"></i>
            </div>
            <div class="card-info">
                <h3>Notifications</h3>
                <p class="card-number" id="dashNotifCount"><?= $notifications; ?></p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <i class="fa-solid fa-check-circle"></i>
            </div>
            <div class="card-info">
                <h3>Resolved</h3>
                <p class="card-number"><?= $resolved; ?></p>
            </div>
        </div>

        <div class="card">
            <div class="card-icon" style="background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="card-info">
                <h3>Pending</h3>
                <p class="card-number"><?= $pending; ?></p>
            </div>
        </div>
    </div>

    <!-- Alert Notifications Section -->
    <div class="alerts-section">
        <div class="section-header">
            <h2><i class="fa-solid fa-exclamation-circle"></i> Priority Alerts</h2>
            <p class="section-subtitle">Critical and high-priority reports requiring attention</p>
        </div>

        <?php if ($priority_reports->num_rows > 0): ?>
            <div class="alerts-container">
                <?php while ($alert = $priority_reports->fetch_assoc()): ?>
                    <div class="card alert-<?= strtolower($alert['priority']); ?>">
                        <div class="card-icon" style="background: linear-gradient(135deg, <?= $alert['priority'] === 'critical' ? '#ff6b6b 0%, #ee5a52 100%' : '#ffa726 0%, #fb8c00 100%'; ?>);">
                            <i class="fa-solid fa-<?= $alert['priority'] === 'critical' ? 'fire' : 'exclamation-triangle'; ?>"></i>
                        </div>
                        <div class="card-info">
                            <h3><?= htmlspecialchars($alert['report_title']); ?></h3>
                            <p class="card-number"><?= strtoupper($alert['priority']); ?> - <?= date('M d, H:i', strtotime($alert['created_at'])); ?></p>
                            <p class="alert-summary"><?= htmlspecialchars($alert['summary']); ?>...</p>
                            <p class="alert-meta">
                                <strong>From:</strong> <?= htmlspecialchars($alert['user_name']); ?> 
                                <strong style="margin-left: 15px;">ID:</strong> #<?= $alert['id']; ?>
                            </p>
                            <a href="admin_view_reports.php?id=<?= $alert['id']; ?>" class="btn-view">
                                <i class="fa-solid fa-eye"></i> View Report
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-alerts">
                <i class="fa-solid fa-check-circle"></i>
                <p>No critical or high-priority alerts at the moment</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// If admin visited notifications page, clear the dashboard count
if (sessionStorage.getItem('admin_notif_cleared') === '1') {
    var el = document.getElementById('dashNotifCount');
    if (el) el.textContent = '0';
    sessionStorage.removeItem('admin_notif_cleared');
}
</script>
    
</body>
</html>