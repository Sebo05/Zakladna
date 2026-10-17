<?php declare(strict_types=1);

namespace App\Model\Transaction;

use Nextras\Orm\Mapper\Dbal\DbalMapper;


final class TransactionsMapper extends DbalMapper
{
	public function getTableName(): string
	{
		return 'transactions';
	}
}
