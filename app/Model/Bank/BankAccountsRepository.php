<?php declare(strict_types=1);

namespace App\Model\Bank;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<BankAccount>
 */
final class BankAccountsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [BankAccount::class];
	}
}
