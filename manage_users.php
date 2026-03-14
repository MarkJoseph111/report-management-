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

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $delete_id = intval($_POST['user_id']);

    // Prevent admin from deleting themselves
    $self_check = $conn->query("SELECT id FROM users WHERE email = '" . $conn->real_escape_string($_SESSION['email']) . "'");
    $self_data = $self_check->fetch_assoc();

    if ($delete_id === intval($self_data['id'])) {
        $error_message = "You cannot delete your own account.";
    } else {
        // ON DELETE CASCADE handles reports automatically
        if ($conn->query("DELETE FROM users WHERE id = '$delete_id'")) {
            $success_message = "User and all their data have been permanently deleted.";
        } else {
            $error_message = "Error deleting user. Please try again.";
        }
    }
}

// Fetch all users (non-admin) with their report stats
$users_result = $conn->query("
    SELECT 
        u.id,
        u.name,
        u.email,
        u.role,
        u.created_at,
        COUNT(r.id) AS total_reports,
        SUM(CASE WHEN r.status = 'resolved' THEN 1 ELSE 0 END) AS resolved_reports,
        SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) AS pending_reports
    FROM users u
    LEFT JOIN reports r ON u.id = r.user_id
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
<?php $active_page = 'users'; require_once 'menu_admin.php'; ?>

<div class="main-content">
    <div class="header">
        <h1>Manage Users</h1>
        <p class="subtitle">View, monitor, and remove users from the system</p>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="success-alert">
            <i class="fa-solid fa-circle-check"></i>
            <?= $success_message; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="error-alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= $error_message; ?>
        </div>
    <?php endif; ?>

    <div class="users-table-container">
        <?php if ($users_result && $users_result->num_rows > 0): ?>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><i class="fa-solid fa-user"></i> Name</th>
                        <th><i class="fa-solid fa-envelope"></i> Email</th>
                        <th><i class="fa-solid fa-file-lines"></i> Total Reports</th>
                        <th><i class="fa-solid fa-check-circle"></i> Resolved</th>
                        <th><i class="fa-solid fa-clock"></i> Pending</th>
                        <th><i class="fa-solid fa-calendar"></i> Joined</th>
                        <th><i class="fa-solid fa-gear"></i> Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $count = 1; while ($user = $users_result->fetch_assoc()): ?>
                        <tr>
                            <td class="row-number"><?= $count++; ?></td>
                            <td>
                                <div class="user-name-cell">
                                    <div class="user-avatar">
                                        <?= strtoupper(substr($user['name'], 0, 1)); ?>
                                    </div>
                                    <span><?= htmlspecialchars($user['name']); ?></span>
                                </div>
                            </td>
                            <td class="user-email"><?= htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="stat-badge stat-total"><?= $user['total_reports']; ?></span>
                            </td>
                            <td>
                                <span class="stat-badge stat-resolved"><?= $user['resolved_reports'] ?? 0; ?></span>
                            </td>
                            <td>
                                <span class="stat-badge stat-pending"><?= $user['pending_reports'] ?? 0; ?></span>
                            </td>
                            <td class="joined-date">
                                <?= date('M d, Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td>
                                <button class="btn-delete-user" onclick="confirmDelete(<?= $user['id']; ?>, '<?= htmlspecialchars(addslashes($user['name'])); ?>', <?= $user['total_reports']; ?>)">
                                    <i class="fa-solid fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-users-slash"></i>
                <h3>No Users Found</h3>
                <p>There are no registered users in the system yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h2>Delete User?</h2>
        <p id="modal-message">This action cannot be undone.</p>
        <div class="modal-warning">
            <i class="fa-solid fa-circle-exclamation"></i>
            All reports submitted by this user will also be <strong>permanently deleted</strong>.
        </div>
        <div class="modal-actions">
            <form method="POST" id="deleteForm">
                <input type="hidden" name="user_id" id="deleteUserId">
                <button type="submit" name="delete_user" class="btn-confirm-delete">
                    <i class="fa-solid fa-trash"></i> Yes, Delete Permanently
                </button>
            </form>
            <button class="btn-cancel-delete" onclick="closeModal()">
                <i class="fa-solid fa-times"></i> Cancel
            </button>
        </div>
    </div>
</div>

<script>
function confirmDelete(userId, userName, reportCount) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('modal-message').innerHTML =
        'You are about to delete <strong>' + userName + '</strong>.' +
        (reportCount > 0 ? ' They have submitted <strong>' + reportCount + ' report(s)</strong>.' : '');
    document.getElementById('deleteModal').classList.add('active');
}

function closeModal() {
    document.getElementById('deleteModal').classList.remove('active');
}

// Close modal on overlay click
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>

</body>
</html>