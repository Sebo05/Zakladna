<?php declare(strict_types=1);

namespace App\Model\CalendarEvent;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<CalendarEvent>
 */
final class CalendarEventsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [CalendarEvent::class];
	}
}
