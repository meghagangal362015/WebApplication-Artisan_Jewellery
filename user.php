<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Section | Artisan Jewelry by Megha</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        nav a.active { background: rgba(255, 255, 255, 0.22); color: #e8c9a8; font-weight: 600; pointer-events: none; }
        .user-card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; margin-top: 2rem; }
        .user-card-grid-3 { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
        .user-card { background: #fff; border: 1px solid #e8ddd2; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(92, 46, 66, 0.08); }
        .user-card h2 { margin-bottom: 0.5rem; }
        .user-card p { color: #5a4a4a; margin-bottom: 1rem; }
        .user-card a { display: inline-block; padding: 0.55rem 1rem; border-radius: 8px; background: linear-gradient(135deg, #5c2e42 0%, #7d3c5c 100%); color: #fff; text-decoration: none; }
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
            <h1>User Management</h1>
        </div>
    </section>

    <main>
        <div class="container">
            <p>Manage Company A (Artisan Jewelry by Megha) users: view the full list, add a new user, or search.</p>

            <div class="user-card-grid user-card-grid-3">
                <article class="user-card">
                    <h2>Company A users</h2>
                    <p>Browse every user record for Artisan Jewelry by Megha (Company A).</p>
                    <a href="user_browse.php">View user list</a>
                </article>
                <article class="user-card">
                    <h2>Create User</h2>
                    <p>Add a new user with name, email, address, and phone details.</p>
                    <a href="user_create.php">Open User Creation Form</a>
                </article>
                <article class="user-card">
                    <h2>Search Users</h2>
                    <p>Find users by name, email, home phone, or cell phone with partial matching.</p>
                    <a href="user_search.php">Open User Search Form</a>
                </article>
            </div>
        </div>
    </main>
</body>
</html>
