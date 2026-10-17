<?php declare(strict_types=1);

namespace App\Model\Household;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<HouseholdItem>
 */
final class HouseholdItemsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [HouseholdItem::class];
	}
}
