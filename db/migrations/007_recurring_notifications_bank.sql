-- Recurring payments (rent, Netflix, leasing). Not booked automatically:
-- when next_date is due the app asks the user to confirm or skip.
CREATE TABLE recurring_transactions (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	type ENUM('income','expense') NOT NULL DEFAULT 'expense',
	amount DECIMAL(12,2) NOT NULL,
	description VARCHAR(255) NOT NULL,
	category VARCHAR(100) NOT NULL,
	period ENUM('month','year') NOT NULL DEFAULT 'month',
	next_date DATE NOT NULL,
	end_date DATE NULL,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- E-mail reminders: opt-out per user + log so each reminder goes out once.
ALTER TABLE users ADD COLUMN notify_email TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE notification_log (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	ref VARCHAR(100) NOT NULL,
	sent_at DATETIME NOT NULL,
	UNIQUE KEY uq_notification (user_id, ref),
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bank connections (Fio token / Česká spořitelna OAuth). Secrets are encrypted by the app.
CREATE TABLE bank_accounts (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	bank ENUM('fio','cs') NOT NULL,
	name VARCHAR(100) NOT NULL,
	secret TEXT NULL,
	client_id VARCHAR(255) NULL,
	client_secret TEXT NULL,
	api_key TEXT NULL,
	sandbox TINYINT(1) NOT NULL DEFAULT 0,
	access_token TEXT NULL,
	token_expires_at DATETIME NULL,
	account_ref VARCHAR(255) NULL,
	last_sync_at DATETIME NULL,
	last_error VARCHAR(500) NULL,
	created_at DATETIME NOT NULL,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Imported bank movements: external id for de-duplication.
ALTER TABLE transactions ADD COLUMN external_id VARCHAR(100) NULL,
	ADD UNIQUE KEY uq_transaction_external (user_id, external_id);
