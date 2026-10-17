<?php declare(strict_types=1);

namespace App\Model\Car;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\OneHasMany;
use App\Model\User\User;
use App\Model\ServiceRecord\ServiceRecord;
use App\Model\CarDocument\CarDocument;
use App\Model\FuelRecord\FuelRecord;


/**
 * @property int $id {primary}
 * @property User $user {m:1 User::$cars}
 * @property string $name
 * @property string $licensePlate
 * @property string $plateCountry {default CZ}
 * @property int $year
 * @property float $purchasePrice {default 0}
 * @property string|null $photo
 * @property DateTimeImmutable $createdAt {default now}
 * @property OneHasMany<ServiceRecord> $serviceRecords {1:m ServiceRecord::$car, cascade=[persist, remove]}
 * @property OneHasMany<CarDocument> $documents {1:m CarDocument::$car, cascade=[persist, remove]}
 * @property OneHasMany<FuelRecord> $fuelRecords {1:m FuelRecord::$car, cascade=[persist, remove]}
 */
final class Car extends Entity
{
	/** EU distinguishing signs offered in the plate country picker */
	public const PlateCountries = [
		'CZ' => 'Česko', 'SK' => 'Slovensko', 'D' => 'Německo', 'A' => 'Rakousko', 'PL' => 'Polsko',
		'H' => 'Maďarsko', 'I' => 'Itálie', 'F' => 'Francie', 'NL' => 'Nizozemsko', 'B' => 'Belgie',
		'E' => 'Španělsko', 'HR' => 'Chorvatsko', 'SLO' => 'Slovinsko', 'UA' => 'Ukrajina', 'GB' => 'Velká Británie',
	];
}
