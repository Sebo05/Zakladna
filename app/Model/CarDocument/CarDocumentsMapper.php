<?php declare(strict_types=1);

namespace App\Model\CarDocument;

use Nextras\Orm\Mapper\Dbal\DbalMapper;


final class CarDocumentsMapper extends DbalMapper
{
	public function getTableName(): string
	{
		return 'car_documents';
	}
}
