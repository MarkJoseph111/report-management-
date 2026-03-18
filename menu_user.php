<?php
// menu_user.php - Include this in all USER pages
// Active page values: 'home', 'report', 'viewreports'
if (!isset($active_page)) $active_page = '';
?>

<!-- Hamburger Menu Button -->
<button class="menu-btn" id="menuBtn" onclick="toggleSidebar()" aria-label="Toggle Menu">
    <span class="bar"></span>
    <span class="bar"></span>
    <span class="bar"></span>
</button>

<!-- Overlay (click to close) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- User Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo-icon">
            <i class="fa-solid fa-file-circle-check"></i>
        </div>
        <div>
            <div class="sidebar-title">Report System</div>
            <div class="sidebar-role">User Panel</div>
        </div>
    </div>
    <ul>
        <li>
            <a href="user_page.php" <?= $active_page === 'home' ? 'class="active"' : ''; ?>>
                <span class="icon"><i class="fa-solid fa-house"></i></span>
                <span class="text">Home</span>
            </a>
        </li>
        <li>
            <a href="submit_reports.php" <?= $active_page === 'report' ? 'class="active"' : ''; ?>>
                <span class="icon"><i class="fa-solid fa-file-lines"></i></span>
                <span class="text">Report</span>
            </a>
        </li>
        <li>
            <a href="notifications_user.php" <?= $active_page === 'notifications' ? 'class="active"' : ''; ?>>
                <span class="icon notif-bell-wrap">
                    <i class="fa-solid fa-bell"></i>
                    <span class="notif-badge" id="userNotifBadge" style="display:none;"></span>
                </span>
                <span class="text">Notifications</span>
            </a>
        </li>
        <li>
            <a href="view_reports.php" <?= $active_page === 'viewreports' ? 'class="active"' : ''; ?>>
                <span class="icon"><i class="fa-solid fa-folder-open"></i></span>
                <span class="text">View Reports</span>
            </a>
        </li>
        <!-- Settings with Dark Mode Dropdown -->
        <li class="settings-item" id="settingsItem">
            <a href="#" onclick="toggleSettings(event)">
                <span class="icon"><i class="fa-solid fa-gear"></i></span>
                <span class="text">Settings</span>
                <i class="fa-solid fa-chevron-down settings-arrow" id="settingsArrow"></i>
            </a>
            <div class="settings-dropdown" id="settingsDropdown">
                <div class="settings-dropdown-label">Appearance</div>
                <div class="dark-mode-toggle">
                    <div class="toggle-label">
                        <i class="fa-solid fa-moon" id="themeIcon"></i>
                        <span id="themeLabel">Dark Mode</span>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="darkModeToggle" onchange="toggleDarkMode(this)">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </li>

        <li class="logout-item">
            <a href="logout.php">
                <span class="icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                <span class="text">Logout</span>
            </a>
        </li>
    </ul>
</div>

<!-- Sidebar + Dark Mode JS -->
<script>
function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var btn     = document.getElementById('menuBtn');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
    btn.classList.toggle('open');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('active');
    document.getElementById('menuBtn').classList.remove('open');
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSidebar();
});

function toggleSettings(e) {
    e.preventDefault();
    var dropdown = document.getElementById('settingsDropdown');
    var item     = document.getElementById('settingsItem');
    dropdown.classList.toggle('open');
    item.classList.toggle('open');
}

function toggleDarkMode(checkbox) {
    var isDark = checkbox.checked;
    document.body.classList.toggle('dark-mode', isDark);
    localStorage.setItem('theme_user', isDark ? 'dark' : 'light');
    var icon  = document.getElementById('themeIcon');
    var label = document.getElementById('themeLabel');
    if (isDark) {
        icon.className = 'fa-solid fa-sun';
        icon.style.color = '#f9ca24';
        label.textContent = 'Light Mode';
    } else {
        icon.className = 'fa-solid fa-moon';
        icon.style.color = '#a0a8b8';
        label.textContent = 'Dark Mode';
    }
}

(function() {
    var saved = localStorage.getItem('theme_user');
    if (saved === 'dark') {
        document.body.classList.add('dark-mode');
        var toggle = document.getElementById('darkModeToggle');
        if (toggle) {
            toggle.checked = true;
            var icon  = document.getElementById('themeIcon');
            var label = document.getElementById('themeLabel');
            icon.className = 'fa-solid fa-sun';
            icon.style.color = '#f9ca24';
            label.textContent = 'Light Mode';
        }
    }
})();

// Fetch and show user notification badge
function fetchUserNotifCount() {
    fetch('notification_api.php?action=get_count')
        .then(r => r.json())
        .then(data => {
            var badge = document.getElementById('userNotifBadge');
            if (!badge) return;
            if (data.success && data.count > 0) {
                badge.textContent = data.count > 99 ? '99+' : data.count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }).catch(() => {});
}
fetchUserNotifCount();
setInterval(fetchUserNotifCount, 15000);
</script>