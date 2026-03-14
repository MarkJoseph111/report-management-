<?php


$host = "localhost";
$user = "root";
$password = "";
$database = "ars_db"; // updated to use ARS database

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: ". $conn->connect_error);
}

?>
