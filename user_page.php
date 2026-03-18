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

$user_email = $_SESSION['email'];

// Get user ID
$user_id_result = $conn->query("SELECT id FROM users WHERE email = '$user_email'");
$user_id = $user_id_result->fetch_assoc()['id'];

// My Reports: total reports submitted by this user
$total_result = $conn->query("SELECT COUNT(*) as total FROM reports WHERE user_email = '$user_email'");
$total_reports = $total_result->fetch_assoc()['total'];

// Notifications = unread user notifications
$notif_result = $conn->query("SELECT COUNT(*) as total FROM user_notifications WHERE user_id = $user_id AND is_read = FALSE");
$notifications = $notif_result->fetch_assoc()['total'];

// Completed = reports that have an admin reply
$completed_result = $conn->query("SELECT COUNT(*) as total FROM reports WHERE user_email = '$user_email' AND admin_reply IS NOT NULL AND admin_reply != ''");
$completed = $completed_result->fetch_assoc()['total'];

// Pending = reports with no admin reply yet
$pending_result = $conn->query("SELECT COUNT(*) as total FROM reports WHERE user_email = '$user_email' AND (admin_reply IS NULL OR admin_reply = '')");
$pending = $pending_result->fetch_assoc()['total'];

// Get recent notifications
$notifications_list = $conn->query("SELECT un.*, r.report_title FROM user_notifications un LEFT JOIN reports r ON un.report_id = r.id WHERE un.user_id = $user_id ORDER BY un.created_at DESC LIMIT 10");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
<?php $active_page = 'home'; require_once 'menu_user.php'; ?>

<div class="main-content">
    <div class="header">
        <h1>Welcome, <span class="user-name"><?= htmlspecialchars($_SESSION['name']); ?></span>!</h1>
        <p class="subtitle">Have a great day ahead</p>
    </div>
    
    <div class="dashboard-cards">
        <div class="card">
            <div class="card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="fa-solid fa-file-lines"></i>
            </div>
            <div class="card-info">
                <h3>My Reports</h3>
                <p class="card-number"><?= $total_reports; ?></p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <i class="fa-solid fa-bell"></i>
            </div>
            <div class="card-info">
                <h3>Notifications</h3>
                <p class="card-number" id="dashNotifCount"><?= $notifications; ?></p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="fa-solid fa-check-circle"></i>
            </div>
            <div class="card-info">
                <h3>Completed</h3>
                <p class="card-number"><?= $completed; ?></p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="card-info">
                <h3>Pending</h3>
                <p class="card-number"><?= $pending; ?></p>
            </div>
        </div>
    </div>

    <!-- Notifications Section -->
    <div class="alerts-section">
        <div class="section-header">
            <h2><i class="fa-solid fa-bell"></i> My Notifications</h2>
            <p class="section-subtitle">Updates on your reports</p>
        </div>

        <?php if ($notifications_list->num_rows > 0): ?>
            <div class="alerts-container">
                <?php while ($notif = $notifications_list->fetch_assoc()): ?>
                    <div class="card notification-card <?= $notif['is_read'] ? 'read' : 'unread'; ?>">
                        <div class="card-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                        <div class="card-info">
                            <h3>Report Update</h3>
                            <p class="card-number"><?= date('M d, H:i', strtotime($notif['created_at'])); ?></p>
                            <p class="alert-summary"><?= htmlspecialchars($notif['message']); ?></p>
                            <p class="alert-meta">
                                <strong>Report:</strong> <?= htmlspecialchars($notif['report_title']); ?>
                            </p>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-alerts">
                <i class="fa-solid fa-check-circle"></i>
                <p>No notifications at the moment</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// If user visited notifications page, clear the dashboard count
if (sessionStorage.getItem('user_notif_cleared') === '1') {
    var el = document.getElementById('dashNotifCount');
    if (el) el.textContent = '0';
    sessionStorage.removeItem('user_notif_cleared');
}
</script>
</body>
</html>