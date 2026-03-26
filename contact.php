<?php
/**
 * Contact Page - Artisan Jewelry by Megha
 * 
 * This script reads contact information from a text file (contacts.txt)
 * and displays it dynamically on the webpage.
 * 
 * How it works:
 * 1. Define the path to the contacts.txt file (relative to this PHP file)
 * 2. Check if the file exists and is readable
 * 3. Use file_get_contents() to read the entire file into a string
 * 4. Use htmlspecialchars() to prevent XSS - converts special HTML characters to safe entities
 * 5. Display the content in a formatted <pre> block for proper formatting
 */

// Define the path to the contacts text file
// __DIR__ gives us the directory of this PHP file, so we can build a path relative to it
$contactsFile = __DIR__ . '/data/contacts.txt';

// Initialize a variable to hold the contact content (for display)
$contactContent = '';
$errorMessage = '';

// Check if the file exists before attempting to read it
if (file_exists($contactsFile)) {
    
    // Check if the file is readable (permissions check)
    if (is_readable($contactsFile)) {
        
        // Read the entire contents of the file into a string
        // file_get_contents() is a simple, efficient way to read text files in PHP
        $contactContent = file_get_contents($contactsFile);
        
        // Sanitize the output to prevent XSS (Cross-Site Scripting) attacks
        // htmlspecialchars() converts <, >, ", ', & to HTML entities so they display safely
        $contactContent = htmlspecialchars($contactContent, ENT_QUOTES, 'UTF-8');
        
    } else {
        $errorMessage = 'Sorry, the contact file could not be read. Please try again later.';
    }
    
} else {
    $errorMessage = 'Sorry, the contact information is temporarily unavailable. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | Artisan Jewelry by Megha</title>
    <link rel="stylesheet" href="css/style.css">
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
                    <li><a href="login.php">Login</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section class="page-title">
        <div class="container">
            <h1>Contact Us</h1>
        </div>
    </section>

    <main>
        <div class="container">
            <div class="content-section">
                <h2>Get in Touch</h2>
                <p>We'd love to hear from you! Whether you have questions about our pieces, want to place a custom order, or just want to say hello—reach out using the contact information below.</p>
            </div>

            <div class="contact-display">
                <?php if (!empty($errorMessage)): ?>
                    <!-- Display error message if file read failed -->
                    <p style="color: #c0392b;"><?php echo $errorMessage; ?></p>
                <?php else: ?>
                    <!-- Display the contact information read from the text file -->
                    <pre><?php echo $contactContent; ?></pre>
                <?php endif; ?>
            </div>
        </div>
    </main>

</body>
</html>
