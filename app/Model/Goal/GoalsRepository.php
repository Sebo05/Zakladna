<?php declare(strict_types=1);

namespace App\Model\Goal;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<Goal>
 */
final class GoalsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [Goal::class];
	}
}
