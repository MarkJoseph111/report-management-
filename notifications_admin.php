<?php

session_start();
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

require_once 'config.php';

// Get admin_id for notification tracking
$admin_data = $conn->query("SELECT id FROM users WHERE email = '" . $conn->real_escape_string($_SESSION['email']) . "'")->fetch_assoc();
$admin_id = $admin_data['id'];

// Handle AJAX request to mark notification as seen
if (isset($_POST['action']) && $_POST['action'] === 'mark_seen' && isset($_POST['report_id'])) {
    $report_id = intval($_POST['report_id']);
    $update_sql = "UPDATE reports SET admin_notif_seen = 1 WHERE id = $report_id";
    
    if ($conn->query($update_sql)) {
        // Also mark in report_notifications table if it exists
        $conn->query("UPDATE report_notifications SET is_read = 1 WHERE report_id = $report_id AND admin_id = $admin_id");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit();
}

// NOTE: admin_notif_seen is set to 1 when the admin clicks the notification or submits a reply.
// The red dot stays until explicitly marked as seen or the admin replies to the report

// Fetch only NEW reports (no admin reply yet)
$reports = $conn->query("SELECT * FROM reports WHERE admin_reply IS NULL OR admin_reply = '' ORDER BY FIELD(priority, 'critical', 'high', 'medium', 'low'), created_at DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Notifications</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    .notif-page-wrap { max-width: 800px; width: 100%; margin: 0 auto; padding-bottom: 40px; }

    .notif-list { display: flex; flex-direction: column; gap: 14px; }

    .ncard {
      background: #fff;
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 2px 14px rgba(0,0,0,0.07);
      border-left: 4px solid #e74c3c;
      transition: transform .2s, box-shadow .2s;
      animation: cardIn .35s ease both;
    }
    .ncard:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(0,0,0,0.12); }
    @keyframes cardIn {
      from { opacity:0; transform:translateY(14px); }
      to   { opacity:1; transform:translateY(0); }
    }

    .ncard-inner { padding: 20px 24px 22px; }

    /* Top row */
    .ncard-top { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 16px; }
    .ncard-avatar {
      width: 46px; height: 46px; border-radius: 50%;
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: #fff; font-size: 18px; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; position: relative;
    }
    .ncard-avatar .dot {
      position: absolute; top: -1px; right: -1px;
      width: 13px; height: 13px;
      background: #e74c3c; border-radius: 50%; border: 2px solid #fff;
      animation: npulse 1.6s ease-in-out infinite;
    }
    @keyframes npulse {
      0%,100% { transform:scale(1); box-shadow:0 0 0 0 rgba(231,76,60,.6); }
      50%     { transform:scale(1.1); box-shadow:0 0 0 5px rgba(231,76,60,0); }
    }
    .ncard-meta { flex: 1; min-width: 0; }
    .ncard-name { font-size: 15px; font-weight: 700; color: #2c3e50; }
    .ncard-email { font-size: 12px; color: #95a5a6; margin-top: 3px; display: flex; align-items: center; gap: 5px; }
    .ncard-time-top { font-size: 12px; color: #b2bec3; display: flex; align-items: center; gap: 5px; flex-shrink: 0; }

    /* Report box */
    .ncard-report {
      background: #f8f9fd;
      border-radius: 12px;
      padding: 14px 16px;
      margin-bottom: 16px;
      border-left: 3px solid #667eea;
    }
    .ncard-report-title {
      font-size: 14px; font-weight: 700; color: #2c3e50;
      display: flex; align-items: center; gap: 7px; margin-bottom: 8px;
    }
    .ncard-report-title i { color: #667eea; font-size: 13px; }
    .ncard-report-body { font-size: 13px; color: #7f8c8d; line-height: 1.65; }

    /* Footer */
    .ncard-footer {
      display: flex; align-items: center; justify-content: flex-end;
      padding-top: 14px; border-top: 1px solid #f0f4fb;
    }
    .ncard-action {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 9px 22px; border-radius: 10px;
      font-size: 13px; font-weight: 600; color: #fff;
      background: linear-gradient(135deg, #667eea, #764ba2);
      text-decoration: none; border: none; cursor: pointer;
      transition: all .2s; width: auto; margin: 0;
    }
    .ncard-action:hover { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(102,126,234,0.4); }

    /* Empty */
    .notif-empty {
      text-align: center; padding: 70px 20px;
      background: #fff; border-radius: 20px;
      box-shadow: 0 2px 14px rgba(0,0,0,0.06);
    }
    .notif-empty i  { font-size: 60px; color: #dce3ed; margin-bottom: 18px; display: block; }
    .notif-empty h3 { font-size: 20px; color: #2c3e50; margin-bottom: 8px; }
    .notif-empty p  { font-size: 14px; color: #95a5a6; }

    /* Dark mode */
    body.dark-mode .ncard            { background: #1c2a38; border-left-color: #e74c3c; box-shadow: 0 2px 14px rgba(0,0,0,.35); }
    body.dark-mode .ncard-name       { color: #e8edf3; }
    body.dark-mode .ncard-email      { color: #4a6278; }
    body.dark-mode .ncard-time-top   { color: #4a6278; }
    body.dark-mode .ncard-report     { background: #17232f; border-left-color: #667eea; }
    body.dark-mode .ncard-report-title { color: #e8edf3; }
    body.dark-mode .ncard-report-body  { color: #8395a7; }
    body.dark-mode .ncard-footer     { border-top-color: #243444; }
    body.dark-mode .notif-empty      { background: #1c2a38; }
    body.dark-mode .notif-empty i    { color: #2e4060; }
    body.dark-mode .notif-empty h3   { color: #e8edf3; }
    body.dark-mode .notif-empty p    { color: #4a6278; }
  </style>
</head>
<body>
<?php $active_page = 'notifications'; require_once 'menu_admin.php'; ?>

<div class="main-content">
  <div class="header">
    <h1>Notifications</h1>
    <p class="subtitle">New reports waiting for your reply</p>
  </div>

  <div class="notif-page-wrap">
    <div class="notif-list">
      <?php if ($reports && $reports->num_rows > 0):
        $i = 0;
        while ($r = $reports->fetch_assoc()):
          $delay = $i * 0.06; $i++;
      ?>
        <div class="ncard" style="animation-delay:<?= $delay; ?>s">
          <div class="ncard-inner">

            <div class="ncard-top">
              <div class="ncard-avatar">
                <?= strtoupper(substr($r['user_name'], 0, 1)); ?>
                <span class="dot"></span>
              </div>
              <div class="ncard-meta">
                <div class="ncard-name"><?= htmlspecialchars($r['user_name']); ?></div>
                <div class="ncard-email">
                  <i class="fa-solid fa-envelope"></i>
                  <?= htmlspecialchars($r['user_email']); ?>
                </div>
              </div>
              <span class="ncard-time-top">
                <i class="fa-solid fa-clock"></i>
                <?= date('M d, Y — h:i A', strtotime($r['created_at'])); ?>
              </span>
            </div>

            <div class="ncard-report">
              <div class="ncard-report-title">
                <i class="fa-solid fa-file-lines"></i>
                <?= htmlspecialchars($r['report_title']); ?>
              </div>
              <div class="ncard-report-body">
                <?= nl2br(htmlspecialchars($r['report_content'])); ?>
              </div>
            </div>

            <div class="ncard-footer">
              <a href="admin_view_reports.php#report-<?= $r['id']; ?>" class="ncard-action">
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

</body>
</html>