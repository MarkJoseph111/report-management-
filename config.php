<?php


$host = "turntable.proxy.rlwy.net";
$user = "root";
$password = "KACjxiwcBGxvuBtMORuRgBRRMMcMlLnR";
$database = "railway"; // updated to use ARS database

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: ". $conn->connect_error);
}

?>
