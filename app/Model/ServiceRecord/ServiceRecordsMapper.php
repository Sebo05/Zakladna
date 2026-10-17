<?php declare(strict_types=1);

namespace App\Model\ServiceRecord;

use Nextras\Orm\Mapper\Dbal\DbalMapper;


final class ServiceRecordsMapper extends DbalMapper
{
	public function getTableName(): string
	{
		return 'service_records';
	}
}
