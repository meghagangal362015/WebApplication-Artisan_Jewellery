<?php
/**
 * Secure Section - Document Listing Current Website Users
 * Artisan Jewelry by Megha
 *
 * This page requires administrator login.
 * Displays a document listing the current users of the website.
 */

session_start();

// Require login - redirect to login page if not authenticated
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Same source as combined_users.php / get_users.php — live rows from the users table (not a static list)
require_once __DIR__ . '/db.php';
$websiteUsers = [];
$result         = $conn->query('SELECT id, name, email FROM users ORDER BY id ASC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $websiteUsers[] = $row;
    }
} else {
    $dbListError = 'Could not load users from the database.';
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Section | Artisan Jewelry by Megha</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .user-list { background: #fff; border: 1px solid #e8ddd2; border-radius: 12px; padding: 2rem; margin: 2rem 0; box-shadow: 0 4px 15px rgba(92, 46, 66, 0.08); }
        .user-list h2 { color: #5c2e42; margin-bottom: 1rem; }
        .user-list ul { list-style: none; }
        .user-list li { padding: 0.75rem 1rem; border-bottom: 1px solid #f0ebe5; color: #3d3535; }
        .user-list li:last-child { border-bottom: none; }
        .admin-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .logout-link { color: #7d3c5c; font-weight: 600; text-decoration: none; padding: 0.5rem 1rem; border-radius: 6px; }
        .logout-link:hover { background: rgba(125, 60, 92, 0.1); }
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
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section class="page-title">
        <div class="container">
            <h1>Secure Section</h1>
        </div>
    </section>

    <main>
        <div class="container">
            <div class="admin-bar">
                <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION['user_id'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></strong></p>
                <a href="logout.php" class="logout-link">Logout</a>
            </div>

            <div class="user-list">
                <h2>Current Website Users</h2>
                <p style="margin-bottom: 1rem; color: #6b5b52;">Same data as the database-backed list (e.g. Combined Users → Company A):</p>
                <?php if (!empty($dbListError)): ?>
                    <p class="error" style="color: #b00020;"><?php echo htmlspecialchars($dbListError, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php elseif (empty($websiteUsers)): ?>
                    <p style="color: #6b5b52;">No users in the <code>users</code> table yet.</p>
                <?php else: ?>
                <ul>
                    <?php foreach ($websiteUsers as $u): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($u['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
                            <?php if (!empty($u['email'])): ?>
                                <span style="color: #6b5b52;"> — <?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </main>

</body>
</html>
