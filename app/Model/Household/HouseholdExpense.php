<?php declare(strict_types=1);

namespace App\Model\Household;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\OneHasMany;
use App\Model\User\User;


/**
 * @property int $id {primary}
 * @property Household $household {m:1 Household::$expenses}
 * @property User $paidBy {m:1 User, oneSided=true}
 * @property float $amount
 * @property string $description
 * @property string $category
 * @property DateTimeImmutable $date
 * @property OneHasMany<HouseholdExpenseShare> $shares {1:m HouseholdExpenseShare::$expense, cascade=[persist, remove]}
 */
final class HouseholdExpense extends Entity
{
}
