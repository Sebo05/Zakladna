-- Vehicle identification number (17 chars, ISO 3779), optional
ALTER TABLE `cars` ADD COLUMN `vin` CHAR(17) NULL DEFAULT NULL AFTER `plate_country`;
