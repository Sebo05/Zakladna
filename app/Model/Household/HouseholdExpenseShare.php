<?php declare(strict_types=1);

namespace App\Model\Household;

use Nextras\Orm\Entity\Entity;
use App\Model\User\User;


/**
 * @property int $id {primary}
 * @property HouseholdExpense $expense {m:1 HouseholdExpense::$shares}
 * @property User $user {m:1 User, oneSided=true}
 * @property float $amount
 */
final class HouseholdExpenseShare extends Entity
{
}
