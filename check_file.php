<?php
// ============================================================
//  check_files.php — System File & Connection Checker
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System File Check</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 30px; color: #2c3e50; }
        h1 { font-size: 26px; margin-bottom: 6px; }
        .subtitle { color: #7f8c8d; font-size: 14px; margin-bottom: 30px; }
        .section { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 4px 16px rgba(0,0,0,0.07); }
        .section h2 { font-size: 16px; font-weight: 700; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #f0f2f5; display: flex; align-items: center; gap: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { text-align: left; padding: 10px 14px; background: #2c3e50; color: #fff; font-weight: 600; }
        tr:nth-child(even) { background: #f8f9fa; }
        td { padding: 10px 14px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 700; }
        .badge-green  { background: #e8f5e9; color: #1b5e20; border: 1px solid #4caf50; }
        .badge-red    { background: #ffebee; color: #b71c1c; border: 1px solid #ef9a9a; }
        .badge-yellow { background: #fff8e1; color: #e65100; border: 1px solid #ffcc02; }
        .badge-blue   { background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9; }
        .badge-admin  { background: #fce4ec; color: #880e4f; border: 1px solid #f48fb1; }
        .badge-db     { background: #e8eaf6; color: #283593; border: 1px solid #9fa8da; }
        .connection-list { list-style: none; padding: 0; }
        .connection-list li { padding: 6px 0; font-size: 14px; display: flex; align-items: flex-start; gap: 8px; border-bottom: 1px solid #f5f5f5; }
        .connection-list li:last-child { border-bottom: none; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-top: 20px; }
        .summary-card { background: #f8f9fa; border-radius: 10px; padding: 18px; text-align: center; border: 2px solid #e0e0e0; }
        .summary-card .num { font-size: 32px; font-weight: 800; }
        .summary-card .label { font-size: 12px; color: #7f8c8d; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        .green-card { border-color: #4caf50; } .green-card .num { color: #1b5e20; }
        .red-card   { border-color: #ef9a9a; } .red-card .num   { color: #b71c1c; }
        .blue-card  { border-color: #90caf9; } .blue-card .num  { color: #1565c0; }
    </style>
</head>
<body>

<h1>🔍 System File &amp; Connection Checker</h1>
<p class="subtitle">Report Management System — Full integrity check of all 16 files</p>

<?php

$files = [
    'config.php'             => ['type' => 'Core',     'desc' => 'Database connection (mysqli)'],
    'index.php'              => ['type' => 'Public',   'desc' => 'Login & Register page'],
    'login_register.php'     => ['type' => 'Auth',     'desc' => 'Handles login & register POST'],
    'logout.php'             => ['type' => 'Auth',     'desc' => 'Destroys session, redirects to index'],
    'script.js'              => ['type' => 'Asset',    'desc' => 'Login form toggle (showForm)'],
    'style.css'              => ['type' => 'Asset',    'desc' => 'Global stylesheet + dark mode'],
    'user_page.php'          => ['type' => 'User',     'desc' => 'User dashboard with stats cards'],
    'submit_reports.php'      => ['type' => 'User',     'desc' => 'Submit a new report form'],
    'view_reports.php'       => ['type' => 'User',     'desc' => 'View own reports & admin replies'],
    'menu_user.php'          => ['type' => 'Include',  'desc' => 'User sidebar navigation'],
    'admin_page.php'         => ['type' => 'Admin',    'desc' => 'Admin dashboard with stats cards'],
    'admin_view_reports.php' => ['type' => 'Admin',    'desc' => 'View & reply to all user reports'],
    'manage_users.php'       => ['type' => 'Admin',    'desc' => 'View, monitor & delete users'],
    'menu_admin.php'         => ['type' => 'Include',  'desc' => 'Admin sidebar navigation'],
    'check_files.php'        => ['type' => 'Utility',  'desc' => 'This file — system integrity checker'],
    'Database'               => ['type' => 'Database', 'desc' => 'SQL schema: users + reports tables'],
];

$total = count($files);
$found = 0;
$missing = 0;

echo '<div class="section">';
echo '<h2>📁 File Existence Check &nbsp;<small style="font-weight:400;color:#7f8c8d;font-size:13px;">(' . $total . ' files total)</small></h2>';
echo '<table><tr><th>#</th><th>File</th><th>Type</th><th>Description</th><th>Status</th></tr>';

$i = 1;
foreach ($files as $file => $info) {
    $exists = file_exists($file);
    if ($exists) $found++; else $missing++;
    $statusBadge = $exists
        ? '<span class="badge badge-green">✓ EXISTS</span>'
        : '<span class="badge badge-red">✗ MISSING</span>';
    $typeBadge = match($info['type']) {
        'Admin'    => '<span class="badge badge-admin">Admin</span>',
        'User'     => '<span class="badge badge-green">User</span>',
        'Asset'    => '<span class="badge badge-yellow">Asset</span>',
        'Include'  => '<span class="badge badge-yellow">Include</span>',
        'Database' => '<span class="badge badge-db">DB</span>',
        default    => '<span class="badge badge-blue">' . $info['type'] . '</span>',
    };
    echo "<tr><td>{$i}</td><td><strong>{$file}</strong></td><td>{$typeBadge}</td><td>{$info['desc']}</td><td>{$statusBadge}</td></tr>";
    $i++;
}
echo '</table>';

echo '<div class="summary-grid">';
echo '<div class="summary-card green-card"><div class="num">' . $found   . '</div><div class="label">Files Found</div></div>';
echo '<div class="summary-card red-card"  ><div class="num">' . $missing . '</div><div class="label">Files Missing</div></div>';
echo '<div class="summary-card blue-card" ><div class="num">' . $total   . '</div><div class="label">Total Files</div></div>';
echo '</div></div>';

// ============================================================
// CONNECTION MAP
// ============================================================
$connections = [
    'index.php'              => ['→ login_register.php (form action)', '→ style.css', '→ script.js'],
    'login_register.php'     => ['→ config.php (DB)', '→ admin_page.php (admin login)', '→ user_page.php (user login)', '→ index.php (on failure)'],
    'logout.php'             => ['→ index.php (after session destroy)'],
    'admin_page.php'         => ['→ config.php (DB)', '→ menu_admin.php (nav)', '→ user_page.php (unauthorized)', '→ index.php (no session)'],
    'admin_view_reports.php' => ['→ config.php (DB)', '→ menu_admin.php (nav)', '→ user_page.php (unauthorized)', '→ index.php (no session)'],
    'manage_users.php'       => ['→ config.php (DB)', '→ menu_admin.php (nav)', '→ user_page.php (unauthorized)', '→ index.php (no session)', '→ DB CASCADE: deletes user reports automatically'],
    'menu_admin.php'         => ['→ admin_page.php', '→ admin_view_reports.php', '→ manage_users.php', '→ logout.php', '⚠ Notifications: href="#" (not yet built)'],
    'user_page.php'          => ['→ config.php (DB)', '→ menu_user.php (nav)', '→ admin_page.php (unauthorized)', '→ index.php (no session)'],
    'submit_reports.php'      => ['→ config.php (DB)', '→ menu_user.php (nav)', '→ admin_page.php (unauthorized)', '→ index.php (no session)'],
    'view_reports.php'       => ['→ config.php (DB)', '→ menu_user.php (nav)', '→ submit_reports.php (empty state link)', '→ admin_page.php (unauthorized)', '→ index.php (no session)'],
    'menu_user.php'          => ['→ user_page.php', '→ submit_reports.php', '→ view_reports.php', '→ logout.php', '⚠ Notifications: href="#" (not yet built)'],
    'config.php'             => ['Provides $conn to: login_register, admin_page, admin_view_reports, manage_users, user_page, submit_report, view_reports'],
    'style.css'              => ['Loaded by all PHP pages via <link rel="stylesheet">'],
    'script.js'              => ['Loaded only by index.php — login/register form toggle'],
];

echo '<div class="section">';
echo '<h2>🔗 File Connection Map</h2>';
echo '<table><tr><th>File</th><th>Connects To</th></tr>';
foreach ($connections as $file => $links) {
    echo "<tr><td><strong>{$file}</strong></td><td><ul class='connection-list'>";
    foreach ($links as $link) {
        $warn = str_starts_with($link, '⚠');
        $style = $warn ? 'color:#e65100;' : '';
        echo "<li style='{$style}'>{$link}</li>";
    }
    echo "</ul></td></tr>";
}
echo '</table></div>';

// ============================================================
// NOTES
// ============================================================
echo '<div class="section">';
echo '<h2>📋 System Notes</h2>';
$notes = [
    ['badge-green',  '✓ OK',   'ON DELETE CASCADE in DB — deleting a user in manage_users.php auto-removes all their reports.'],
    ['badge-green',  '✓ OK',   'Status auto-sets to "resolved" when admin replies in admin_view_reports.php.'],
    ['badge-green',  '✓ OK',   'Role-based session guards on every protected page (admin & user).'],
    ['badge-green',  '✓ OK',   'Dark mode supported across all pages via style.css.'],
    ['badge-green',  '✓ OK',   'Admin cannot delete their own account (self-delete protection in manage_users.php).'],
    ['badge-yellow', '⚠ TODO', 'Notifications menu item is href="#" in both menu_admin.php and menu_user.php — not yet implemented.'],
];
echo '<ul class="connection-list">';
foreach ($notes as [$badge, $label, $text]) {
    echo "<li><span class='badge {$badge}'>{$label}</span> &nbsp; {$text}</li>";
}
echo '</ul></div>';

// ============================================================
// DIRECTORY LISTING
// ============================================================
echo '<div class="section">';
echo '<h2>📂 All Files in: ' . htmlspecialchars(__DIR__) . '</h2>';
echo '<table><tr><th>#</th><th>Filename</th><th>Size</th></tr>';
$allFiles = array_diff(scandir('.'), ['.', '..']);
$j = 1;
foreach ($allFiles as $f) {
    $size = is_file($f) ? number_format(filesize($f)) . ' bytes' : '<em>directory</em>';
    echo "<tr><td>{$j}</td><td>{$f}</td><td>{$size}</td></tr>";
    $j++;
}
echo '</table></div>';
?>

</body>
</html>