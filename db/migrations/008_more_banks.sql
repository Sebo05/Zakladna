-- More banks (ported from Bronze OpenBanking): Komerční banka (OAuth), ČSOB + Raiffeisenbank (client certificate).
-- cert_file = file name in cert/bank/ (outside the webroot), cert_password encrypted by the app.
ALTER TABLE bank_accounts
	MODIFY bank ENUM('fio','cs','kb','csob','rb') NOT NULL,
	ADD COLUMN cert_file VARCHAR(255) NULL AFTER token_expires_at,
	ADD COLUMN cert_password TEXT NULL AFTER cert_file;
