<?php declare(strict_types=1);

namespace App\Model\Recurring;

use Nextras\Orm\Mapper\Dbal\DbalMapper;


final class RecurringTransactionsMapper extends DbalMapper
{
	public function getTableName(): string
	{
		return 'recurring_transactions';
	}
}
