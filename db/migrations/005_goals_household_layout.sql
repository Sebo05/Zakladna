-- Per-user dashboard layout: JSON list of {key, hidden, wide}.
ALTER TABLE users ADD COLUMN dashboard_layout TEXT NULL;

-- Household: a user belongs to at most one. Joined via invite code.
CREATE TABLE households (
	id INT AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(100) NOT NULL,
	invite_code VARCHAR(12) NOT NULL UNIQUE,
	owner_id INT NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (owner_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users ADD COLUMN household_id INT NULL,
	ADD CONSTRAINT fk_users_household FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE SET NULL;

-- Shared expense paid by one member, split into shares among members.
CREATE TABLE household_expenses (
	id INT AUTO_INCREMENT PRIMARY KEY,
	household_id INT NOT NULL,
	paid_by_id INT NOT NULL,
	amount DECIMAL(12,2) NOT NULL,
	description VARCHAR(255) NOT NULL,
	category VARCHAR(100) NOT NULL,
	date DATE NOT NULL,
	FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE CASCADE,
	FOREIGN KEY (paid_by_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE household_expense_shares (
	id INT AUTO_INCREMENT PRIMARY KEY,
	expense_id INT NOT NULL,
	user_id INT NOT NULL,
	amount DECIMAL(12,2) NOT NULL,
	FOREIGN KEY (expense_id) REFERENCES household_expenses(id) ON DELETE CASCADE,
	FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Money sent between members to settle debts.
CREATE TABLE household_settlements (
	id INT AUTO_INCREMENT PRIMARY KEY,
	household_id INT NOT NULL,
	from_user_id INT NOT NULL,
	to_user_id INT NOT NULL,
	amount DECIMAL(12,2) NOT NULL,
	date DATE NOT NULL,
	FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE CASCADE,
	FOREIGN KEY (from_user_id) REFERENCES users(id),
	FOREIGN KEY (to_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shared tasks and shopping list.
CREATE TABLE household_items (
	id INT AUTO_INCREMENT PRIMARY KEY,
	household_id INT NOT NULL,
	kind ENUM('task', 'shopping') NOT NULL,
	title VARCHAR(200) NOT NULL,
	done TINYINT(1) NOT NULL DEFAULT 0,
	assignee_id INT NULL,
	due_date DATE NULL,
	created_by_id INT NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE CASCADE,
	FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL,
	FOREIGN KEY (created_by_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shared monthly budgets, measured against household_expenses.
CREATE TABLE household_budgets (
	id INT AUTO_INCREMENT PRIMARY KEY,
	household_id INT NOT NULL,
	category VARCHAR(100) NOT NULL,
	monthly_limit DECIMAL(12,2) NOT NULL,
	UNIQUE KEY uq_hh_budget (household_id, category),
	FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Savings goals: personal (user_id) or shared (household_id) — exactly one is set.
CREATE TABLE goals (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NULL,
	household_id INT NULL,
	name VARCHAR(120) NOT NULL,
	target_amount DECIMAL(12,2) NOT NULL,
	deadline DATE NULL,
	color VARCHAR(20) NOT NULL DEFAULT 'blue',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE goal_deposits (
	id INT AUTO_INCREMENT PRIMARY KEY,
	goal_id INT NOT NULL,
	user_id INT NOT NULL,
	amount DECIMAL(12,2) NOT NULL,
	note VARCHAR(255) NULL,
	date DATE NOT NULL,
	FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE,
	FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
