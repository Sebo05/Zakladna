<?php declare(strict_types=1);

namespace App\Model\ServiceRecord;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use App\Model\Car\Car;


/**
 * @property int $id {primary}
 * @property Car $car {m:1 Car::$serviceRecords}
 * @property DateTimeImmutable $date
 * @property int $mileage
 * @property string $description
 * @property float $cost {default 0}
 * @property string|null $shop
 */
final class ServiceRecord extends Entity
{
}
