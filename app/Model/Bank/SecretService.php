<?php declare(strict_types=1);

namespace App\Model\Bank;


/**
 * Encrypts bank credentials at rest (libsodium secretbox). The key lives in config/app.key
 * (not in git) and is generated on first use — losing it means reconnecting the banks.
 */
final class SecretService
{
	private ?string $key = null;


	public function __construct(
		private readonly string $keyFile = __DIR__ . '/../../../config/app.key',
	) {
	}


	public function encrypt(?string $plain): ?string
	{
		if ($plain === null || $plain === '') {
			return null;
		}
		$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key()));
	}


	public function decrypt(?string $cipher): ?string
	{
		if ($cipher === null || $cipher === '') {
			return null;
		}
		$raw = base64_decode($cipher, true);
		$plain = $raw === false ? false : sodium_crypto_secretbox_open(
			substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
			substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
			$this->key(),
		);
		if ($plain === false) {
			throw new BankException('Uložený přístup nejde dešifrovat (změnil se config/app.key?) — připoj banku znovu.');
		}
		return $plain;
	}


	private function key(): string
	{
		if ($this->key === null) {
			if (!is_file($this->keyFile)) {
				file_put_contents($this->keyFile, base64_encode(sodium_crypto_secretbox_keygen()), LOCK_EX);
			}
			$this->key = base64_decode(trim((string) file_get_contents($this->keyFile)), true) ?: throw new \RuntimeException('Invalid config/app.key');
		}
		return $this->key;
	}
}
