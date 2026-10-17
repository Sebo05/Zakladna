<?php declare(strict_types=1);

namespace App\Presentation\App\Dashboard;

use DateTimeImmutable;
use App\Presentation\App\BasePresenter;
use App\Model\Transaction\Transaction;
use App\Model\FuelRecord\FuelRecordsRepository;


final class DashboardPresenter extends BasePresenter
{
	public function renderDefault(): void
	{
		$user = $this->getCurrentUser();
		$this->template->due = $this->orm->recurringTransactions->findDue($user);
		$now = new DateTimeImmutable;
		$monthStart = $now->modify('first day of this month')->setTime(0, 0);
		$monthEnd = $now->modify('last day of this month')->setTime(23, 59, 59);

		// Monthly balance
		$income = 0.0;
		$expense = 0.0;
		$monthTxs = $this->orm->transactions->findBy([
			'user' => $user,
			'date>=' => $monthStart,
			'date<=' => $monthEnd,
		]);
		foreach ($monthTxs as $tx) {
			if ($tx->type === Transaction::TYPE_INCOME) {
				$income += $tx->amount;
			} else {
				$expense += $tx->amount;
			}
		}
		$this->template->income = $income;
		$this->template->expense = $expense;
		$this->template->balance = $income - $expense;

		// Upcoming car documents (next 60 days) — across all user's cars
		$cutoff = $now->modify('+60 days')->setTime(23, 59, 59);
		$userCarIds = [];
		foreach ($user->cars as $car) {
			$userCarIds[] = $car->id;
		}
		$this->template->upcomingDocs = $userCarIds
			? $this->orm->carDocuments
				->findBy(['car' => $userCarIds, 'expiresAt<=' => $cutoff])
				->orderBy('expiresAt')
			: [];

		// Upcoming calendar events (next 7 days)
		$weekAhead = $now->modify('+7 days')->setTime(23, 59, 59);
		$this->template->upcomingEvents = $this->orm->calendarEvents
			->findBy([
				'user' => $user,
				'date>=' => $now->setTime(0, 0),
				'date<=' => $weekAhead,
			])
			->orderBy('date');

		// Last 3 service records (across all user's cars)
		$this->template->recentServices = $userCarIds
			? $this->orm->serviceRecords
				->findBy(['car' => $userCarIds])
				->orderBy('date', 'DESC')
				->limitBy(3)
			: [];

		$this->template->today = $now;

		// Budgets for the current month, most used first
		$spent = $this->orm->transactions->getMonthlyExpensesByCategory($user, $now);
		$budgets = [];
		foreach ($user->budgets as $b) {
			$s = $spent[$b->category] ?? 0.0;
			$budgets[] = ['budget' => $b, 'spent' => $s, 'pct' => $s / $b->monthlyLimit * 100];
		}
		usort($budgets, fn($a, $b) => $b['pct'] <=> $a['pct']);
		$this->template->budgets = array_slice($budgets, 0, 4);

		// Per-car fuel summary
		$carStats = [];
		foreach ($user->cars as $car) {
			$carStats[] = ['car' => $car, 'fuel' => FuelRecordsRepository::computeStats($car->fuelRecords)];
		}
		$this->template->carStats = $carStats;

		// Savings goals (personal + household), closest to completion first
		$goals = $this->orm->goals->findBy(['user' => $user])->fetchAll();
		if ($user->household !== null) {
			$goals = array_merge($goals, $this->orm->goals->findBy(['household' => $user->household])->fetchAll());
		}
		$goals = array_filter($goals, fn($g) => $g->saved < $g->targetAmount);
		usort($goals, fn($a, $b) => $b->saved / $b->targetAmount <=> $a->saved / $a->targetAmount);
		$this->template->goals = array_slice($goals, 0, 3);

		// Household summary
		$hh = $user->household;
		$this->template->household = $hh;
		if ($hh !== null) {
			$this->template->hhBalance = $hh->getBalances()[$user->id] ?? 0.0;
			$this->template->hhTasks = $this->orm->householdItems->findBy(['household' => $hh, 'kind' => 'task', 'done' => false])->orderBy('createdAt', 'DESC')->limitBy(3);
			$this->template->hhShoppingCount = $this->orm->householdItems->findBy(['household' => $hh, 'kind' => 'shopping', 'done' => false])->countStored();
		}

		$this->template->layout = $this->normalizeLayout($user->dashboardLayout);

		$hour = (int) $now->format('G');
		$this->template->greeting = match (true) {
			$hour < 5 => 'Dobrou noc',
			$hour < 10 => 'Dobré ráno',
			$hour < 18 => 'Dobrý den',
			default => 'Dobrý večer',
		};
	}


	/** Saves the customised layout (POSTed as JSON by the dashboard editor). Signals are same-origin only. */
	public function handleSaveLayout(): void
	{
		$data = json_decode((string) $this->getHttpRequest()->getRawBody(), true);
		$layout = is_array($data) ? $this->normalizeLayout(json_encode($data)) : self::DefaultLayout;
		$user = $this->getCurrentUser();
		$user->dashboardLayout = json_encode($layout);
		$this->orm->users->persistAndFlush($user);
		$this->sendJson(['ok' => true]);
	}


	public function handleResetLayout(): void
	{
		$user = $this->getCurrentUser();
		$user->dashboardLayout = null;
		$this->orm->users->persistAndFlush($user);
		$this->flashMessage('Přehled vrácen do výchozího stavu.', 'success');
		$this->redirect('this');
	}


	private const DefaultLayout = [
		['k' => 'balance', 'w' => 2, 'h' => false],
		['k' => 'budgets', 'w' => 1, 'h' => false],
		['k' => 'deadlines', 'w' => 1, 'h' => false],
		['k' => 'cars', 'w' => 1, 'h' => false],
		['k' => 'events', 'w' => 1, 'h' => false],
		['k' => 'goals', 'w' => 1, 'h' => false],
		['k' => 'household', 'w' => 2, 'h' => false],
		['k' => 'services', 'w' => 3, 'h' => false],
	];


	/**
	 * Normalises a stored layout: drops unknown keys and duplicates, clamps widths,
	 * appends widgets added since the user saved (so new features show up).
	 * @return list<array{k: string, w: int, h: bool}>
	 */
	private function normalizeLayout(?string $json): array
	{
		$saved = $json ? json_decode($json, true) : null;
		$items = is_array($saved) ? $saved : self::DefaultLayout;
		$out = [];
		foreach ($items as $it) {
			$k = is_array($it) ? ($it['k'] ?? null) : null;
			if (!is_string($k) || !in_array($k, array_column(self::DefaultLayout, "k"), true) || isset($out[$k])) {
				continue;
			}
			$out[$k] = ['k' => $k, 'w' => max(1, min(3, (int) ($it['w'] ?? 1))), 'h' => (bool) ($it['h'] ?? false)];
		}
		foreach (self::DefaultLayout as $d) {
			$out[$d['k']] ??= $d;
		}
		return array_values($out);
	}
}
