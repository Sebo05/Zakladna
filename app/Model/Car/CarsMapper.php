<?php declare(strict_types=1);

namespace App\Model\Car;

use Nextras\Orm\Mapper\Dbal\DbalMapper;


final class CarsMapper extends DbalMapper
{
	public function getTableName(): string
	{
		return 'cars';
	}
}
