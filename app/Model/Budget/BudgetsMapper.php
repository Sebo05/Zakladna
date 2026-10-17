<?php declare(strict_types=1);

namespace App\Model\Budget;

use Nextras\Orm\Mapper\Dbal\DbalMapper;


final class BudgetsMapper extends DbalMapper
{
	public function getTableName(): string
	{
		return 'budgets';
	}
}
