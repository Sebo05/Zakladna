<?php declare(strict_types=1);

namespace App\Model\Page;

use Nextras\Orm\Mapper\Dbal\DbalMapper;


final class PagesMapper extends DbalMapper
{
	public function getTableName(): string
	{
		return 'pages';
	}
}
