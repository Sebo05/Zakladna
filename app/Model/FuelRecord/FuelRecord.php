<?php declare(strict_types=1);

namespace App\Model\FuelRecord;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use App\Model\Car\Car;
use App\Model\Transaction\Transaction;


/**
 * @property int $id {primary}
 * @property Car $car {m:1 Car::$fuelRecords}
 * @property Transaction|null $transaction {m:1 Transaction, oneSided=true}
 * @property DateTimeImmutable $date
 * @property int $mileage
 * @property float $liters
 * @property float $priceTotal
 * @property bool $fullTank {default true}
 * @property string|null $station
 */
final class FuelRecord extends Entity
{
	public const CATEGORY = 'Palivo';
}
