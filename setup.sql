-- Hostinger-safe SQL:
-- 1) In phpMyAdmin, select your database first (e.g. u305223495_artisanjewlery)
-- 2) Then run/import this script.

-- Canonical schema (no legacy `name` column)
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    home_address VARCHAR(255) NOT NULL,
    home_phone VARCHAR(20) NOT NULL,
    cell_phone VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_users_email UNIQUE (email),
    CONSTRAINT chk_users_home_phone_len CHECK (CHAR_LENGTH(home_phone) >= 7),
    CONSTRAINT chk_users_cell_phone_len CHECK (CHAR_LENGTH(cell_phone) >= 7)
);

-- Upgrade existing table safely (if some columns were missing)
ALTER TABLE users ADD COLUMN IF NOT EXISTS first_name VARCHAR(100) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_name VARCHAR(100) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS home_address VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS home_phone VARCHAR(20) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS cell_phone VARCHAR(20) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE users
    MODIFY first_name VARCHAR(100) NOT NULL,
    MODIFY last_name VARCHAR(100) NOT NULL,
    MODIFY email VARCHAR(255) NOT NULL,
    MODIFY home_address VARCHAR(255) NOT NULL,
    MODIFY home_phone VARCHAR(20) NOT NULL,
    MODIFY cell_phone VARCHAR(20) NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_users_email ON users (email);

-- Optional legacy cleanup:
-- If your table still has old `name` data, run these manually once in phpMyAdmin before dropping `name`.
-- UPDATE users
-- SET
--   first_name = TRIM(SUBSTRING_INDEX(name, ' ', 1)),
--   last_name = TRIM(SUBSTRING(name, LENGTH(SUBSTRING_INDEX(name, ' ', 1)) + 1))
-- WHERE (first_name = '' OR last_name = '') AND name IS NOT NULL AND name <> '' AND name NOT LIKE '%@%';
--
-- UPDATE users
-- SET email = name
-- WHERE name LIKE '%@%' AND (email = '' OR email NOT LIKE '%@%');
--
-- ALTER TABLE users DROP COLUMN name;

INSERT INTO users (first_name, last_name, email, home_address, home_phone, cell_phone) VALUES
    ('Alice', 'Johnson', 'alice.johnson@example.com', '142 Elm St, San Jose, CA 95112', '4085551001', '4085552001'),
    ('Bob', 'Smith', 'bob.smith@example.com', '89 Pine Ave, Santa Clara, CA 95050', '4085551002', '4085552002'),
    ('Carol', 'Williams', 'carol.williams@example.com', '22 Market Rd, Sunnyvale, CA 94086', '4085551003', '4085552003'),
    ('David', 'Brown', 'david.brown@example.com', '400 Willow Dr, Fremont, CA 94536', '5105551004', '5105552004'),
    ('Eva', 'Davis', 'eva.davis@example.com', '63 Blossom Ct, Milpitas, CA 95035', '4085551005', '4085552005'),
    ('Frank', 'Miller', 'frank.miller@example.com', '91 Oakmont Ln, Cupertino, CA 95014', '4085551006', '4085552006'),
    ('Grace', 'Wilson', 'grace.wilson@example.com', '155 Bay View Rd, Redwood City, CA 94063', '6505551007', '6505552007'),
    ('Henry', 'Moore', 'henry.moore@example.com', '270 Heritage Way, San Mateo, CA 94401', '6505551008', '6505552008'),
    ('Ivy', 'Taylor', 'ivy.taylor@example.com', '14 Garden St, Mountain View, CA 94040', '6505551009', '6505552009'),
    ('Jack', 'Anderson', 'jack.anderson@example.com', '301 Sierra Ave, Palo Alto, CA 94301', '6505551010', '6505552010'),
    ('Karen', 'Thomas', 'karen.thomas@example.com', '72 Cedar Park, San Ramon, CA 94582', '9255551011', '9255552011'),
    ('Liam', 'Jackson', 'liam.jackson@example.com', '508 Orchard Blvd, Dublin, CA 94568', '9255551012', '9255552012'),
    ('Maya', 'White', 'maya.white@example.com', '66 Lakewood Dr, Hayward, CA 94541', '5105551013', '5105552013'),
    ('Noah', 'Harris', 'noah.harris@example.com', '804 River St, Union City, CA 94587', '5105551014', '5105552014'),
    ('Olivia', 'Martin', 'olivia.martin@example.com', '217 Sunrise Ct, Campbell, CA 95008', '4085551015', '4085552015'),
    ('Peter', 'Thompson', 'peter.thompson@example.com', '30 Vine St, Los Gatos, CA 95030', '4085551016', '4085552016'),
    ('Quinn', 'Garcia', 'quinn.garcia@example.com', '775 Summit Rd, Saratoga, CA 95070', '4085551017', '4085552017'),
    ('Riya', 'Martinez', 'riya.martinez@example.com', '148 Maple Ln, Morgan Hill, CA 95037', '6695551018', '6695552018'),
    ('Sam', 'Robinson', 'sam.robinson@example.com', '912 Creekside Ave, Gilroy, CA 95020', '4085551019', '4085552019'),
    ('Tina', 'Clark', 'tina.clark@example.com', '54 Pearl St, San Jose, CA 95123', '4085551020', '4085552020')
ON DUPLICATE KEY UPDATE
    updated_at = CURRENT_TIMESTAMP;
