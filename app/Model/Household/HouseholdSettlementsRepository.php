<?php declare(strict_types=1);

namespace App\Model\Household;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<HouseholdSettlement>
 */
final class HouseholdSettlementsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [HouseholdSettlement::class];
	}
}
