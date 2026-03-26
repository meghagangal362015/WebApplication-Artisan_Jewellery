-- ============================================
-- Company A — shared hosting (Hostinger / phpMyAdmin)
-- Select your database first, then Import this file
-- (e.g. u305223495_artisanjewelry)
-- ============================================

-- Create the users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL
);

-- Insert sample users (includes Megha Gangal — Company A / Artisan Jewelry)
INSERT INTO users (name, email) VALUES
    ('Alice Johnson', 'alice.johnson@example.com'),
    ('Bob Smith', 'bob.smith@example.com'),
    ('Carol Williams', 'carol.williams@example.com'),
    ('David Brown', 'david.brown@example.com'),
    ('Eva Davis', 'eva.davis@example.com'),
    ('Megha Gangal', 'megha.gangal@artisanjewelrybymegha.com');
