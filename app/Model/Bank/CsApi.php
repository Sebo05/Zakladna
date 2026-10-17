<?php declare(strict_types=1);

namespace App\Model\Bank;

use DateTimeImmutable;


/**
 * Česká spořitelna — Accounts API v3 (Erste Developer Portal, ported from Bronze OpenBanking).
 * The user registers an app on developers.erstegroup.com (product Accounts API) and gets
 * WEB-API-key, client_id and client_secret; the redirect URI must match our callback.
 * OAuth2 scope `siblings.accounts`, refresh token valid max 90 days — then connect again.
 * Production endpoints follow the portal docs; verify them once the bank approves the app.
 */
final class CsApi extends OAuthApi
{
	/** [production, sandbox] */
	private const Api = ['https://www.csas.cz/webapi/api/v3/accounts', 'https://webapi.developers.erstegroup.com/api/csas/public/sandbox/v3/accounts'];
	protected const Authorize = ['https://bezpecnost.csas.cz/mep/fs/fl/oauth2/auth', 'https://webapi.developers.erstegroup.com/api/csas/sandbox/v1/sandbox-idp/auth'];
	protected const Token = ['https://www.csas.cz/widp/oauth2/token', 'https://webapi.developers.erstegroup.com/api/csas/sandbox/v1/sandbox-idp/token'];
	protected const Scope = 'siblings.accounts';
	protected const AuthorizeParams = ['access_type' => 'offline'];
	private const PageSize = 100;
	private const MaxPages = 50;


	/** @return list<array{id: string, label: string}> */
	public function accounts(BankAccount $account): array
	{
		$data = Http::json('GET', self::Api[(int) $account->sandbox] . '/my/accounts', $this->headers($account));
		$result = [];
		foreach ((array) Http::get($data, 'accounts') as $row) {
			$row = (array) $row;
			if ($id = Http::get($row, 'id')) {
				$result[] = ['id' => (string) $id, 'label' => trim(Http::get($row, 'identification.iban') . ' ' . Http::get($row, 'currency')) ?: (string) $id];
			}
		}
		return $result;
	}


	/** @return list<BankMovement> */
	public function movements(BankAccount $account, DateTimeImmutable $since): array
	{
		$url = self::Api[(int) $account->sandbox] . '/my/accounts/' . rawurlencode((string) $account->accountRef) . '/transactions?';
		$result = [];
		for ($page = 0; $page < self::MaxPages; $page++) {
			$data = Http::json('GET', $url . http_build_query([
				'fromDate' => $since->format('Y-m-d'), 'toDate' => date('Y-m-d'), 'size' => self::PageSize, 'page' => $page,
			]), $this->headers($account));
			foreach ((array) Http::get($data, 'transactions') as $row) {
				if ($m = self::map((array) $row)) {
					$result[] = $m;
				}
			}
			if ($page + 1 >= (int) (Http::get($data, 'pageCount') ?? 1)) {
				break;
			}
		}
		return $result;
	}


	/** ISO 20022 (camt): direction in creditDebitIndicator; counterparty is debtor for incoming, creditor for outgoing. */
	public static function map(array $row): ?BankMovement
	{
		$value = Http::get($row, 'amount.value');
		$date = Http::get($row, 'bookingDate.date', 'bookingDate', 'valueDate.date', 'valueDate');
		if (!is_numeric($value) || !is_string($date)) {
			return null;
		}
		$credit = strtoupper((string) (Http::get($row, 'creditDebitIndicator') ?? 'CRDT')) !== 'DBIT';
		$details = (array) Http::get($row, 'entryDetails.transactionDetails');
		$message = Http::get($details, 'remittanceInformation.unstructured', 'additionalTransactionInformation');
		$name = Http::get($details, 'relatedParties.' . ($credit ? 'debtor' : 'creditor') . '.name');
		$currency = strtoupper((string) (Http::get($row, 'amount.currency') ?? 'CZK'));
		$id = Http::get($row, 'entryReference', 'entryDetails.transactionDetails.references.accountServicerReference')
			?? sha1($date . '|' . $value . '|' . $currency . '|' . (is_string($message) ? $message : ''));

		return new BankMovement(
			id: 'cs-' . $id,
			date: new DateTimeImmutable(substr($date, 0, 10)),
			amount: ($credit ? 1 : -1) * abs((float) $value),
			currency: $currency,
			counterName: is_string($name) ? $name : null,
			message: is_string($message) ? $message : null,
		);
	}


	protected function apiKeyHeaders(BankAccount $account): array
	{
		return ['WEB-API-key' => (string) $this->secrets->decrypt($account->apiKey)];
	}
}
