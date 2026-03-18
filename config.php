<?php
// ── Railway MySQL connection ──────────────────────────────────────
// Railway provides these as environment variables automatically.
// No hardcoding needed — just make sure your Railway project has
// a MySQL plugin added and these variables will be set for you.

$host     = getenv('MYSQLHOST')     ?: getenv('DB_HOST')     ?: 'localhost';
$user     = getenv('MYSQLUSER')     ?: getenv('DB_USER')     ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: getenv('DB_PASSWORD') ?: '';
$database = getenv('MYSQLDATABASE') ?: getenv('DB_NAME')     ?: 'report_system';
$port     = getenv('MYSQLPORT')     ?: getenv('DB_PORT')     ?: 3306;

$conn = new mysqli($host, $user, $password, $database, (int)$port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>
