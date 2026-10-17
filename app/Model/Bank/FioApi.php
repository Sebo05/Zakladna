<?php declare(strict_types=1);

namespace App\Model\Bank;

use DateTimeImmutable;


/**
 * Fio banka API — free read-only token from internet banking (Nastavení → API).
 * Limits: one call per 30 s per token; history older than 90 days needs extra authorization in IB.
 * @see https://www.fio.cz/docs/cz/API_Bankovnictvi.pdf
 */
final class FioApi
{
	private const Url = 'https://fioapi.fio.cz/v1/rest/periods/%s/%s/%s/transactions.json';


	/** @return list<BankMovement> */
	public function movements(string $token, DateTimeImmutable $since): array
	{
		$data = Http::json('GET', sprintf(self::Url, rawurlencode($token), $since->format('Y-m-d'), date('Y-m-d')));
		$result = [];
		foreach ((array) Http::get($data, 'accountStatement.transactionList.transaction') as $row) {
			if ($m = self::map((array) $row)) {
				$result[] = $m;
			}
		}
		return $result;
	}


	/** Fio sends `columnN: {value}` — 22 id, 0 date, 1 amount, 14 currency, 10 counter name, 16 message, 7 user note, 25 comment. */
	public static function map(array $row): ?BankMovement
	{
		$col = fn(int $n) => Http::get($row, "column$n.value");
		$id = $col(22);
		$amount = $col(1);
		$date = $col(0);
		if ($id === null || !is_numeric($amount) || !is_string($date)) {
			return null;
		}
		return new BankMovement(
			id: 'fio-' . $id,
			date: new DateTimeImmutable(substr($date, 0, 10)),
			amount: (float) $amount,
			currency: (string) ($col(14) ?? 'CZK'),
			counterName: self::text($col(10)),
			message: self::text($col(16) ?? $col(7) ?? $col(25)),
		);
	}


	private static function text(mixed $v): ?string
	{
		return is_scalar($v) && trim((string) $v) !== '' ? trim((string) $v) : null;
	}
}
