<?php declare(strict_types=1);

namespace App\Model\CarDocument;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use App\Model\Car\Car;


/**
 * @property int $id {primary}
 * @property Car $car {m:1 Car::$documents}
 * @property string $type {enum self::TYPE_STK, self::TYPE_INSURANCE, self::TYPE_VIGNETTE}
 * @property DateTimeImmutable $expiresAt
 * @property string|null $note
 */
final class CarDocument extends Entity
{
	public const TYPE_STK = 'stk';
	public const TYPE_INSURANCE = 'insurance';
	public const TYPE_VIGNETTE = 'vignette';

	public const TYPE_LABELS = [
		self::TYPE_STK => 'STK',
		self::TYPE_INSURANCE => 'Pojištění',
		self::TYPE_VIGNETTE => 'Dálniční známka',
	];
}
