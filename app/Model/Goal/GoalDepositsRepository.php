<?php declare(strict_types=1);

namespace App\Model\Goal;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<GoalDeposit>
 */
final class GoalDepositsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [GoalDeposit::class];
	}
}
