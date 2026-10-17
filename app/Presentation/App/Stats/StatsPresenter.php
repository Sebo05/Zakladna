<?php declare(strict_types=1);

namespace App\Presentation\App\Stats;

use Nette\Utils\Json;
use App\Presentation\App\BasePresenter;
use App\Model\Transaction\Transaction;


final class StatsPresenter extends BasePresenter
{
	public function renderDefault(): void
	{
		$user = $this->getCurrentUser();
		$now = new \DateTimeImmutable;
		$start = $now->modify('-11 months')->modify('first day of this month')->setTime(0, 0);

		$monthly = [];
		for ($i = 0; $i < 12; $i++) {
			$key = $start->modify("+$i months")->format('Y-m');
			$monthly[$key] = ['income' => 0.0, 'expense' => 0.0];
		}

		$txs = $this->orm->transactions->findBy(['user' => $user, 'date>=' => $start]);
		$byCategory = [];
		$byCar = [];
		foreach ($txs as $t) {
			$mk = $t->date->format('Y-m');
			if (isset($monthly[$mk])) {
				$monthly[$mk][$t->type === Transaction::TYPE_INCOME ? 'income' : 'expense'] += $t->amount;
			}
			if ($t->type === Transaction::TYPE_EXPENSE) {
				$byCategory[$t->category] = ($byCategory[$t->category] ?? 0) + $t->amount;
				$carName = $t->car?->name ?? $t->carName;
				if ($carName !== null) {
					$byCar[$carName] = ($byCar[$carName] ?? 0) + $t->amount;
				}
			}
		}

		arsort($byCategory);
		arsort($byCar);

		$baseOpts = ['responsive' => true, 'maintainAspectRatio' => false];

		$this->template->monthlyData = Json::encode([
			'labels' => array_keys($monthly),
			'datasets' => [
				['label' => 'Příjmy', 'data' => array_column($monthly, 'income'), 'backgroundColor' => '#34c759', 'borderRadius' => 6, 'maxBarThickness' => 22],
				['label' => 'Výdaje', 'data' => array_column($monthly, 'expense'), 'backgroundColor' => '#ff6b6b', 'borderRadius' => 6, 'maxBarThickness' => 22],
			],
		], htmlSafe: true);

		$this->template->categoryData = Json::encode([
			'labels' => array_keys($byCategory),
			'datasets' => [[
				'data' => array_values($byCategory),
				'backgroundColor' => ['#0a84ff', '#ff9f0a', '#30d158', '#bf5af2', '#ff375f', '#64d2ff', '#ffd60a', '#ac8e68'],
				'borderWidth' => 0,
			]],
		], htmlSafe: true);
		$this->template->hasCategory = !empty($byCategory);

		$this->template->carData = Json::encode([
			'labels' => array_keys($byCar),
			'datasets' => [[
				'label' => 'Výdaje',
				'data' => array_values($byCar),
				'backgroundColor' => '#0a84ff', 'borderRadius' => 6, 'maxBarThickness' => 28,
			]],
		], htmlSafe: true);
		$this->template->hasCar = !empty($byCar);

		$this->template->baseOptions = Json::encode($baseOpts);
		$this->template->carOptions = Json::encode($baseOpts + ['plugins' => ['legend' => ['display' => false]]]);
		$this->template->doughnutOptions = Json::encode($baseOpts + ['cutout' => '68%', 'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['usePointStyle' => true, 'boxWidth' => 8, 'padding' => 16]]]]);
	}
}
