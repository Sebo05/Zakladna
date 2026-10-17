<?php declare(strict_types=1);

namespace App\Model\Budget;

use Nextras\Orm\Entity\Entity;
use App\Model\User\User;


/**
 * @property int $id {primary}
 * @property User $user {m:1 User::$budgets}
 * @property string $category
 * @property float $monthlyLimit
 */
final class Budget extends Entity
{
}
