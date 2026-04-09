<?php
/**
 * Login Page - Administrator Authentication
 * Artisan Jewelry by Megha - Secure Section
 *
 * Uses Message Digest (Hash-Based Password Authentication):
 * - Valid userid: admin
 * - Password stored as SHA-256 hash (message digest) - never plain text
 * - On login: hash submitted password and compare with stored hash
 * - Session-based: sets session on success, redirects to secure section
 */

session_start();

// If already logged in, redirect to secure section
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: secure.php');
    exit;
}

// Administrator credentials - Message Digest (Hash-Based) Authentication
$valid_userid = 'admin';
// Store password as SHA-256 message digest (hash) - plain text "admin123" is never stored
$valid_password_hash = hash('sha256', 'admin123');

$errorMessage = '';

// Process login form (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userid = isset($_POST['userid']) ? trim($_POST['userid']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Check userid and compare password hash (message digest) with stored hash
    $password_hash = hash('sha256', $password);
    if ($userid === $valid_userid && $password_hash === $valid_password_hash) {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $userid;
        header('Location: secure.php');
        exit;
    } else {
        $errorMessage = 'Invalid User ID or Password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Artisan Jewelry by Megha</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .login-container { max-width: 400px; margin: 2rem auto; padding: 2rem; background: #fff; border: 1px solid #e8ddd2; border-radius: 12px; box-shadow: 0 4px 20px rgba(92, 46, 66, 0.15); }
        .login-container h2 { color: #5c2e42; margin-bottom: 1.5rem; text-align: center; }
        .login-form label { display: block; margin-bottom: 0.5rem; color: #5c2e42; font-weight: 500; }
        .login-form input { width: 100%; padding: 0.75rem; margin-bottom: 1rem; border: 1px solid #e8ddd2; border-radius: 6px; font-size: 1rem; }
        .login-form button { width: 100%; padding: 0.75rem; background: linear-gradient(135deg, #5c2e42 0%, #7d3c5c 100%); color: #fff; border: none; border-radius: 6px; font-size: 1rem; cursor: pointer; }
        .login-error { color: #c0392b; background: #fdeaea; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">Artisan Jewelry <span>by Megha</span></div>
            <nav>
                <ul>
                    <li><a href="index.html">Home</a></li>
                    <li><a href="about.html">About</a></li>
                    <li><a href="products.html">Products & Services</a></li>
                    <li><a href="news.html">News</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="combined_users.php">Combined Users</a></li>
                    <li><a href="login.php">Login</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section class="page-title">
        <div class="container">
            <h1>Administrator Login</h1>
        </div>
    </section>

    <main>
        <div class="container">
            <div class="login-container">
                <h2>Sign In</h2>
                <?php if (!empty($errorMessage)): ?>
                    <div class="login-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <form class="login-form" method="POST" action="login.php">
                    <label for="userid">User ID</label>
                    <input type="text" id="userid" name="userid" required>

                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>

                    <button type="submit">Login</button>
                </form>
            </div>
        </div>
    </main>

</body>
</html>
