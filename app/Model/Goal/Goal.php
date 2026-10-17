<?php declare(strict_types=1);

namespace App\Model\Goal;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\OneHasMany;
use App\Model\Household\Household;
use App\Model\User\User;


/**
 * Savings goal — personal (user set) or shared by a household (household set).
 *
 * @property int $id {primary}
 * @property User|null $user {m:1 User::$goals}
 * @property Household|null $household {m:1 Household::$goals}
 * @property string $name
 * @property float $targetAmount
 * @property DateTimeImmutable|null $deadline
 * @property string $color {default 'blue'}
 * @property DateTimeImmutable $createdAt {default now}
 * @property OneHasMany<GoalDeposit> $deposits {1:m GoalDeposit::$goal, cascade=[persist, remove], orderBy=[date=DESC]}
 * @property-read float $saved {virtual}
 */
final class Goal extends Entity
{
	public const COLORS = ['blue', 'green', 'orange', 'pink', 'violet', 'teal'];


	protected function getterSaved(): float
	{
		$sum = 0.0;
		foreach ($this->deposits as $d) {
			$sum += $d->amount;
		}
		return $sum;
	}


	public function canAccess(User $user): bool
	{
		return $this->user !== null
			? $this->user->id === $user->id
			: $this->household !== null && $user->household?->id === $this->household->id;
	}


	/** Monthly amount needed to hit the deadline, null without a deadline or when reached. */
	public function getMonthlyNeeded(): ?float
	{
		$left = $this->targetAmount - $this->saved;
		if ($this->deadline === null || $left <= 0) {
			return null;
		}
		$now = new DateTimeImmutable('today');
		$months = max(1, ($this->deadline->format('Y') - $now->format('Y')) * 12 + $this->deadline->format('n') - $now->format('n'));
		return $left / $months;
	}
}
