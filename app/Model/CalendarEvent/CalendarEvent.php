<?php declare(strict_types=1);

namespace App\Model\CalendarEvent;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use App\Model\User\User;


/**
 * @property int $id {primary}
 * @property User $user {m:1 User::$calendarEvents}
 * @property string $title
 * @property DateTimeImmutable $date
 * @property string|null $description
 * @property string $source {enum self::SOURCE_MANUAL, self::SOURCE_GARAGE} {default self::SOURCE_MANUAL}
 * @property int|null $sourceId
 */
final class CalendarEvent extends Entity
{
	public const SOURCE_MANUAL = 'manual';
	public const SOURCE_GARAGE = 'garage';
}
