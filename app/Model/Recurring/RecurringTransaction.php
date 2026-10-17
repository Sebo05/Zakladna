<?php declare(strict_types=1);

namespace App\Model\Recurring;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use App\Model\User\User;


/**
 * @property int $id {primary}
 * @property User $user {m:1 User, oneSided=true}
 * @property string $type {enum self::TYPE_INCOME, self::TYPE_EXPENSE}
 * @property float $amount
 * @property string $description
 * @property string $category
 * @property string $period {enum self::PERIOD_MONTH, self::PERIOD_YEAR}
 * @property DateTimeImmutable $nextDate
 * @property DateTimeImmutable|null $endDate
 * @property-read bool $finished {virtual}
 */
final class RecurringTransaction extends Entity
{
	public const TYPE_INCOME = 'income';
	public const TYPE_EXPENSE = 'expense';
	public const PERIOD_MONTH = 'month';
	public const PERIOD_YEAR = 'year';


	/** Move to the following occurrence. ponytail: day clamps to month end, so a 31st drifts to 28th after February; store an anchor day if that matters. */
	public function advance(): void
	{
		$this->nextDate = self::addPeriod($this->nextDate, $this->period);
	}


	public static function addPeriod(DateTimeImmutable $date, string $period): DateTimeImmutable
	{
		$months = $period === self::PERIOD_YEAR ? 12 : 1;
		$first = $date->modify('first day of this month')->modify("+$months months");
		$day = min((int) $date->format('j'), (int) $first->format('t'));
		return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day);
	}


	protected function getterFinished(): bool
	{
		return $this->endDate !== null && $this->nextDate > $this->endDate;
	}
}
