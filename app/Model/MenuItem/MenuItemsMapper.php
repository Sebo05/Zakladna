<?php declare(strict_types=1);

namespace App\Model\MenuItem;

use Nextras\Orm\Mapper\Dbal\DbalMapper;


final class MenuItemsMapper extends DbalMapper
{
	public function getTableName(): string
	{
		return 'menu_items';
	}
}
