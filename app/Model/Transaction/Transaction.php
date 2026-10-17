<?php declare(strict_types=1);

namespace App\Model\Transaction;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use App\Model\User\User;
use App\Model\Car\Car;


/**
 * @property int $id {primary}
 * @property User $user {m:1 User::$transactions}
 * @property string $type {enum self::TYPE_INCOME, self::TYPE_EXPENSE}
 * @property float $amount
 * @property string $description
 * @property string $category
 * @property Car|null $car {m:1 Car, oneSided=true}
 * @property string|null $carName
 * @property DateTimeImmutable $date
 */
final class Transaction extends Entity
{
	public const TYPE_INCOME = 'income';
	public const TYPE_EXPENSE = 'expense';
}
