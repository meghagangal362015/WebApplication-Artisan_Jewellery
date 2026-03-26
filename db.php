<?php
/**
 * Database Connection File
 * Connects to MySQL using mysqli for Company A
 * XAMPP default: user=root, password=empty
 * On macOS, use 127.0.0.1 instead of localhost to avoid socket "No such file or directory" errors.
 */

// Database configuration
$host     = 'localhost'; // try this FIRST on Hostinger
$username = 'u305223495_megha';
$password = 'Goldengirl@22071996';
$database = 'u305223495_artisanjewlery';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
?>
