<?php declare(strict_types=1);

namespace App\Model\Bank;

use DateTimeImmutable;
use DateTimeZone;
use Nette\Utils\Random;


/**
 * Komerční banka — Account Direct Access API (ADAA v2, ported from Bronze OpenBanking).
 * The app is registered on the KB API portal (software statement + client registration) which gives
 * client_id, client_secret and apiKey; the user then grants access in the bank (OAuth2 scope `adaa`).
 * Accounts are addressed by `accountId`, not IBAN. Without the notification API history may be read
 * once per 61 minutes (BankAccount::MIN_SYNC_MINUTES).
 * @see https://www.kb.cz/cs/kbapi
 */
final class KbApi extends OAuthApi
{
	/** [production, sandbox] */
	private const Api = ['https://api-gateway.kb.cz/adaa/v2', 'https://api-gateway.kb.cz/sandbox/adaa/v2'];
	protected const Authorize = ['https://login.kb.cz/autfe/ssologin', 'https://api-gateway.kb.cz/sandbox/oauth2-authorization-ui/v3/'];
	protected const Token = ['https://api-gateway.kb.cz/oauth2/v3/access_token', 'https://api-gateway.kb.cz/sandbox/oauth2/v3/access_token'];
	protected const Scope = 'adaa';
	private const PageSize = 100;
	private const MaxPages = 50;


	/** @return list<array{id: string, label: string}> */
	public function accounts(BankAccount $account): array
	{
		$data = Http::json('GET', self::Api[(int) $account->sandbox] . '/accounts', $this->headers($account));
		$result = [];
		foreach (array_is_list($data) ? $data : (array) Http::get($data, 'content') as $row) {
			$row = (array) $row;
			if ($id = Http::get($row, 'accountId', 'id')) {
				$result[] = ['id' => (string) $id, 'label' => trim(Http::get($row, 'iban') . ' ' . Http::get($row, 'currency')) ?: (string) $id];
			}
		}
		return $result;
	}


	/** @return list<BankMovement> */
	public function movements(BankAccount $account, DateTimeImmutable $since): array
	{
		$url = self::Api[(int) $account->sandbox] . '/accounts/' . rawurlencode((string) $account->accountRef) . '/transactions?';
		$utc = new DateTimeZone('UTC');
		$result = [];
		for ($page = 0; $page < self::MaxPages; $page++) {
			$data = Http::json('GET', $url . http_build_query([
				'page' => $page,
				'size' => self::PageSize,
				'fromDateTime' => $since->setTimezone($utc)->format('Y-m-d\TH:i:s.v\Z'),
				'toDateTime' => (new DateTimeImmutable('now', $utc))->format('Y-m-d\TH:i:s.v\Z'),
			]), $this->headers($account));
			foreach ((array) Http::get($data, 'content') as $row) {
				if ($m = self::map((array) $row)) {
					$result[] = $m;
				}
			}
			if (Http::get($data, 'last') !== false) {
				break;
			}
		}
		return $result;
	}


	/** ADAA row: `references.accountServicer` is the unique id, direction CREDIT/DEBIT, counterparty in `counterParty`. */
	public static function map(array $row): ?BankMovement
	{
		$value = Http::get($row, 'amount.value');
		$currency = Http::get($row, 'amount.currency');
		$date = Http::get($row, 'bookingDate', 'valueDate', 'lastUpdated');
		if (!is_numeric($value) || !is_string($currency) || !is_string($date)) {
			return null;
		}
		$credit = strtoupper((string) (Http::get($row, 'creditDebitIndicator') ?? 'CREDIT')) !== 'DEBIT';
		$message = Http::get($row, 'references.receiver', 'additionalTransactionInformation', 'references.myDescription');
		$message = is_array($message) ? implode(' ', array_filter($message, 'is_string')) : $message;
		$name = Http::get($row, 'counterParty.name');
		$id = Http::get($row, 'references.accountServicer', 'entryReference')
			?? sha1($date . '|' . $value . '|' . $currency . '|' . Http::get($row, 'references.variable') . '|' . Http::get($row, 'counterParty.accountNo', 'counterParty.iban'));

		return new BankMovement(
			id: 'kb-' . $id,
			date: new DateTimeImmutable(substr($date, 0, 10)),
			amount: ($credit ? 1 : -1) * abs((float) $value),
			currency: strtoupper($currency),
			counterName: is_string($name) ? $name : null,
			message: is_string($message) && trim($message) !== '' ? trim($message) : null,
		);
	}


	protected function apiKeyHeaders(BankAccount $account): array
	{
		return ['apiKey' => (string) $this->secrets->decrypt($account->apiKey), 'X-Correlation-Id' => Random::generate(32)];
	}
}
