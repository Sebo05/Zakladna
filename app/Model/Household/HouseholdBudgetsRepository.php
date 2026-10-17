<?php declare(strict_types=1);

namespace App\Model\Household;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<HouseholdBudget>
 */
final class HouseholdBudgetsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [HouseholdBudget::class];
	}
}
