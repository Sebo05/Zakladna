<?php declare(strict_types=1);

namespace App\Model\User;

use Nextras\Orm\Mapper\Dbal\DbalMapper;


final class UsersMapper extends DbalMapper
{
	public function getTableName(): string
	{
		return 'users';
	}
}
