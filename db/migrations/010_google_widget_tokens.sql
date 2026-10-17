-- Sign in with Google: Google account id ("sub") linked to the user
ALTER TABLE `users` ADD COLUMN `google_id` VARCHAR(64) NULL DEFAULT NULL AFTER `password`,
	ADD UNIQUE KEY `google_id` (`google_id`);

-- Desktop widget: long-lived tokens issued after the user approves the widget in the browser (only SHA-256 is stored)
CREATE TABLE `widget_tokens` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`user_id` INT NOT NULL,
	`token_hash` CHAR(64) NOT NULL,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`last_used_at` DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `token_hash` (`token_hash`),
	CONSTRAINT `fk_widget_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
