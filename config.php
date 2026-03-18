<?php
$host     = 'localhost';
$user     = 'root';
$password = '';        // XAMPP default is empty
$database = 'report_system';

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>