<?php
require_once __DIR__ . '/db.php';

$allUsers = [];
$dbError  = null;

$result = $conn->query(
    'SELECT id, first_name, last_name, email, home_address, home_phone, cell_phone, created_at, updated_at
     FROM users
     ORDER BY id ASC'
);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $allUsers[] = $row;
    }
} else {
    $dbError = $conn->error;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company A users | Artisan Jewelry by Megha</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        nav a.active { background: rgba(255, 255, 255, 0.22); color: #e8c9a8; font-weight: 600; pointer-events: none; }
        .db-table-wrap { overflow-x: auto; border: 1px solid #e8ddd2; border-radius: 12px; background: #fff; box-shadow: 0 4px 15px rgba(92, 46, 66, 0.08); }
        .db-table { width: 100%; border-collapse: collapse; min-width: 1100px; font-size: 0.95rem; }
        .db-table th {
            text-align: left; padding: 0.7rem 0.6rem; color: #fff;
            background: linear-gradient(135deg, #5c2e42 0%, #7d3c5c 100%);
            font-weight: 600; white-space: nowrap; position: sticky; top: 0;
        }
        .db-table td { padding: 0.6rem; border-bottom: 1px solid #f0ebe5; vertical-align: top; word-break: break-word; }
        .db-table tbody tr:nth-child(even) { background: #fdf9f5; }
        .db-table tbody tr:hover { background: rgba(125, 60, 92, 0.06); }
        .db-table .num { text-align: right; font-variant-numeric: tabular-nums; color: #5c2e42; }
        .db-empty { background: #fff8e8; border: 1px solid #e8c9a8; color: #5c3d20; border-radius: 10px; padding: 0.9rem; }
        .back-link { display: inline-block; margin-bottom: 1rem; }
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
                    <li><a href="user.php" class="active" aria-current="page">User</a></li>
                    <li><a href="combined_users.php">Combined Users</a></li>
                    <li><a href="login.php">Login</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section class="page-title">
        <div class="container">
            <h1>Company A — Artisan Jewelry by Megha</h1>
        </div>
    </section>

    <main>
        <div class="container">
            <a class="back-link" href="user.php">Back to User Section</a>

            <?php if ($dbError !== null): ?>
                <div class="db-empty">Could not load users: <?php echo htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php elseif (empty($allUsers)): ?>
                <div class="db-empty">No users found in the <code>users</code> table yet.</div>
            <?php else: ?>
                <p style="margin-bottom:0.75rem;"><strong><?php echo (int) count($allUsers); ?></strong> row(s)</p>
                <div class="db-table-wrap">
                    <table class="db-table">
                        <thead>
                            <tr>
                                <th class="num">id</th>
                                <th>first_name</th>
                                <th>last_name</th>
                                <th>email</th>
                                <th>home_address</th>
                                <th>home_phone</th>
                                <th>cell_phone</th>
                                <th>created_at</th>
                                <th>updated_at</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsers as $u): ?>
                                <tr>
                                    <td class="num"><?php echo (int) $u['id']; ?></td>
                                    <td><?php echo htmlspecialchars($u['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($u['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($u['home_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($u['home_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($u['cell_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($u['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($u['updated_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
