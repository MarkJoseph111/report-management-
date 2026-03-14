<?php
/**
 * Notification API — Handles notification operations via AJAX
 * Supports: marking as seen, counting unread, clearing notifications
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

require_once 'config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$user_email = $_SESSION['email'];

// Get user role and ID
$user_result = $conn->query("SELECT id, role FROM users WHERE email = '" . $conn->real_escape_string($user_email) . "'");
if (!$user_result || $user_result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit();
}

$user_data = $user_result->fetch_assoc();
$user_id = $user_data['id'];
$role = $user_data['role'];

switch ($action) {
    
    // Mark a single report notification as seen
    case 'mark_seen':
        if (!isset($_POST['report_id'])) {
            echo json_encode(['success' => false, 'error' => 'Missing report_id']);
            exit();
        }
        
        $report_id = intval($_POST['report_id']);
        
        if ($role === 'admin') {
            $update_sql = "UPDATE reports SET admin_notif_seen = 1 WHERE id = $report_id";
            $conn->query("UPDATE report_notifications SET is_read = 1 WHERE report_id = $report_id AND admin_id = $user_id");
        } else {
            $update_sql = "UPDATE reports SET user_notif_seen = 1 WHERE id = $report_id AND user_id = $user_id";
            $conn->query("UPDATE user_notifications SET is_read = 1 WHERE report_id = $report_id AND user_id = $user_id");
        }
        
        if ($conn->query($update_sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update database']);
        }
        break;
    
    // Get count of unread notifications
    case 'get_count':
        if ($role === 'admin') {
            // Admin unreplied reports
            $count_sql = "SELECT COUNT(*) as count FROM reports WHERE (admin_reply IS NULL OR admin_reply = '') AND admin_notif_seen = 0";
        } else {
            // User unreplied reports with admin responses
            $count_sql = "SELECT COUNT(*) as count FROM reports WHERE user_email = '" . $conn->real_escape_string($user_email) . "' AND admin_reply IS NOT NULL AND admin_reply != '' AND user_notif_seen = 0";
        }
        
        $result = $conn->query($count_sql);
        $count_data = $result->fetch_assoc();
        $count = $count_data['count'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'count' => $count,
            'role' => $role
        ]);
        break;
    
    // Mark all notifications as seen
    case 'mark_all_seen':
        if ($role === 'admin') {
            $conn->query("UPDATE reports SET admin_notif_seen = 1 WHERE admin_reply IS NULL OR admin_reply = ''");
        } else {
            $conn->query("UPDATE reports SET user_notif_seen = 1 WHERE user_email = '" . $conn->real_escape_string($user_email) . "' AND admin_reply IS NOT NULL AND admin_reply != ''");
        }
        
        echo json_encode(['success' => true]);
        break;
    
    // Get detailed notification list
    case 'get_notifications':
        if ($role === 'admin') {
            $query = "SELECT id, user_name, user_email, report_title, created_at, priority FROM reports WHERE (admin_reply IS NULL OR admin_reply = '') ORDER BY FIELD(priority, 'critical', 'high', 'medium', 'low'), created_at DESC LIMIT 10";
        } else {
            $query = "SELECT id, report_title, admin_name, replied_at FROM reports WHERE user_email = '" . $conn->real_escape_string($user_email) . "' AND admin_reply IS NOT NULL AND admin_reply != '' AND user_notif_seen = 0 ORDER BY replied_at DESC LIMIT 10";
        }
        
        $result = $conn->query($query);
        $notifications = [];
        
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'role' => $role
        ]);
        break;
    
    // Get unread count specifically
    case 'get_unread_count':
        if ($role === 'admin') {
            $query = "SELECT COUNT(*) as count FROM reports WHERE (admin_reply IS NULL OR admin_reply = '')";
        } else {
            $query = "SELECT COUNT(*) as count FROM reports WHERE user_email = '" . $conn->real_escape_string($user_email) . "' AND admin_reply IS NOT NULL AND admin_reply != '' AND user_notif_seen = 0";
        }
        
        $result = $conn->query($query);
        $data = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'count' => $data['count'] ?? 0
        ]);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>
