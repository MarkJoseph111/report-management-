<?php

session_start();
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

require_once 'config.php';

$role_check = $conn->query("SELECT role FROM users WHERE email = '" . $conn->real_escape_string($_SESSION['email']) . "'");
$role_data  = $role_check->fetch_assoc();
if (!$role_data || $role_data['role'] !== 'user') {
    header("Location: admin_page.php");
    exit();
}

$user_email = $_SESSION['email'];
$user_data  = $conn->query("SELECT id FROM users WHERE email = '" . $conn->real_escape_string($user_email) . "'")->fetch_assoc();
$user_id    = $user_data['id'];

if (isset($_POST['action']) && $_POST['action'] === 'mark_seen' && isset($_POST['report_id'])) {
    $report_id = intval($_POST['report_id']);
    if ($conn->query("UPDATE reports SET user_notif_seen = 1 WHERE id = $report_id AND user_email = '" . $conn->real_escape_string($user_email) . "'")) {
        $conn->query("UPDATE user_notifications SET is_read = 1 WHERE report_id = $report_id AND user_id = $user_id");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

$reports = $conn->query("
    SELECT * FROM reports
    WHERE user_email = '" . $conn->real_escape_string($user_email) . "'
      AND admin_reply IS NOT NULL
      AND admin_reply != ''
    ORDER BY replied_at DESC
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Notifications</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<?php $active_page = 'notifications'; require_once 'menu_user.php'; ?>

<div class="main-content">
  <div class="header">
    <h1>Notifications</h1>
    <p class="subtitle">Admin replies to your reports — latest first</p>
  </div>

  <div class="notif-page-wrap">
    <div class="notif-list">
      <?php if ($reports && $reports->num_rows > 0):
        $i = 0;
        while ($r = $reports->fetch_assoc()):
          $delay  = $i * 0.06; $i++;
          $is_new = empty($r['user_notif_seen']);

          $pmap = [
            'critical' => ['color' => '#dc2626', 'label' => 'CRITICAL', 'icon' => 'fire'],
            'high'     => ['color' => '#d97706', 'label' => 'HIGH',     'icon' => 'exclamation-triangle'],
            'medium'   => ['color' => '#2563eb', 'label' => 'MEDIUM',   'icon' => 'circle-info'],
            'low'      => ['color' => '#059669', 'label' => 'LOW',      'icon' => 'circle-check'],
          ];
          $pc = $pmap[$r['priority']] ?? $pmap['medium'];

          $smap = [
            'pending'     => ['class' => 'ncard-status-pending',   'label' => 'Pending'],
            'in-progress' => ['class' => 'ncard-status-progress',  'label' => 'In-progress'],
            'resolved'    => ['class' => 'ncard-status-resolved',  'label' => 'Resolved'],
          ];
          $sc = $smap[$r['status']] ?? $smap['pending'];
      ?>
        <div class="ncard <?= $is_new ? 'ncard-unread' : ''; ?>"
             style="animation-delay:<?= $delay; ?>s; border-left-color:<?= $pc['color']; ?>">
          <div class="ncard-inner">

            <div class="ncard-head">
              <div class="ncard-avatar ncard-avatar-green">
                <i class="fa-solid fa-user-shield"></i>
                <?php if ($is_new): ?><span class="ncard-dot"></span><?php endif; ?>
              </div>
              <div class="ncard-meta-info">
                <div class="ncard-name">
                  <?= !empty($r['admin_name']) ? htmlspecialchars($r['admin_name']) : 'Admin'; ?>
                </div>
                <div class="ncard-sub">
                  <i class="fa-solid fa-reply"></i> Replied to your report
                </div>
              </div>
              <div class="ncard-head-right">
                <span class="ncard-badge ncard-badge-<?= $r['priority']; ?>">
                  <i class="fa-solid fa-<?= $pc['icon']; ?>"></i>
                  <?= $pc['label']; ?>
                </span>
                <span class="<?= $sc['class']; ?>">
                  <i class="fa-solid fa-circle-half-stroke"></i>
                  <?= $sc['label']; ?>
                </span>
                <span class="ncard-time">
                  <i class="fa-solid fa-clock"></i>
                  <?= date('M d, Y — h:i A', strtotime($r['replied_at'])); ?>
                </span>
              </div>
            </div>

            <div class="ncard-divider"></div>

            <div class="ncard-section">
              <div class="ncard-section-label">
                <i class="fa-solid fa-file-lines"></i> Your Report
                <span class="ncard-id">#<?= $r['id']; ?></span>
              </div>
              <div class="ncard-report-title"><?= htmlspecialchars($r['report_title']); ?></div>
              <div class="ncard-report-body"><?= nl2br(htmlspecialchars($r['report_content'])); ?></div>
            </div>

            <div class="ncard-section ncard-section-reply">
              <div class="ncard-section-label ncard-section-label-green">
                <i class="fa-solid fa-shield-halved"></i> Admin Response
              </div>
              <div class="ncard-report-body"><?= nl2br(htmlspecialchars($r['admin_reply'])); ?></div>
            </div>

            <?php if (!empty($r['help_arrival'])): ?>
              <div class="ncard-eta">
                <i class="fa-solid fa-truck-fast"></i>
                Help arrives: <?= htmlspecialchars($r['help_arrival']); ?>
              </div>
            <?php endif; ?>

            <div class="ncard-meta-row">
              <span><i class="fa-solid fa-calendar-plus"></i> Submitted: <?= date('M d, Y', strtotime($r['created_at'])); ?></span>
              <span><i class="fa-solid fa-calendar-check"></i> Replied: <?= date('M d, Y', strtotime($r['replied_at'])); ?></span>
            </div>

            <div class="ncard-footer">
              <span class="ncard-footer-left">
                <i class="fa-solid fa-hashtag"></i> Report #<?= $r['id']; ?>
              </span>
              <a href="view_reports.php?seen=<?= $r['id']; ?>#report-<?= $r['id']; ?>" class="ncard-btn ncard-btn-green">
                <i class="fa-solid fa-eye"></i> View Full Report
              </a>
            </div>

          </div>
        </div>
      <?php endwhile; else: ?>
        <div class="notif-empty">
          <i class="fa-solid fa-bell-slash"></i>
          <h3>No Replies Yet</h3>
          <p>You'll be notified here when an admin replies to your report.</p>
          <a href="submit_reports.php" class="btn-primary">
            <i class="fa-solid fa-plus"></i> Submit a Report
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('notification_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_all_seen'
    }).then(r => r.json()).then(data => {
        if (data.success) {
            var badge = document.getElementById('userNotifBadge');
            if (badge) badge.style.display = 'none';
            document.querySelectorAll('.ncard-dot').forEach(d => d.style.display = 'none');
            document.querySelectorAll('.ncard-unread').forEach(c => c.classList.remove('ncard-unread'));
            sessionStorage.setItem('user_notif_cleared', '1');
        }
    }).catch(() => {});
});
</script>
</body>
</html>