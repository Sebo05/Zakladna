<?php declare(strict_types=1);

namespace App\Model\Car;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<Car>
 */
final class CarsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [Car::class];
	}
}
