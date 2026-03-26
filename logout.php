<?php
/**
 * Logout - Destroys session and redirects to login
 * Artisan Jewelry by Megha - Secure Section
 */

session_start();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
