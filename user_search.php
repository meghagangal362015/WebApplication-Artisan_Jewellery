<?php
require_once __DIR__ . '/db.php';

$filters = [
    'first_name' => trim($_GET['first_name'] ?? ''),
    'last_name' => trim($_GET['last_name'] ?? ''),
    'email' => trim($_GET['email'] ?? ''),
    'home_phone' => trim($_GET['home_phone'] ?? ''),
    'cell_phone' => trim($_GET['cell_phone'] ?? ''),
];

$results = [];
$searchPerformed = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && array_filter($filters, fn($value) => $value !== '')) {
    $searchPerformed = true;

    $where = [];
    $params = [];
    $types = '';

    foreach ($filters as $column => $value) {
        if ($value === '') {
            continue;
        }
        $where[] = $column . ' LIKE ?';
        $params[] = '%' . $value . '%';
        $types .= 's';
    }

    $sql = 'SELECT id, first_name, last_name, email, home_address, home_phone, cell_phone, created_at FROM users';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY created_at DESC, id DESC';

    $stmt = $conn->prepare($sql);
    if ($stmt && !empty($params)) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $results[] = $row;
            }
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Users | Artisan Jewelry by Megha</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        nav a.active { background: rgba(255, 255, 255, 0.22); color: #e8c9a8; font-weight: 600; pointer-events: none; }
        .search-card { background: #fff; border: 1px solid #e8ddd2; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(92, 46, 66, 0.08); margin-bottom: 1.25rem; }
        .search-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
        .search-group { display: flex; flex-direction: column; }
        label { margin-bottom: 0.35rem; color: #5c2e42; font-weight: 600; }
        input { padding: 0.6rem 0.7rem; border: 1px solid #dbcfc5; border-radius: 8px; font: inherit; }
        .btn { margin-top: 1rem; padding: 0.7rem 1.2rem; border: 0; border-radius: 8px; background: linear-gradient(135deg, #5c2e42 0%, #7d3c5c 100%); color: #fff; cursor: pointer; }
        .results-table-wrap { overflow-x: auto; border: 1px solid #e8ddd2; border-radius: 12px; background: #fff; }
        .results-table { width: 100%; border-collapse: collapse; min-width: 760px; }
        .results-table th { text-align: left; padding: 0.8rem; color: #fff; background: linear-gradient(135deg, #5c2e42 0%, #7d3c5c 100%); }
        .results-table td { padding: 0.75rem 0.8rem; border-bottom: 1px solid #f0ebe5; }
        .results-empty { background: #fff8e8; border: 1px solid #e8c9a8; color: #5c3d20; border-radius: 10px; padding: 0.8rem; }
        @media (max-width: 920px) { .search-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 640px) { .search-grid { grid-template-columns: 1fr; } }
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
            <h1>Search Users</h1>
        </div>
    </section>

    <main>
        <div class="container">
            <div class="search-card">
                <p style="margin-bottom: 1rem;"><a href="user.php">Back to User Section</a></p>
                <form method="GET" action="user_search.php">
                    <div class="search-grid">
                        <div class="search-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($filters['first_name'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="search-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($filters['last_name'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="search-group">
                            <label for="email">Email</label>
                            <input type="text" id="email" name="email" value="<?php echo htmlspecialchars($filters['email'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="search-group">
                            <label for="home_phone">Home Phone</label>
                            <input type="text" id="home_phone" name="home_phone" value="<?php echo htmlspecialchars($filters['home_phone'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="search-group">
                            <label for="cell_phone">Cell Phone</label>
                            <input type="text" id="cell_phone" name="cell_phone" value="<?php echo htmlspecialchars($filters['cell_phone'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <button class="btn" type="submit">Search</button>
                </form>
            </div>

            <?php if ($searchPerformed): ?>
                <?php if (empty($results)): ?>
                    <div class="results-empty">No matching users found.</div>
                <?php else: ?>
                    <div class="results-table-wrap">
                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Home Address</th>
                                    <th>Home Phone</th>
                                    <th>Cell Phone</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($user['home_address'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($user['home_phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($user['cell_phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
