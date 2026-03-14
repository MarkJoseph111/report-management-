<?php

session_start();
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

require_once 'config.php';
require_once 'nlp_service.php';

// Role check — only users can access this page
$role_check = $conn->query("SELECT role FROM users WHERE email = '" . $conn->real_escape_string($_SESSION['email']) . "'");
$role_data = $role_check->fetch_assoc();
if (!$role_data || $role_data['role'] !== 'user') {
    header("Location: admin_page.php");
    exit();
}

// Initialize NLP Service
$nlp_service = new NLPService($conn);

// Handle report submission
if (isset($_POST['submit_report'])) {
    $user_name = $_SESSION['name'];
    $user_email = $_SESSION['email'];
    $report_title = $conn->real_escape_string($_POST['report_title']);
    $report_content = $conn->real_escape_string($_POST['report_content']);
    
    // Get user_id from users table
    $user_result = $conn->query("SELECT id FROM users WHERE email = '$user_email'");
    $user_data = $user_result->fetch_assoc();
    $user_id = $user_data['id'];
    
    // Analyze report priority using NLP
    $analysis = $nlp_service->analyzeReport($_POST['report_title'] . ' ' . $_POST['report_content']);
    $category = $analysis['category'] ?? 'normal';
    $priority = $analysis['priority'] ?? 'medium';
    $confidence = $analysis['priority_confidence'] ?? 0.5;
    
    $sql = "INSERT INTO reports (user_id, user_name, user_email, report_title, report_content, category, priority, priority_confidence, status) 
            VALUES ('$user_id', '$user_name', '$user_email', '$report_title', '$report_content', '$category', '$priority', '$confidence', 'pending')";
    
    if ($conn->query($sql)) {
        $report_id = $conn->insert_id;
        
        // Log the priority analysis
        $log_sql = "INSERT INTO priority_logs (report_id, new_priority, confidence_score, analysis_method) 
                    VALUES ($report_id, '$priority', $confidence, 'nlp_auto')";
        $conn->query($log_sql);
        
        $success_message = "Report submitted successfully! (AI Priority: " . strtoupper($priority) . ")";
    } else {
        $error_message = "Error submitting report. Please try again.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Report</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
<?php $active_page = 'report'; require_once 'menu_user.php'; ?>

<div class="main-content">
    <div class="header">
        <h1>Submit a Report</h1>
        <p class="subtitle">Compose and submit your report</p>
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
    
    <div class="report-form-container">
        <form action="submit_reports.php" method="POST" class="report-form">
            <div class="form-group">
                <label for="report_title">
                    <i class="fa-solid fa-heading"></i> Report Title
                </label>
                <input type="text" id="report_title" name="report_title" 
                       placeholder="Enter report title" required>
            </div>
            
            <div class="form-group">
                <label for="report_content">
                    <i class="fa-solid fa-file-lines"></i> Report Content
                </label>
                <textarea id="report_content" name="report_content" 
                          placeholder="Compose your report here..." 
                          rows="12" required></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="submit_report" class="btn-submit">
                    <i class="fa-solid fa-paper-plane"></i> Submit Report
                </button>
                <button type="reset" class="btn-reset">
                    <i class="fa-solid fa-rotate-left"></i> Clear
                </button>
            </div>
        </form>
    </div>
</div>
    
</body>
</html>