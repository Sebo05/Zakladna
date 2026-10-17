<?php declare(strict_types=1);

namespace App\Model\Bank;

use DateTimeImmutable;
use App\Model\Orm;
use App\Model\Transaction\Transaction;


/**
 * Pulls movements from the bank and books them as transactions.
 * De-duplicated by transactions.external_id; category is guessed from the user's
 * earlier transaction with the same title, otherwise "Nezařazeno".
 */
final class BankSyncService
{
	public const Uncategorized = 'Nezařazeno';
	private const FirstSyncDays = 89; // Fio: older history needs extra authorization in IB
	private const OverlapDays = 5;    // re-read a few days back, banks book late; dedupe handles repeats


	public function __construct(
		private readonly Orm $orm,
		private readonly SecretService $secrets,
	) {
	}


	/** OAuth client for ČS / KB. */
	public function oauth(BankAccount $account): OAuthApi
	{
		return $account->bank === BankAccount::BANK_KB ? new KbApi($this->secrets) : new CsApi($this->secrets);
	}


	/** @return int number of newly imported transactions */
	public function sync(BankAccount $account): int
	{
		$min = BankAccount::MIN_SYNC_MINUTES[$account->bank] ?? 0;
		if ($account->lastSyncAt && ($wait = (int) ceil(($account->lastSyncAt->getTimestamp() + $min * 60 - time()) / 60)) > 0) {
			throw new BankException("Banka povoluje stažení jednou za $min min. Další stažení možné za $wait min."); // not stored as lastError
		}
		try {
			$since = $account->lastSyncAt?->modify('-' . self::OverlapDays . ' days')->setTime(0, 0)
				?? new DateTimeImmutable('-' . self::FirstSyncDays . ' days');
			$movements = match ($account->bank) {
				BankAccount::BANK_FIO => (new FioApi)->movements((string) $this->secrets->decrypt($account->secret), $since),
				BankAccount::BANK_CSOB => (new CsobApi)->movements($account, $since, $this->certificate($account)),
				BankAccount::BANK_RB => (new RbApi)->movements($account, $since, $this->certificate($account)),
				default => $this->oauth($account)->movements($account, $since),
			};
			$count = $this->import($account, $movements);
			$account->lastSyncAt = new DateTimeImmutable;
			$account->lastError = null;
			return $count;
		} catch (BankException $e) {
			$account->lastError = mb_substr($e->getMessage(), 0, 500);
			throw $e;
		} finally {
			$this->orm->bankAccounts->persistAndFlush($account);
		}
	}


	/** @return array{string, ?string} [absolute path, password] for mTLS */
	private function certificate(BankAccount $account): array
	{
		$path = BankAccount::CERT_DIR . '/' . $account->certFile;
		if (!$account->certFile || !is_file($path)) {
			throw new BankException('Chybí soubor klientského certifikátu — připoj banku znovu.');
		}
		return [$path, $this->secrets->decrypt($account->certPassword)];
	}


	/** @param list<BankMovement> $movements */
	public function import(BankAccount $account, array $movements): int
	{
		$user = $account->user;
		$known = $this->orm->transactions->findBy(['user' => $user, 'externalId!=' => null])->fetchPairs(null, 'externalId');
		$known = array_flip($known);
		$categories = $this->orm->transactions->findBy(['user' => $user])->orderBy('date')->fetchPairs('description', 'category');

		$count = 0;
		foreach ($movements as $m) {
			if (isset($known[$m->id]) || $m->amount == 0.0 || $m->currency !== 'CZK') { // ponytail: foreign currency skipped, convert when someone has an EUR account
				continue;
			}
			$t = new Transaction;
			$t->user = $user;
			$t->type = $m->amount > 0 ? Transaction::TYPE_INCOME : Transaction::TYPE_EXPENSE;
			$t->amount = abs($m->amount);
			$t->description = $m->title();
			$t->category = $categories[$t->description] ?? self::Uncategorized;
			$t->date = $m->date;
			$t->externalId = $m->id;
			$this->orm->transactions->persist($t);
			$known[$m->id] = true;
			$count++;
		}
		$this->orm->flush();
		return $count;
	}
}
