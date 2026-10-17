<?php declare(strict_types=1);

namespace App\Model\Recurring;

use DateTimeImmutable;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Repository\Repository;
use App\Model\User\User;


/**
 * @extends Repository<RecurringTransaction>
 */
final class RecurringTransactionsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [RecurringTransaction::class];
	}


	/** Payments waiting for confirmation (due today or earlier, not past their end). @return list<RecurringTransaction> */
	public function findDue(User $user, ?DateTimeImmutable $until = null): array
	{
		$until ??= new DateTimeImmutable('today');
		$rows = $this->findBy(['user' => $user, 'nextDate<=' => $until])->orderBy('nextDate')->fetchAll();
		return array_values(array_filter($rows, fn(RecurringTransaction $r) => !$r->finished));
	}
}
