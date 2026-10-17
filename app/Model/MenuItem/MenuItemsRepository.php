<?php declare(strict_types=1);

namespace App\Model\MenuItem;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<MenuItem>
 */
final class MenuItemsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [MenuItem::class];
	}
}
