<?php declare(strict_types=1);

namespace App\Model\ServiceRecord;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<ServiceRecord>
 */
final class ServiceRecordsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [ServiceRecord::class];
	}
}
