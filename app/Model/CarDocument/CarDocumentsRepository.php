<?php declare(strict_types=1);

namespace App\Model\CarDocument;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<CarDocument>
 */
final class CarDocumentsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [CarDocument::class];
	}
}
