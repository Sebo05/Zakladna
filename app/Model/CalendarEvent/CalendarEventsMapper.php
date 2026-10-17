<?php declare(strict_types=1);

namespace App\Model\CalendarEvent;

use Nextras\Orm\Mapper\Dbal\DbalMapper;


final class CalendarEventsMapper extends DbalMapper
{
	public function getTableName(): string
	{
		return 'calendar_events';
	}
}
