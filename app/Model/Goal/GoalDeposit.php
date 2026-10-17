<?php declare(strict_types=1);

namespace App\Model\Goal;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use App\Model\User\User;


/**
 * Amount added to (or withdrawn from, when negative) a goal.
 *
 * @property int $id {primary}
 * @property Goal $goal {m:1 Goal::$deposits}
 * @property User $user {m:1 User, oneSided=true}
 * @property float $amount
 * @property string|null $note
 * @property DateTimeImmutable $date
 */
final class GoalDeposit extends Entity
{
}
