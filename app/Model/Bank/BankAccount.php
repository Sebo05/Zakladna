<?php declare(strict_types=1);

namespace App\Model\Bank;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use App\Model\User\User;


/**
 * Bank connection. `secret` = Fio token / ČS+KB refresh token, `clientSecret`, `apiKey`, `accessToken`,
 * `certPassword` are stored encrypted (see SecretService) — never read them directly in templates.
 * ČSOB: clientId = CEB contract number, accountRef = own account number (optional filter).
 * RB: clientId = X-IBM-Client-Id, accountRef = account number. Certificate file lives in CERT_DIR.
 *
 * @property int $id {primary}
 * @property User $user {m:1 User, oneSided=true}
 * @property string $bank {enum self::BANK_FIO, self::BANK_CS, self::BANK_KB, self::BANK_CSOB, self::BANK_RB}
 * @property string $name
 * @property string|null $secret
 * @property string|null $clientId
 * @property string|null $clientSecret
 * @property string|null $apiKey
 * @property bool $sandbox {default false}
 * @property string|null $accessToken
 * @property DateTimeImmutable|null $tokenExpiresAt
 * @property string|null $certFile
 * @property string|null $certPassword
 * @property string|null $accountRef
 * @property DateTimeImmutable|null $lastSyncAt
 * @property string|null $lastError
 * @property DateTimeImmutable $createdAt {default now}
 */
final class BankAccount extends Entity
{
	public const BANK_FIO = 'fio';
	public const BANK_CS = 'cs';
	public const BANK_KB = 'kb';
	public const BANK_CSOB = 'csob';
	public const BANK_RB = 'rb';
	public const BANKS = [
		self::BANK_FIO => 'Fio banka',
		self::BANK_CS => 'Česká spořitelna',
		self::BANK_KB => 'Komerční banka',
		self::BANK_CSOB => 'ČSOB',
		self::BANK_RB => 'Raiffeisenbank',
	];

	/** Shortest gap between two syncs (Bronze Bank::minSyncMinutes). KB: 1× per 61 min, ČSOB: 30 calls / 20 min. Fio has its own 30 s limit (HTTP 409). */
	public const MIN_SYNC_MINUTES = [self::BANK_KB => 61, self::BANK_CSOB => 30, self::BANK_RB => 15, self::BANK_CS => 15];

	/** Client certificates (ČSOB, RB) — outside the webroot, not in git. */
	public const CERT_DIR = __DIR__ . '/../../../cert/bank';


	public function isConnected(): bool
	{
		return match ($this->bank) {
			self::BANK_FIO => $this->secret !== null,
			self::BANK_CSOB, self::BANK_RB => $this->certFile !== null && $this->clientId !== null,
			default => $this->secret !== null && $this->accountRef !== null,
		};
	}


	public function usesOAuth(): bool
	{
		return $this->bank === self::BANK_CS || $this->bank === self::BANK_KB;
	}
}
