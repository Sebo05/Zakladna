<?php declare(strict_types=1);

namespace App\Model\Household;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use App\Model\User\User;


/**
 * @property int $id {primary}
 * @property Household $household {m:1 Household::$settlements}
 * @property User $fromUser {m:1 User, oneSided=true}
 * @property User $toUser {m:1 User, oneSided=true}
 * @property float $amount
 * @property DateTimeImmutable $date
 */
final class HouseholdSettlement extends Entity
{
}
