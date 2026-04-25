<?php
$host = 'localhost'; // or the exact MySQL host from Hostinger hPanel
$username = 'u305223495_megha';
$password = 'Goldengirl@22071996';
$database = 'u305223495_artisanjewlery';
$port = 3306;

$conn = new mysqli($host, $username, $password, $database, $port);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
?>