<?php declare(strict_types=1);

namespace App\Model\Household;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<HouseholdExpense>
 */
final class HouseholdExpensesRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [HouseholdExpense::class];
	}
}
