-- Separate admins from end users. Admins manage the CMS,
-- regular users have their own garage/finance/calendar data.

CREATE TABLE admins (
	id INT AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(100) NOT NULL,
	email VARCHAR(150) NOT NULL UNIQUE,
	password VARCHAR(255) NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing admin accounts. Keep the row in users too so existing
-- per-user data (cars, transactions, ...) isn't orphaned.
INSERT INTO admins (id, name, email, password, created_at)
SELECT id, name, email, password, created_at
FROM users
WHERE role = 'admin';

-- The role column on users is no longer meaningful — admins live elsewhere.
ALTER TABLE users DROP COLUMN role;
