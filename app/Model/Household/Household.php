<?php declare(strict_types=1);

namespace App\Model\Household;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\OneHasMany;
use App\Model\Goal\Goal;
use App\Model\User\User;


/**
 * @property int $id {primary}
 * @property string $name
 * @property string $inviteCode
 * @property User $owner {m:1 User, oneSided=true}
 * @property DateTimeImmutable $createdAt {default now}
 * @property OneHasMany<User> $members {1:m User::$household}
 * @property OneHasMany<HouseholdExpense> $expenses {1:m HouseholdExpense::$household, cascade=[persist, remove]}
 * @property OneHasMany<HouseholdSettlement> $settlements {1:m HouseholdSettlement::$household, cascade=[persist, remove]}
 * @property OneHasMany<HouseholdItem> $items {1:m HouseholdItem::$household, cascade=[persist, remove]}
 * @property OneHasMany<HouseholdBudget> $budgets {1:m HouseholdBudget::$household, cascade=[persist, remove]}
 * @property OneHasMany<Goal> $goals {1:m Goal::$household, cascade=[persist, remove]}
 */
final class Household extends Entity
{
	public function isMember(User $user): bool
	{
		return $user->household?->id === $this->id;
	}


	/** @return array<int, float> user id => net balance (positive = others owe them) */
	public function getBalances(): array
	{
		$memberIds = [];
		foreach ($this->members as $m) {
			$memberIds[] = $m->id;
		}
		$expenses = [];
		foreach ($this->expenses as $e) {
			$shares = [];
			foreach ($e->shares as $s) {
				$shares[$s->user->id] = $s->amount;
			}
			$expenses[] = ['paidBy' => $e->paidBy->id, 'amount' => $e->amount, 'shares' => $shares];
		}
		$settlements = [];
		foreach ($this->settlements as $s) {
			$settlements[] = ['from' => $s->fromUser->id, 'to' => $s->toUser->id, 'amount' => $s->amount];
		}
		return Ledger::balances($memberIds, $expenses, $settlements);
	}
}
