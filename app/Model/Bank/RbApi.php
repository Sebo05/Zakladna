<?php declare(strict_types=1);

namespace App\Model\Bank;

use DateTimeImmutable;
use Nette\Utils\Random;


/**
 * Raiffeisenbank — Premium API (business API, not PSD2; ported from Bronze OpenBanking).
 * The app is registered on developers.rb.cz (gives X-IBM-Client-Id = clientId), the user generates
 * a client certificate (.p12) in internet banking and allows it the accounts. Calls go over mTLS.
 * History max. 90 days back, 10 calls/s and 5000/day per client.
 * @see https://developers.rb.cz/premium
 */
final class RbApi
{
	/** [production, sandbox] */
	private const Api = ['https://api.rb.cz/rbcz/premium/api', 'https://api.rb.cz/rbcz/premium/mock'];
	private const HistoryDays = 89;
	private const MaxPages = 50;


	/**
	 * @param array{string, ?string} $cert [path, password]
	 * @return list<BankMovement>
	 */
	public function movements(BankAccount $account, DateTimeImmutable $since, array $cert): array
	{
		if (!$account->accountRef || !$account->clientId) {
			throw new BankException('Chybí číslo účtu nebo Client ID (X-IBM-Client-Id).');
		}
		$floor = new DateTimeImmutable('-' . self::HistoryDays . ' days');
		$from = max($since, $floor);
		$url = self::Api[(int) $account->sandbox] . '/accounts/' . rawurlencode($account->accountRef) . '/CZK/transactions?';
		$result = [];
		for ($page = 1; $page <= self::MaxPages; $page++) {
			$data = Http::json('GET', $url . http_build_query([
				'from' => $from->format('Y-m-d\TH:i:s.vP'),
				'to' => (new DateTimeImmutable)->format('Y-m-d\TH:i:s.vP'),
				'page' => $page,
			]), [
				'X-IBM-Client-Id' => $account->clientId,
				'X-Request-Id' => Random::generate(32),
				'Accept' => 'application/json',
			], cert: $cert);
			foreach ((array) Http::get($data, 'transactions') as $row) {
				if ($m = self::map((array) $row)) {
					$result[] = $m;
				}
			}
			if (Http::get($data, 'lastPage') !== false) {
				break;
			}
		}
		return $result;
	}


	/** ISO 20022 row: direction `creditDebitIndication` (docs also say …Indicator) CRDT/DBIT, counterparty in relatedParties.counterParty. */
	public static function map(array $row): ?BankMovement
	{
		$value = Http::get($row, 'amount.value');
		$currency = Http::get($row, 'amount.currency');
		$date = Http::get($row, 'bookingDate', 'valueDate');
		if (!is_numeric($value) || !is_string($currency) || !is_string($date)) {
			return null;
		}
		$credit = strtoupper((string) (Http::get($row, 'creditDebitIndication', 'creditDebitIndicator') ?? 'CRDT')) !== 'DBIT';
		$details = (array) Http::get($row, 'entryDetails.transactionDetails');
		$message = Http::get($details, 'remittanceInformation.unstructured', 'additionalTransactionInformation');
		$name = Http::get($details, 'relatedParties.counterParty.name');
		$id = Http::get($row, 'entryReference')
			?? sha1($date . '|' . $value . '|' . $currency . '|' . Http::get($details, 'references.variableSymbol') . '|' . Http::get($details, 'relatedParties.counterParty.account.accountNumber', 'relatedParties.counterParty.account.iban'));

		return new BankMovement(
			id: 'rb-' . $id,
			date: new DateTimeImmutable(substr($date, 0, 10)),
			amount: ($credit ? 1 : -1) * abs((float) $value),
			currency: strtoupper($currency),
			counterName: is_string($name) ? $name : null,
			message: is_string($message) ? $message : null,
		);
	}
}
