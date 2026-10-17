<?php declare(strict_types=1);

namespace App\Model\Budget;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<Budget>
 */
final class BudgetsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [Budget::class];
	}
}
