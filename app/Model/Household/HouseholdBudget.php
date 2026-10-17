<?php declare(strict_types=1);

namespace App\Model\Household;

use Nextras\Orm\Entity\Entity;


/**
 * @property int $id {primary}
 * @property Household $household {m:1 Household::$budgets}
 * @property string $category
 * @property float $monthlyLimit
 */
final class HouseholdBudget extends Entity
{
}
