<?php declare(strict_types=1);

namespace App\Model\Household;


/**
 * Who owes whom inside a household (Splitwise-style), pure functions.
 */
final class Ledger
{
	/**
	 * Net balance per user: paid − own shares + settlements sent − settlements received.
	 * Positive = others owe this user money.
	 *
	 * @param list<int> $memberIds
	 * @param iterable<array{paidBy: int, amount: float, shares: array<int, float>}> $expenses
	 * @param iterable<array{from: int, to: int, amount: float}> $settlements
	 * @return array<int, float>
	 */
	public static function balances(array $memberIds, iterable $expenses, iterable $settlements): array
	{
		$bal = array_fill_keys($memberIds, 0.0);
		foreach ($expenses as $e) {
			$bal[$e['paidBy']] = ($bal[$e['paidBy']] ?? 0) + $e['amount'];
			foreach ($e['shares'] as $uid => $amt) {
				$bal[$uid] = ($bal[$uid] ?? 0) - $amt;
			}
		}
		foreach ($settlements as $s) {
			$bal[$s['from']] = ($bal[$s['from']] ?? 0) + $s['amount'];
			$bal[$s['to']] = ($bal[$s['to']] ?? 0) - $s['amount'];
		}
		return array_map(fn($v) => round($v, 2), $bal);
	}


	/**
	 * Minimal-ish list of payments that settles all balances (greedy: biggest debtor pays biggest creditor).
	 *
	 * @param array<int, float> $balances
	 * @return list<array{from: int, to: int, amount: float}>
	 */
	public static function simplify(array $balances): array
	{
		$debtors = $creditors = [];
		foreach ($balances as $uid => $b) {
			if ($b < -0.009) {
				$debtors[$uid] = -$b;
			} elseif ($b > 0.009) {
				$creditors[$uid] = $b;
			}
		}
		$out = [];
		while ($debtors && $creditors) {
			arsort($debtors);
			arsort($creditors);
			$d = array_key_first($debtors);
			$c = array_key_first($creditors);
			$amt = round(min($debtors[$d], $creditors[$c]), 2);
			$out[] = ['from' => $d, 'to' => $c, 'amount' => $amt];
			$debtors[$d] -= $amt;
			$creditors[$c] -= $amt;
			if ($debtors[$d] < 0.01) {
				unset($debtors[$d]);
			}
			if ($creditors[$c] < 0.01) {
				unset($creditors[$c]);
			}
		}
		return $out;
	}


	/**
	 * Splits an amount equally; rounding remainder goes to the first member so shares sum exactly.
	 *
	 * @param list<int> $userIds
	 * @return array<int, float>
	 */
	public static function splitEqually(float $amount, array $userIds): array
	{
		$n = count($userIds);
		$base = floor($amount / $n * 100) / 100;
		$shares = array_fill_keys($userIds, $base);
		$shares[$userIds[0]] = round($amount - $base * ($n - 1), 2);
		return $shares;
	}
}
