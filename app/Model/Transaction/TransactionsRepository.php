<?php declare(strict_types=1);

namespace App\Model\Transaction;

use DateTimeImmutable;
use Nextras\Orm\Repository\Repository;
use App\Model\User\User;


/**
 * @extends Repository<Transaction>
 */
final class TransactionsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [Transaction::class];
	}


	/** @return array<string, float> category => spent, for the month containing $month */
	public function getMonthlyExpensesByCategory(User $user, DateTimeImmutable $month): array
	{
		$rows = $this->findBy([
			'user' => $user,
			'type' => Transaction::TYPE_EXPENSE,
			'date>=' => $month->modify('first day of this month')->setTime(0, 0),
			'date<=' => $month->modify('last day of this month')->setTime(23, 59, 59),
		]);
		$sums = [];
		foreach ($rows as $t) {
			$sums[$t->category] = ($sums[$t->category] ?? 0) + $t->amount;
		}
		return $sums;
	}


	/** @return list<string> distinct categories the user has ever used, sorted */
	public function getCategories(User $user): array
	{
		$cats = array_values(array_unique(array_filter($this->findBy(['user' => $user])->fetchPairs(null, 'category'))));
		sort($cats);
		return $cats;
	}
}
