<?php declare(strict_types=1);

namespace App\Model\Household;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<Household>
 */
final class HouseholdsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [Household::class];
	}
}
