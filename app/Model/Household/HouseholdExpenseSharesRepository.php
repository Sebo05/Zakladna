<?php declare(strict_types=1);

namespace App\Model\Household;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<HouseholdExpenseShare>
 */
final class HouseholdExpenseSharesRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [HouseholdExpenseShare::class];
	}
}
