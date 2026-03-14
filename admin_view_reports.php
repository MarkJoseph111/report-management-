        <?php

        session_start();
        if (!isset($_SESSION['email'])) {
            header("Location: index.php");
            exit();
        }

        require_once 'config.php';
        require_once 'nlp_service.php';

        // Role check only admins can access this page
        $role_check = $conn->query("SELECT role FROM users WHERE email = '" . $conn->real_escape_string($_SESSION['email']) . "'");
        $role_data = $role_check->fetch_assoc();
        if (!$role_data || $role_data['role'] !== 'admin') {
            header("Location: user_page.php");
            exit();
        }

        // Initialize NLP Service
        $nlp_service = new NLPService($conn);
        $admin_id = $conn->query("SELECT id FROM users WHERE email = '" . $conn->real_escape_string($_SESSION['email']) . "'")->fetch_assoc()['id'];

        // Handle NLP analysis for a specific report
        if (isset($_POST['analyze_priority'])) {
            $report_id = intval($_POST['report_id']);
            $report_result = $conn->query("SELECT report_title, report_content FROM reports WHERE id = $report_id");
            if ($report_result->num_rows > 0) {
                $report = $report_result->fetch_assoc();
                $analysis = $nlp_service->analyzeReport($report['report_title'] . ' ' . $report['report_content']);
                $priority = $analysis['priority'] ?? 'medium';
                $confidence = $analysis['priority_confidence'] ?? $analysis['category_confidence'] ?? 0.5;
                if ($nlp_service->updateReportPriority($report_id, $priority, $confidence, 'nlp_auto', $admin_id)) {
                    $_SESSION['analysis_message'] = "✓ Report priority analyzed successfully! Priority: " . strtoupper($priority);
                }
            }
        }

        // Handle manual priority update
        if (isset($_POST['update_priority'])) {
            $report_id = intval($_POST['report_id']);
            $new_priority = $conn->real_escape_string($_POST['priority']);
            
            if (in_array($new_priority, ['critical', 'high', 'medium', 'low'])) {
                if ($nlp_service->updateReportPriority($report_id, $new_priority, 1.0, 'admin_manual', $admin_id)) {
                    $_SESSION['analysis_message'] = "✓ Priority updated manually to " . strtoupper($new_priority);
                }
            }
        }

        // Handle admin reply submission
        if (isset($_POST['submit_reply'])) {
            $report_id = $conn->real_escape_string($_POST['report_id']);
            $admin_reply = $conn->real_escape_string($_POST['admin_reply']);
            $help_arrival = $conn->real_escape_string($_POST['help_arrival']);
            $admin_name = $_SESSION['name'];
            
            $sql = "UPDATE reports SET 
                    admin_reply = '$admin_reply', 
                    help_arrival = '$help_arrival',
                    admin_name = '$admin_name',
                    admin_id = $admin_id,
                    replied_at = NOW(),
                    admin_notif_seen = 1,
                    status = 'in-progress'  /* mark in-progress */
                    WHERE id = '$report_id'";
            
            if ($conn->query($sql)) {
                // Get user_id for notification
                $user_id_result = $conn->query("SELECT user_id FROM reports WHERE id = '$report_id'");
                $user_id = $user_id_result->fetch_assoc()['user_id'];
                
                // Insert notification for user
                $notification_message = "Your report has been reviewed and forwarded for action.";
                $conn->query("INSERT INTO user_notifications (user_id, report_id, message) VALUES ($user_id, '$report_id', '$notification_message')");
                
                // Insert notification record in report_notifications for audit trail
                $conn->query("INSERT INTO report_notifications (report_id, admin_id, notification_type, is_read) VALUES ('$report_id', $admin_id, 'new_report', 1)");
                
                $success_message = "Reply sent successfully!";
            } else {
                $error_message = "Error sending reply. Please try again.";
            }
        }

        // Handle manual resolution
        if (isset($_POST['mark_resolved']) && isset($_POST['report_id'])) {
            $rid = intval($_POST['report_id']);
            if ($conn->query("UPDATE reports SET status = 'resolved' WHERE id = $rid")) {
                $success_message = "Report #$rid marked as resolved.";
            } else {
                $error_message = "Failed to mark report as resolved.";
            }
        }

        // Get filter options
        $priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
        $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

        // Build query with filters
        $query = "SELECT * FROM reports WHERE 1=1";
        if ($priority_filter !== 'all') {
            $query .= " AND priority = '" . $conn->real_escape_string($priority_filter) . "'";
        }
        if ($status_filter !== 'all') {
            $query .= " AND status = '" . $conn->real_escape_string($status_filter) . "'";
        }
        $query .= " ORDER BY FIELD(priority, 'critical', 'high', 'medium', 'low'), created_at DESC";

        $result = $conn->query($query);

        // Get priority statistics
        $priority_stats = $nlp_service->getPriorityStats();

        ?>

        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>All User Reports</title>
            <link rel="stylesheet" href="style.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
            <style>
                .highlighted {
                    border: 3px solid #ff6b6b !important;
                    box-shadow: 0 0 20px rgba(255, 107, 107, 0.5) !important;
                    transition: border 0.3s ease;
                }
                
                /* Clickable stat cards with hover effects */
                .stats-dashboard a.stat-card {
                    display: flex;
                    align-items: center;
                    gap: 20px;
                    padding: 25px;
                    border-radius: 15px;
                    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
                    text-decoration: none;
                    color: inherit;
                    transition: all 0.3s ease;
                    cursor: pointer;
                    position: relative;
                    border: none;
                }
                
                .stats-dashboard a.stat-card:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
                    border-color: #667eea;
                }
                
                .stats-dashboard a.stat-card.active {
                    border-color: #667eea;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: #333;
                }
                
                .stats-dashboard a.stat-card.active i {
                    color: #fff !important;
                }
                
                .stats-dashboard a.stat-card.active .stat-label {
                    color: #fff;
                }
                
                .stats-dashboard a.stat-card.active .stat-value {
                    color: #333;
                    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
                }
                
                .stats-dashboard a.stat-card .filter-indicator {
                    position: absolute;
                    top: 8px;
                    right: 8px;
                    font-size: 11px;
                    color: #667eea;
                    background: #fff;
                    padding: 3px 8px;
                    border-radius: 20px;
                    font-weight: 600;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                
                /* Clear filter button */
                .clear-filter {
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    padding: 8px 16px;
                    background: #ff6b6b;
                    color: #fff;
                    text-decoration: none;
                    border-radius: 8px;
                    font-size: 13px;
                    font-weight: 600;
                    transition: all 0.3s ease;
                }
                
                .clear-filter:hover {
                    background: #ee5a52;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);
                }
            </style>
        </head>

        <body>
        <?php $active_page = 'reports'; require_once 'menu_admin.php'; ?>

        <div class="main-content">
            <div class="header">
                <h1>All User Reports</h1>
                <p class="subtitle">Manage and review user submitted reports with AI prioritization</p>
            </div>
            
            <!-- Statistics Dashboard - Clickable for Priority Filter -->
            <div class="stats-dashboard">
                <a href="?priority=critical&status=<?= $status_filter; ?>" class="stat-card <?= $priority_filter === 'critical' ? 'active' : ''; ?>" title="Click to filter Critical reports">
                    <i class="fa-solid fa-exclamation-triangle" style="color: #e74c3c;"></i>
                    <div class="stat-content">
                        <span class="stat-label">Critical</span>
                        <span class="stat-value"><?= isset($priority_stats['critical']) ? $priority_stats['critical']['count'] : 0; ?></span>
                    </div>
                    <?php if ($priority_filter === 'critical'): ?>
                        <span class="filter-indicator"><i class="fa-solid fa-check-circle"></i> Filtering</span>
                    <?php endif; ?>
                </a>
                <a href="?priority=high&status=<?= $status_filter; ?>" class="stat-card <?= $priority_filter === 'high' ? 'active' : ''; ?>" title="Click to filter High priority reports">
                    <i class="fa-solid fa-triangle-exclamation" style="color: #f39c12;"></i>
                    <div class="stat-content">
                        <span class="stat-label">High</span>
                        <span class="stat-value"><?= isset($priority_stats['high']) ? $priority_stats['high']['count'] : 0; ?></span>
                    </div>
                    <?php if ($priority_filter === 'high'): ?>
                        <span class="filter-indicator"><i class="fa-solid fa-check-circle"></i> Filtering</span>
                    <?php endif; ?>
                </a>
                <a href="?priority=medium&status=<?= $status_filter; ?>" class="stat-card <?= $priority_filter === 'medium' ? 'active' : ''; ?>" title="Click to filter Medium priority reports">
                    <i class="fa-solid fa-circle-info" style="color: #3498db;"></i>
                    <div class="stat-content">
                        <span class="stat-label">Medium</span>
                        <span class="stat-value"><?= isset($priority_stats['medium']) ? $priority_stats['medium']['count'] : 0; ?></span>
                    </div>
                    <?php if ($priority_filter === 'medium'): ?>
                        <span class="filter-indicator"><i class="fa-solid fa-check-circle"></i> Filtering</span>
                    <?php endif; ?>
                </a>
                <a href="?priority=low&status=<?= $status_filter; ?>" class="stat-card <?= $priority_filter === 'low' ? 'active' : ''; ?>" title="Click to filter Low priority reports">
                    <i class="fa-solid fa-circle-check" style="color: #27ae60;"></i>
                    <div class="stat-content">
                        <span class="stat-label">Low</span>
                        <span class="stat-value"><?= isset($priority_stats['low']) ? $priority_stats['low']['count'] : 0; ?></span>
                    </div>
                    <?php if ($priority_filter === 'low'): ?>
                        <span class="filter-indicator"><i class="fa-solid fa-check-circle"></i> Filtering</span>
                    <?php endif; ?>
                </a>
            </div>
            
            <!-- Filters -->
            <div class="filter-section">
                <form method="GET" class="filter-form" style="justify-content: space-between;">
                    <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center;">
                        <div class="filter-group">
                            <label>Status:</label>
                            <select name="status" onchange="this.form.submit()">
                                <option value="all" <?= $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in-progress" <?= $status_filter === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?= $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>
                        <div class="search-group">
                            <label>Search:</label>
                            <input type="text" id="reportSearch" placeholder="Search by title, content, user name/email...">
                        </div>
                    </div>
                    <?php if ($priority_filter !== 'all'): ?>
                        <div class="filter-group">
                            <a href="?status=<?= $status_filter; ?>" class="clear-filter" style="margin-left: auto;">
                                <i class="fa-solid fa-times"></i> Clear Priority Filter
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <?php 
            $messages = ['success' => $_SESSION['success_message'] ?? '', 'error' => $_SESSION['error_message'] ?? '', 'analysis' => $_SESSION['analysis_message'] ?? ''];
            unset($_SESSION['success_message'], $_SESSION['error_message'], $_SESSION['analysis_message']);
            ?>
            
            <?php if (isset($success_message) || !empty($messages['success'])): ?>
                <div class="success-alert">
                    <i class="fa-solid fa-circle-check"></i>
                    <?= $success_message ?? $messages['success']; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message) || !empty($messages['error'])): ?>
                <div class="error-alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= $error_message ?? $messages['error']; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($messages['analysis'])): ?>
                <div class="info-alert">
                    <i class="fa-solid fa-lightbulb"></i>
                    <?= $messages['analysis']; ?>
                </div>
            <?php endif; ?>
            
            <div class="reports-container">
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <div class="report-card admin-report priority-<?= strtolower($row['priority']); ?>" id="report-<?= $row['id']; ?>">
                            <div class="report-header">
                                <div>
                                    <h3><?= htmlspecialchars($row['report_title']); ?></h3>
                                    <p class="report-user">
                                        <i class="fa-solid fa-user"></i>
                                        <?= htmlspecialchars($row['user_name']); ?> 
                                        (<?= htmlspecialchars($row['user_email']); ?>)
                                    </p>
                                </div>
                                <div class="header-right">
                                    <span class="priority-badge priority-<?= strtolower($row['priority']); ?>">
                                        <i class="fa-solid fa-star"></i>
                                        <?= strtoupper($row['priority']); ?>
                                        <?php if (!empty($row['priority_confidence'])): ?>
                                            <span class="confidence"><?= intval($row['priority_confidence'] * 100); ?>%</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="status-badge status-<?= $row['status']; ?>">
                                        <?= ucfirst($row['status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="report-content">
                                <p><?= nl2br(htmlspecialchars($row['report_content'])); ?></p>
                            </div>
                            
                            <!-- Priority Management Section -->
                            <div class="priority-management">
                                <div class="priority-controls">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="report_id" value="<?= $row['id']; ?>">
                                        <button type="submit" name="analyze_priority" class="btn-analyze" title="Analyze with NLP AI">
                                            <i class="fa-solid fa-brain"></i> Analyze with AI
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" id="priority-form-<?= $row['id']; ?>">
                                        <input type="hidden" name="report_id" value="<?= $row['id']; ?>">
                                        <select name="priority" onchange="this.form.submit()" class="priority-select">
                                            <option value="" disabled selected>Change Priority</option>
                                            <option value="critical">🔴 Critical</option>
                                            <option value="high">🟠 High</option>
                                            <option value="medium">🔵 Medium</option>
                                            <option value="low">🟢 Low</option>
                                        </select>
                                        <input type="hidden" name="update_priority" value="1">
                                    </form>
                                </div>
                            </div>
                            
                            <?php if (!empty($row['admin_reply'])): ?>
                                <div class="admin-reply-display">
                                    <div class="reply-header">
                                        <i class="fa-solid fa-reply"></i>
                                        <strong>Admin Response</strong>
                                        <?php if (!empty($row['admin_name'])): ?>
                                            <span class="reply-by">by <?= htmlspecialchars($row['admin_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p><?= nl2br(htmlspecialchars($row['admin_reply'])); ?></p>
                                    <?php if (!empty($row['help_arrival'])): ?>
                                        <p class="help-arrival">
                                            <i class="fa-solid fa-clock"></i>
                                            <strong>Help will arrive:</strong> <?= htmlspecialchars($row['help_arrival']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="replied-time">
                                        <i class="fa-solid fa-calendar"></i>
                                        Replied: <?= date('M d, Y - h:i A', strtotime($row['replied_at'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="admin-reply-section">
                                <button class="toggle-reply-btn" onclick="toggleReply(<?= $row['id']; ?>)">
                                    <i class="fa-solid fa-message"></i>
                                    <?= empty($row['admin_reply']) ? 'Reply to Report' : 'Update Reply'; ?>
                                </button>
                                
                                <div id="reply-form-<?= $row['id']; ?>" class="reply-form" style="display: none;">
                                    <form method="POST">
                                        <input type="hidden" name="report_id" value="<?= $row['id']; ?>">
                                        
                                        <div class="form-group">
                                            <label>
                                                <i class="fa-solid fa-message"></i> Your Reply to User
                                            </label>
                                            <textarea name="admin_reply" rows="4" placeholder="Write your response to the user..." required><?= htmlspecialchars($row['admin_reply'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>
                                                <i class="fa-solid fa-clock"></i> When Will Help Arrive?
                                            </label>
                                            <input type="text" name="help_arrival" 
                                                placeholder="E.g., Within 24 hours, Tomorrow at 3 PM, etc." 
                                                value="<?= htmlspecialchars($row['help_arrival'] ?? ''); ?>" required>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <button type="submit" name="submit_reply" class="btn-submit">
                                                <i class="fa-solid fa-paper-plane"></i> Send Reply
                                            </button>
                                            <button type="button" class="btn-reset" onclick="toggleReply(<?= $row['id']; ?>)">
                                                <i class="fa-solid fa-times"></i> Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php if (!empty($row['admin_reply']) && $row['status'] !== 'resolved'): ?>
                                <div class="resolution-section">
                                    <form method="POST" style="margin-top:8px;">
                                        <input type="hidden" name="report_id" value="<?= $row['id']; ?>">
                                        <button type="submit" name="mark_resolved" class="btn-resolve">
                                            <i class="fa-solid fa-check-double"></i> Mark Resolved
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                            <div class="report-footer">
                                <span class="report-date">
                                    <i class="fa-solid fa-clock"></i>
                                    <?= date('M d, Y - h:i A', strtotime($row['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-inbox"></i>
                        <h3>No Reports Found</h3>
                        <p><?php 
                            if ($priority_filter !== 'all' || $status_filter !== 'all') {
                                echo "No matching reports found. Try adjusting your filters.";
                            } else {
                                echo "No users have submitted reports yet.";
                            }
                        ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        function toggleReply(reportId) {
            const replyForm = document.getElementById('reply-form-' + reportId);
            if (replyForm.style.display === 'none') {
                replyForm.style.display = 'block';
            } else {
                replyForm.style.display = 'none';
            }
        }
        
        // Handle scrolling, highlighting, and search functionality
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
            var reportId = urlParams.get('id');
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

            if (searchInput) {
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
                    
                    // Show no results message
                    let noResults = document.querySelector('.no-reports-search');
                    if (searchTerm && visibleCount === 0) {
                        if (!noResults) {
                            noResults = document.createElement('div');
                            noResults.className = 'no-reports-search empty-state';
                            noResults.innerHTML = `
                                <i class="fa-solid fa-search"></i>
                                <h3>No Reports Found</h3>
                                <p>No reports match "${searchTerm}". Try different keywords from title, content, or user info.</p>
                            `;
                            reportsContainer.appendChild(noResults);
                        }
                    } else if (noResults) {
                        noResults.remove();
                    }
                });
            }

            // Existing toggleReply function
            function toggleReply(reportId) {
                const replyForm = document.getElementById('reply-form-' + reportId);
                if (replyForm.style.display === 'none') {
                    replyForm.style.display = 'block';
                } else {
                    replyForm.style.display = 'none';
                }
            }
        });
        </script>
            
        </body>
        </html>
  </html>