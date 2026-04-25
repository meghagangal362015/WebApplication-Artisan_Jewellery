<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
/**
 * Get Users API
 * Fetches all users from the database and returns them as JSON
 * Use this for local users or as an endpoint for external companies
 */

// Include the database connection
require_once 'db.php';

// Fetch all users from the database
$sql = "SELECT id, CONCAT(first_name, ' ', last_name) AS name, email, first_name, last_name, home_address, home_phone, cell_phone FROM users ORDER BY id ASC";
$result = $conn->query($sql);

// Check if query succeeded
if (!$result) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database query failed']);
    exit;
}

// Build array of users
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Set JSON header and output
header('Content-Type: application/json');
echo json_encode($users);

// Close connection
$conn->close();
