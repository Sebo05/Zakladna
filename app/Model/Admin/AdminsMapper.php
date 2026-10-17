<?php declare(strict_types=1);

namespace App\Model\Admin;

use Nextras\Orm\Mapper\Dbal\DbalMapper;


final class AdminsMapper extends DbalMapper
{
	public function getTableName(): string
	{
		return 'admins';
	}
}
