-- Country of the license plate (EU distinguishing sign, e.g. CZ, SK, D)
ALTER TABLE `cars` ADD COLUMN `plate_country` VARCHAR(3) NOT NULL DEFAULT 'CZ' AFTER `license_plate`;
