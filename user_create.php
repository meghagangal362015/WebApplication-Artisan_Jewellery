<?php
require_once __DIR__ . '/db.php';

$values = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'home_address' => '',
    'home_phone' => '',
    'cell_phone' => '',
];
$errors = [];
$successMessage = '';

function clean_phone($value)
{
    return preg_replace('/\D+/', '', $value ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $default) {
        $values[$key] = trim($_POST[$key] ?? '');
    }

    if ($values['first_name'] === '') {
        $errors[] = 'First name is required.';
    }
    if ($values['last_name'] === '') {
        $errors[] = 'Last name is required.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($values['home_address'] === '') {
        $errors[] = 'Home address is required.';
    }

    $values['home_phone'] = clean_phone($values['home_phone']);
    $values['cell_phone'] = clean_phone($values['cell_phone']);

    if (strlen($values['home_phone']) < 7) {
        $errors[] = 'Home phone must have at least 7 digits.';
    }
    if (strlen($values['cell_phone']) < 7) {
        $errors[] = 'Cell phone must have at least 7 digits.';
    }

    if (empty($errors)) {
        $sql = 'INSERT INTO users (first_name, last_name, email, home_address, home_phone, cell_phone) VALUES (?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $errors[] = 'Unable to prepare database query.';
        } else {
            $stmt->bind_param(
                'ssssss',
                $values['first_name'],
                $values['last_name'],
                $values['email'],
                $values['home_address'],
                $values['home_phone'],
                $values['cell_phone']
            );

            if ($stmt->execute()) {
                $successMessage = 'User created successfully.';
                foreach ($values as $key => $default) {
                    $values[$key] = '';
                }
            } else {
                if ((int) $conn->errno === 1062) {
                    $errors[] = 'A user with this email already exists.';
                } else {
                    $errors[] = 'Unable to save user. Please try again.';
                }
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User | Artisan Jewelry by Megha</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        nav a.active { background: rgba(255, 255, 255, 0.22); color: #e8c9a8; font-weight: 600; pointer-events: none; }
        .form-card { max-width: 760px; margin: 0 auto; background: #fff; border: 1px solid #e8ddd2; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(92, 46, 66, 0.08); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full { grid-column: 1 / -1; }
        label { margin-bottom: 0.3rem; color: #5c2e42; font-weight: 600; }
        input, textarea { padding: 0.6rem 0.7rem; border: 1px solid #dbcfc5; border-radius: 8px; font: inherit; }
        .btn { margin-top: 1rem; padding: 0.7rem 1.2rem; border: 0; border-radius: 8px; background: linear-gradient(135deg, #5c2e42 0%, #7d3c5c 100%); color: #fff; cursor: pointer; }
        .status-error { margin-bottom: 1rem; padding: 0.8rem 0.9rem; border-radius: 8px; background: #fdeaea; color: #8b1f1f; }
        .status-success { margin-bottom: 1rem; padding: 0.8rem 0.9rem; border-radius: 8px; background: #ecf9ef; color: #1d6f2a; }
        @media (max-width: 720px) { .form-grid { grid-template-columns: 1fr; } }
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
            <h1>Create User</h1>
        </div>
    </section>

    <main>
        <div class="container">
            <div class="form-card">
                <p style="margin-bottom: 1rem;"><a href="user.php">Back to User Section</a></p>

                <?php if (!empty($successMessage)): ?>
                    <div class="status-success"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="status-error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="user_create.php" novalidate>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" maxlength="100" required value="<?php echo htmlspecialchars($values['first_name'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" maxlength="100" required value="<?php echo htmlspecialchars($values['last_name'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group full">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" maxlength="255" required value="<?php echo htmlspecialchars($values['email'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group full">
                            <label for="home_address">Home Address</label>
                            <textarea id="home_address" name="home_address" maxlength="255" required><?php echo htmlspecialchars($values['home_address'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="home_phone">Home Phone</label>
                            <input type="tel" id="home_phone" name="home_phone" minlength="7" maxlength="20" pattern="[0-9+\-\s()]{7,20}" required value="<?php echo htmlspecialchars($values['home_phone'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="cell_phone">Cell Phone</label>
                            <input type="tel" id="cell_phone" name="cell_phone" minlength="7" maxlength="20" pattern="[0-9+\-\s()]{7,20}" required value="<?php echo htmlspecialchars($values['cell_phone'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <button class="btn" type="submit">Save User</button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
