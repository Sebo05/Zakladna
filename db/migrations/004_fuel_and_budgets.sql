-- Fuel log per car. Each refuel also creates an expense transaction (category "Palivo"),
-- linked via transaction_id so edits/deletes stay in sync.
CREATE TABLE fuel_records (
	id INT AUTO_INCREMENT PRIMARY KEY,
	car_id INT NOT NULL,
	transaction_id INT NULL,
	date DATE NOT NULL,
	mileage INT NOT NULL,
	liters DECIMAL(8,2) NOT NULL,
	price_total DECIMAL(10,2) NOT NULL,
	full_tank TINYINT(1) NOT NULL DEFAULT 1,
	station VARCHAR(150) NULL,
	FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE,
	FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monthly spending limit per expense category.
CREATE TABLE budgets (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	category VARCHAR(100) NOT NULL,
	monthly_limit DECIMAL(12,2) NOT NULL,
	UNIQUE KEY uq_budget_user_category (user_id, category),
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
