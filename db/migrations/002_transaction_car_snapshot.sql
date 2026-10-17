-- Snapshot of the car name at the time of the transaction.
-- Lets us show "Škoda Octavia (smazáno)" even after the car was deleted.
ALTER TABLE transactions
	ADD COLUMN car_name VARCHAR(100) NULL AFTER car_id;
