<?php

session_start();
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

require_once 'config.php';

$admin_data = $conn->query("SELECT id FROM users WHERE email = '" . $conn->real_escape_string($_SESSION['email']) . "'")->fetch_assoc();
$admin_id = $admin_data['id'];

if (isset($_POST['action']) && $_POST['action'] === 'mark_seen' && isset($_POST['report_id'])) {
    $report_id = intval($_POST['report_id']);
    if ($conn->query("UPDATE reports SET admin_notif_seen = 1 WHERE id = $report_id")) {
        $conn->query("UPDATE report_notifications SET is_read = 1 WHERE report_id = $report_id AND admin_id = $admin_id");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

if (isset($_POST['action']) && $_POST['action'] === 'dismiss_notif' && isset($_POST['report_id'])) {
    $report_id = intval($_POST['report_id']);
    if ($conn->query("UPDATE reports SET admin_notif_dismissed = 1 WHERE id = $report_id")) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

$reports = $conn->query("
    SELECT * FROM reports
    WHERE (admin_reply IS NULL OR admin_reply = '')
      AND (admin_notif_dismissed IS NULL OR admin_notif_dismissed = 0)
    ORDER BY created_at DESC
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Notifications</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<?php $active_page = 'notifications'; require_once 'menu_admin.php'; ?>

<div class="main-content">
  <div class="header">
    <h1>Notifications</h1>
    <p class="subtitle">New reports waiting for your reply — latest first</p>
  </div>

  <div class="notif-page-wrap">
    <div class="notif-list">
      <?php if ($reports && $reports->num_rows > 0):
        $i = 0;
        while ($r = $reports->fetch_assoc()):
          $delay  = $i * 0.06; $i++;
          $is_new = empty($r['admin_notif_seen']);

          $pmap = [
            'critical' => ['color' => '#dc2626', 'label' => 'CRITICAL', 'icon' => 'fire'],
            'high'     => ['color' => '#d97706', 'label' => 'HIGH',     'icon' => 'exclamation-triangle'],
            'medium'   => ['color' => '#2563eb', 'label' => 'MEDIUM',   'icon' => 'circle-info'],
            'low'      => ['color' => '#059669', 'label' => 'LOW',      'icon' => 'circle-check'],
          ];
          $pc = $pmap[$r['priority']] ?? $pmap['medium'];
      ?>
        <div class="ncard <?= $is_new ? 'ncard-unread' : ''; ?>"
             style="animation-delay:<?= $delay; ?>s; border-left-color:<?= $pc['color']; ?>">
          <div class="ncard-inner">

            <div class="ncard-head">
              <div class="ncard-avatar ncard-avatar-purple">
                <?= strtoupper(substr($r['user_name'], 0, 1)); ?>
                <?php if ($is_new): ?><span class="ncard-dot"></span><?php endif; ?>
              </div>
              <div class="ncard-meta-info">
                <div class="ncard-name"><?= htmlspecialchars($r['user_name']); ?></div>
                <div class="ncard-sub">
                  <i class="fa-solid fa-envelope"></i>
                  <?= htmlspecialchars($r['user_email']); ?>
                </div>
              </div>
              <div class="ncard-head-right">
                <span class="ncard-badge ncard-badge-<?= $r['priority']; ?>">
                  <i class="fa-solid fa-<?= $pc['icon']; ?>"></i>
                  <?= $pc['label']; ?>
                </span>
                <span class="ncard-time">
                  <i class="fa-solid fa-clock"></i>
                  <?= date('M d, Y — h:i A', strtotime($r['created_at'])); ?>
                </span>
              </div>
            </div>

            <div class="ncard-divider"></div>

            <div class="ncard-section">
              <div class="ncard-section-label">
                <i class="fa-solid fa-file-lines"></i> Report
                <span class="ncard-id">#<?= $r['id']; ?></span>
              </div>
              <div class="ncard-report-title"><?= htmlspecialchars($r['report_title']); ?></div>
              <div class="ncard-report-body"><?= nl2br(htmlspecialchars($r['report_content'])); ?></div>
            </div>

            <div class="ncard-meta-row">
              <span><i class="fa-solid fa-tag"></i> Category: <strong><?= ucfirst($r['category']); ?></strong></span>
              <span><i class="fa-solid fa-circle-half-stroke"></i> Status: <strong><?= ucfirst($r['status']); ?></strong></span>
              <span><i class="fa-solid fa-calendar-plus"></i> <?= date('M d, Y', strtotime($r['created_at'])); ?></span>
            </div>

            <div class="ncard-footer">
              <span class="ncard-footer-left">
                <i class="fa-solid fa-calendar-plus"></i>
                Submitted <?= date('M d, Y', strtotime($r['created_at'])); ?>
              </span>
              <a href="admin_view_reports.php#report-<?= $r['id']; ?>" class="ncard-btn"
                 onclick="replyNow(event, this, <?= $r['id']; ?>)">
                <i class="fa-solid fa-reply"></i> Reply Now
              </a>
            </div>

          </div>
        </div>
      <?php endwhile; else: ?>
        <div class="notif-empty">
          <i class="fa-solid fa-bell-slash"></i>
          <h3>All Caught Up!</h3>
          <p>No new reports waiting for a reply.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function replyNow(event, btn, reportId) {
    event.preventDefault();
    var card = btn.closest('.ncard');
    var dest = btn.getAttribute('href');
    fetch('notifications_admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=dismiss_notif&report_id=' + reportId
    }).catch(() => {});
    if (card) {
        card.style.transition = 'opacity 0.3s ease';
        card.style.opacity = '0';
        setTimeout(function() {
            card.remove();
            if (document.querySelectorAll('.ncard').length === 0) {
                document.querySelector('.notif-list').innerHTML = '<div class="notif-empty"><i class="fa-solid fa-bell-slash"></i><h3>All Caught Up!</h3><p>No new reports waiting for a reply.</p></div>';
            }
            window.location.href = dest;
        }, 300);
    } else {
        window.location.href = dest;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    fetch('notification_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_all_seen'
    }).then(r => r.json()).then(data => {
        if (data.success) {
            var badge = document.getElementById('adminNotifBadge');
            if (badge) badge.style.display = 'none';
            document.querySelectorAll('.ncard-dot').forEach(d => d.style.display = 'none');
            document.querySelectorAll('.ncard-unread').forEach(c => c.classList.remove('ncard-unread'));
            sessionStorage.setItem('admin_notif_cleared', '1');
        }
    }).catch(() => {});
});
</script>
</body>
</html>