<?php declare(strict_types=1);

namespace App\Model\Bank;

use Nextras\Orm\Mapper\Dbal\DbalMapper;


final class BankAccountsMapper extends DbalMapper
{
	public function getTableName(): string
	{
		return 'bank_accounts';
	}
}
