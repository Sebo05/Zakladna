<?php declare(strict_types=1);

namespace App\Model\Household;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use App\Model\User\User;


/**
 * @property int $id {primary}
 * @property Household $household {m:1 Household::$items}
 * @property string $kind {enum self::KIND_TASK, self::KIND_SHOPPING}
 * @property string $title
 * @property bool $done {default false}
 * @property User|null $assignee {m:1 User, oneSided=true}
 * @property DateTimeImmutable|null $dueDate
 * @property User $createdBy {m:1 User, oneSided=true}
 * @property DateTimeImmutable $createdAt {default now}
 */
final class HouseholdItem extends Entity
{
	public const KIND_TASK = 'task';
	public const KIND_SHOPPING = 'shopping';
}
