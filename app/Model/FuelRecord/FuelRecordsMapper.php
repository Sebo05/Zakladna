<?php declare(strict_types=1);

namespace App\Model\FuelRecord;

use Nextras\Orm\Mapper\Dbal\DbalMapper;


final class FuelRecordsMapper extends DbalMapper
{
	public function getTableName(): string
	{
		return 'fuel_records';
	}
}
